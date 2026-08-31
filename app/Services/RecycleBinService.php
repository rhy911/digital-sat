<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\ClassroomAnnouncement;
use App\Models\ClassroomDocument;
use App\Models\ClassroomEvent;
use App\Models\ExamSession;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class RecycleBinService
{
    /** @var array<int, class-string<Model>> */
    private const PURGE_ORDER = [
        ExamSession::class,
        Assignment::class,
        Module::class,
        Section::class,
        Question::class,
        ClassroomAnnouncement::class,
        ClassroomEvent::class,
        ClassroomDocument::class,
        Test::class,
    ];

    public function expirationDate(): Carbon
    {
        return now()->subDays((int) config('recycle_bin.retention_days', 7));
    }

    /**
     * Return deleted authoring items visible to the signed-in content owner.
     * Child rows remain listed, but are marked as requiring their deleted
     * parent to be restored first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function itemsFor(User $user): Collection
    {
        $items = collect();

        $this->ownedOnlyTrashed(Test::class, $user)->get()->each(function (Test $test) use ($items) {
            $items->push($this->itemPayload('test', $test, $test->title, 'Practice test'));
        });

        $this->ownedOnlyTrashed(Section::class, $user)->with('test')->get()->each(function (Section $section) use ($items) {
            $parentDeleted = (bool) $section->test?->trashed();
            $items->push($this->itemPayload(
                'section',
                $section,
                $section->name ?: 'Untitled section',
                $parentDeleted ? 'Restore the parent test first' : 'Section',
                $parentDeleted,
            ));
        });

        $this->ownedOnlyTrashed(Module::class, $user)->with('section.test')->get()->each(function (Module $module) use ($items) {
            $parentDeleted = (bool) ($module->section?->trashed() || $module->section?->test?->trashed());
            $items->push($this->itemPayload(
                'module',
                $module,
                $module->key ?: 'Untitled module',
                $parentDeleted ? 'Restore the parent test first' : 'Module',
                $parentDeleted,
            ));
        });

        $this->ownedOnlyTrashed(Question::class, $user)->get()->each(function (Question $question) use ($items) {
            $hasDeletedParent = DB::table('module_questions')
                ->join('modules', 'modules.id', '=', 'module_questions.module_id')
                ->join('sections', 'sections.id', '=', 'modules.section_id')
                ->join('tests', 'tests.id', '=', 'sections.test_id')
                ->where('module_questions.question_id', $question->id)
                ->where(function ($query) {
                    $query->whereNotNull('modules.deleted_at')
                        ->orWhereNotNull('sections.deleted_at')
                        ->orWhereNotNull('tests.deleted_at');
                })
                ->exists();
            $items->push($this->itemPayload(
                'question',
                $question,
                strip_tags((string) $question->stem) ?: 'Untitled question',
                $hasDeletedParent ? 'Restore the parent test first' : 'Question bank item',
                $hasDeletedParent,
            ));
        });

        return $items->sortByDesc('deleted_at')->values();
    }

    public function restoreItem(string $type, int $id, User $user): Model
    {
        $modelClass = $this->modelClassFor($type);
        $model = $this->ownedOnlyTrashed($modelClass, $user)->findOrFail($id);

        return DB::transaction(function () use ($type, $model) {
            return match ($type) {
                'test' => $this->restoreTest($model),
                'section' => $this->restoreSection($model),
                'module' => $this->restoreModule($model),
                'question' => $this->restoreQuestion($model),
            };
        });
    }

    /**
     * Permanently delete one visible recycle-bin item.
     *
     * Parent content is deleted only when its trashed descendants are safe to
     * remove. Shared questions and anything referenced by student history are
     * retained rather than breaking another test or score record.
     *
     * @return array{purged:int, skipped:int, message:string}
     */
    public function permanentlyDeleteItem(string $type, int $id, User $user): array
    {
        $modelClass = $this->modelClassFor($type);
        $model = $this->ownedOnlyTrashed($modelClass, $user)->findOrFail($id);

        return DB::transaction(function () use ($type, $model) {
            $purged = match ($type) {
                'test' => $this->permanentlyDeleteTest($model),
                'section' => $this->permanentlyDeleteSection($model),
                'module' => $this->permanentlyDeleteModule($model),
                'question' => $this->permanentlyDeleteQuestion($model),
            };

            return [
                'purged' => $purged,
                'skipped' => 0,
                'message' => $purged > 1
                    ? sprintf('%d related recycle-bin items permanently deleted.', $purged)
                    : 'Item permanently deleted.',
            ];
        });
    }

    /**
     * Permanently delete all visible recycle-bin items.
     *
     * The operation is best-effort: one blocked item must not prevent safe
     * unrelated items from being cleared. Remaining items include the reason
     * in the response so the UI can explain why history was preserved.
     *
     * @return array{purged:int, skipped:int, skipped_items:array<int, array{type:string,id:int,title:string,message:string,references:array}>, message:string}
     */
    public function permanentlyDeleteAll(User $user): array
    {
        $result = ['purged' => 0, 'skipped' => 0, 'skipped_items' => []];
        $items = $this->itemsFor($user);

        // Parent-first lets a successful test purge remove its child rows in
        // one transaction. Later child entries are then harmlessly absent.
        foreach (['test', 'section', 'module', 'question'] as $type) {
            foreach ($items->where('type', $type) as $item) {
                try {
                    $deleted = $this->permanentlyDeleteItem($type, (int) $item['id'], $user);
                    $result['purged'] += $deleted['purged'];
                } catch (ModelNotFoundException) {
                    // A parent purge already removed this child.
                } catch (RecycleBinBlockedException $e) {
                    $result['skipped']++;
                    $result['skipped_items'][] = [
                        'type' => $type,
                        'id' => (int) $item['id'],
                        'title' => (string) $item['title'],
                        'message' => $e->getMessage(),
                        'references' => $e->references,
                    ];
                } catch (ValidationException $e) {
                    $result['skipped']++;
                    $result['skipped_items'][] = [
                        'type' => $type,
                        'id' => (int) $item['id'],
                        'title' => (string) $item['title'],
                        'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                        'references' => [],
                    ];
                }
            }
        }

        $result['message'] = $result['skipped'] > 0
            ? sprintf('%d item(s) permanently deleted; %d kept because they are still referenced.', $result['purged'], $result['skipped'])
            : sprintf('%d recycle-bin item(s) permanently deleted.', $result['purged']);

        return $result;
    }

    /** @param class-string<Model> $modelClass */
    private function ownedOnlyTrashed(string $modelClass, User $user)
    {
        $query = $modelClass::onlyTrashed();

        if ($user->role !== 'admin') {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    /** @return class-string<Model> */
    private function modelClassFor(string $type): string
    {
        return match ($type) {
            'test' => Test::class,
            'section' => Section::class,
            'module' => Module::class,
            'question' => Question::class,
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    private function itemPayload(string $type, Model $model, string $title, string $subtitle, bool $parentDeleted = false): array
    {
        $deletedAt = Carbon::parse($model->deleted_at);
        $expiresAt = $deletedAt->copy()->addDays((int) config('recycle_bin.retention_days', 7));

        return [
            'type' => $type,
            'id' => (int) $model->getKey(),
            'title' => $title,
            'subtitle' => $subtitle,
            'deleted_at' => $deletedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'days_left' => max(0, (int) ceil(now()->diffInSeconds($expiresAt, false) / 86400)),
            'parent_deleted' => $parentDeleted,
        ];
    }

    private function restoreTest(Test $test): Test
    {
        $deletedAt = $test->deleted_at;
        $test->restore();

        $sectionIds = Section::withTrashed()->where('test_id', $test->id)
            ->where('deleted_at', $deletedAt)->pluck('id');
        $this->restoreMatching(Section::withTrashed()->whereIn('id', $sectionIds), $deletedAt);

        $moduleIds = Module::withTrashed()->whereIn('section_id', $sectionIds)
            ->where('deleted_at', $deletedAt)->pluck('id');
        $this->restoreMatching(Module::withTrashed()->whereIn('id', $moduleIds), $deletedAt);

        $questionIds = DB::table('module_questions')->whereIn('module_id', $moduleIds)->pluck('question_id');
        $this->restoreMatching(Question::withTrashed()->whereIn('id', $questionIds), $deletedAt);

        return $test->fresh('sections.modules.questions');
    }

    private function restoreSection(Section $section): Section
    {
        if ($section->test?->trashed()) {
            throw ValidationException::withMessages(['item' => 'Restore the parent test before restoring this section.']);
        }

        $deletedAt = $section->deleted_at;
        $section->restore();
        $moduleIds = Module::withTrashed()->where('section_id', $section->id)
            ->where('deleted_at', $deletedAt)->pluck('id');
        $this->restoreMatching(Module::withTrashed()->whereIn('id', $moduleIds), $deletedAt);
        $questionIds = DB::table('module_questions')->whereIn('module_id', $moduleIds)->pluck('question_id');
        $this->restoreMatching(Question::withTrashed()->whereIn('id', $questionIds), $deletedAt);

        return $section->fresh('modules.questions');
    }

    private function restoreModule(Module $module): Module
    {
        if ($module->section?->trashed() || $module->section?->test?->trashed()) {
            throw ValidationException::withMessages(['item' => 'Restore the parent test before restoring this module.']);
        }

        $deletedAt = $module->deleted_at;
        $module->restore();
        $questionIds = DB::table('module_questions')->where('module_id', $module->id)->pluck('question_id');
        $this->restoreMatching(Question::withTrashed()->whereIn('id', $questionIds), $deletedAt);

        return $module->fresh('questions');
    }

    private function restoreQuestion(Question $question): Question
    {
        $hasDeletedParent = DB::table('module_questions')
            ->join('modules', 'modules.id', '=', 'module_questions.module_id')
            ->join('sections', 'sections.id', '=', 'modules.section_id')
            ->join('tests', 'tests.id', '=', 'sections.test_id')
            ->where('module_questions.question_id', $question->id)
            ->where(function ($query) {
                $query->whereNotNull('modules.deleted_at')
                    ->orWhereNotNull('sections.deleted_at')
                    ->orWhereNotNull('tests.deleted_at');
            })
            ->exists();

        if ($hasDeletedParent) {
            throw ValidationException::withMessages(['item' => 'Restore the parent test before restoring this question.']);
        }

        $question->restore();

        return $question->fresh();
    }

    private function restoreMatching($query, ?Carbon $deletedAt): void
    {
        if (! $deletedAt) {
            return;
        }

        $query->where('deleted_at', $deletedAt)->get()->each->restore();
    }

    /**
     * @param array<int, array{type:string, count:int, action:string, items?:array<int, string>}> $references
     */
    private function blocked(string $message, array $references): never
    {
        throw new RecycleBinBlockedException($message, $references);
    }

    private function permanentlyDeleteTest(Test $test): int
    {
        $attemptCount = DB::table('user_tests')->where('test_id', $test->id)->count();
        if ($attemptCount > 0) {
            $this->blocked('Student attempts still reference this test.', [[
                'type' => 'Student attempts',
                'count' => $attemptCount,
                'action' => 'Keep this test to preserve score history.',
            ]]);
        }

        $activeAssignments = DB::table('assignments')
            ->where('test_id', $test->id)
            ->whereNull('deleted_at')
            ->get(['id', 'title']);
        if ($activeAssignments->isNotEmpty()) {
            $this->blocked('Active assignments still reference this test.', [[
                'type' => 'Active assignments',
                'count' => $activeAssignments->count(),
                'action' => 'Remove the assignments before deleting this test.',
                'items' => $activeAssignments->map(fn ($assignment) => sprintf('#%d %s', $assignment->id, $assignment->title ?: 'Untitled assignment'))->values()->all(),
            ]]);
        }

        $sectionIds = Section::withTrashed()->where('test_id', $test->id)->pluck('id');
        $activeSectionCount = Section::withTrashed()->whereIn('id', $sectionIds)->whereNull('deleted_at')->count();
        $activeModuleCount = Module::withTrashed()->whereIn('section_id', $sectionIds)->whereNull('deleted_at')->count();
        if ($activeSectionCount > 0 || $activeModuleCount > 0) {
            $references = [];
            if ($activeSectionCount > 0) {
                $references[] = [
                    'type' => 'Active sections',
                    'count' => $activeSectionCount,
                    'action' => 'Move the active sections to the recycle bin first.',
                ];
            }
            if ($activeModuleCount > 0) {
                $references[] = [
                    'type' => 'Active modules',
                    'count' => $activeModuleCount,
                    'action' => 'Move the active modules to the recycle bin first.',
                ];
            }
            $this->blocked('Active test content still exists.', $references);
        }

        $purged = 0;
        $moduleIds = Module::withTrashed()->whereIn('section_id', $sectionIds)->pluck('id');
        foreach (Module::withTrashed()->whereIn('id', $moduleIds)->get() as $module) {
            $purged += $this->permanentlyDeleteModule($module);
        }

        $purged += (int) Section::withTrashed()->whereIn('id', $sectionIds)->forceDelete();
        $purged += $this->forceDeleteDeletedAssignmentsForTest($test->id);
        $purged += (int) $test->forceDelete();

        return $purged;
    }

    private function permanentlyDeleteSection(Section $section): int
    {
        $attemptCount = DB::table('user_tests')->where('test_id', $section->test_id)->count();
        if ($attemptCount > 0) {
            $this->blocked('Student attempts still reference this test.', [[
                'type' => 'Student attempts',
                'count' => $attemptCount,
                'action' => 'Keep this section to preserve score history.',
            ]]);
        }

        $modules = Module::withTrashed()->where('section_id', $section->id)->get();
        $activeModuleCount = $modules->filter(fn (Module $module) => ! $module->trashed())->count();
        if ($activeModuleCount > 0) {
            $this->blocked('Active modules still use this section.', [[
                'type' => 'Active modules',
                'count' => $activeModuleCount,
                'action' => 'Move the active modules to the recycle bin first.',
            ]]);
        }

        $purged = 0;
        foreach ($modules as $module) {
            $purged += $this->permanentlyDeleteModule($module);
        }

        $purged += (int) $section->forceDelete();

        return $purged;
    }

    private function permanentlyDeleteModule(Module $module): int
    {
        $activeSectionCount = ($module->section_id && Section::whereKey($module->section_id)->whereNull('deleted_at')->exists()) ? 1 : 0;
        $activeSectionCount += DB::table('section_modules')
            ->join('sections', 'sections.id', '=', 'section_modules.section_id')
            ->where('section_modules.module_id', $module->id)
            ->whereNull('sections.deleted_at')
            ->count();
        if ($activeSectionCount > 0) {
            $this->blocked('An active section still uses this module.', [[
                'type' => 'Active sections',
                'count' => $activeSectionCount,
                'action' => 'Remove this module from active sections first.',
            ]]);
        }

        $answerCount = DB::table('user_test_answers')->where('module_id', $module->id)->count();
        $submissionCount = DB::table('user_test_module_submissions')->where('module_id', $module->id)->count();
        if ($answerCount > 0 || $submissionCount > 0) {
            $references = [];
            if ($answerCount > 0) {
                $references[] = [
                    'type' => 'Student answers',
                    'count' => $answerCount,
                    'action' => 'Keep this module to preserve submitted answers.',
                ];
            }
            if ($submissionCount > 0) {
                $references[] = [
                    'type' => 'Module submissions',
                    'count' => $submissionCount,
                    'action' => 'Keep this module to preserve submission history.',
                ];
            }
            $this->blocked('Student history still references this module.', $references);
        }

        $questionIds = DB::table('module_questions')->where('module_id', $module->id)->pluck('question_id');
        $purged = (int) $module->forceDelete();

        foreach ($questionIds as $questionId) {
            $question = Question::withTrashed()->find($questionId);
            if (! $question || DB::table('module_questions')->where('question_id', $questionId)->exists()) {
                continue;
            }

            if (DB::table('user_test_answers')->where('question_id', $questionId)->exists()) {
                continue;
            }

            $purged += (int) $question->forceDelete();
        }

        return $purged;
    }

    private function permanentlyDeleteQuestion(Question $question): int
    {
        $activeModuleCount = DB::table('module_questions')
            ->join('modules', 'modules.id', '=', 'module_questions.module_id')
            ->where('module_questions.question_id', $question->id)
            ->whereNull('modules.deleted_at')
            ->count();
        if ($activeModuleCount > 0) {
            $this->blocked('An active module still uses this question.', [[
                'type' => 'Active modules',
                'count' => $activeModuleCount,
                'action' => 'Remove the question from active modules first.',
            ]]);
        }

        $answerCount = DB::table('user_test_answers')->where('question_id', $question->id)->count();
        if ($answerCount > 0) {
            $this->blocked('Student history still references this question.', [[
                'type' => 'Student answers',
                'count' => $answerCount,
                'action' => 'Keep this question to preserve submitted answers.',
            ]]);
        }

        return (int) $question->forceDelete();
    }

    private function forceDeleteDeletedAssignmentsForTest(int $testId): int
    {
        $assignmentIds = DB::table('assignments')
            ->where('test_id', $testId)
            ->whereNotNull('deleted_at')
            ->pluck('id');

        if ($assignmentIds->isEmpty()) {
            return 0;
        }

        // Test attempts were checked before this helper runs. Remove join rows
        // first because assignment_recipients restricts assignment deletion.
        DB::table('assignment_recipients')->whereIn('assignment_id', $assignmentIds)->delete();

        return (int) Assignment::withTrashed()->whereIn('id', $assignmentIds)->forceDelete();
    }

    /**
     * Permanently remove expired soft-deleted content.
     *
     * Shared questions and rows still needed by historical attempts are
     * deliberately retained; a later purge can remove them after references
     * disappear.
     *
     * @return array{purged:int, skipped:int}
     */
    public function purgeExpired(): array
    {
        $purged = 0;
        $skipped = 0;
        $cutoff = $this->expirationDate();

        foreach (self::PURGE_ORDER as $modelClass) {
            $modelClass::onlyTrashed()
                ->where('deleted_at', '<=', $cutoff)
                ->orderBy('id')
                ->chunkById(100, function ($models) use ($modelClass, &$purged, &$skipped) {
                    foreach ($models as $model) {
                        if ($modelClass === Question::class && $this->questionIsStillReferenced($model->id)) {
                            $skipped++;
                            continue;
                        }

                        if ($modelClass === Assignment::class && DB::table('user_tests')->where('assignment_id', $model->id)->exists()) {
                            $skipped++;
                            continue;
                        }

                        try {
                            $file = $model instanceof ClassroomDocument && $model->isFile() && $model->disk && $model->path
                                ? [$model->disk, $model->path]
                                : null;

                            DB::transaction(function () use ($model, $modelClass) {
                                if ($modelClass === Assignment::class) {
                                    DB::table('assignment_recipients')->where('assignment_id', $model->id)->delete();
                                }
                                $model->forceDelete();
                            });

                            if ($file) {
                                Storage::disk($file[0])->delete($file[1]);
                            }
                            $purged++;
                        } catch (\Throwable $e) {
                            $skipped++;
                            Log::warning('Recycle bin item could not be purged yet.', [
                                'model' => $model::class,
                                'id' => $model->getKey(),
                                'exception' => $e,
                            ]);
                        }
                    }
                });
        }

        return compact('purged', 'skipped');
    }

    private function questionIsStillReferenced(int $questionId): bool
    {
        return DB::table('module_questions')->where('question_id', $questionId)->exists()
            || DB::table('user_test_answers')->where('question_id', $questionId)->exists();
    }
}
