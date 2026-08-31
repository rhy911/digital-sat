<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TestManagementService
{
    public function __construct(
        private TestStructureService $structures,
        private TestContentCopyService $copies,
        private TestContentLockService $contentLock,
    ) {}



    /**
     * Auto-generate full SAT structure safely using transactions.
     */
    public function generateFullSatStructure(string $title, string $testType, ?int $userId = null): Test
    {
        return $this->createConfiguredTestFromBlueprint([
            'title' => $title,
            'test_type' => $testType,
            'status' => 'draft',
            'break_duration_minutes' => $testType === 'module_only' ? 0 : 10,
            'populate_from_pool' => false,
            'modules' => $this->defaultBlueprintModules($testType),
        ], null, $userId);
    }

    public function createConfiguredTestFromBlueprint(array $blueprint, $user = null, ?int $userId = null): Test
    {
        $this->structures->validateBlueprint($blueprint);
        $modules = collect($blueprint['modules'] ?? [])->values();

        if ($modules->isEmpty()) {
            throw ValidationException::withMessages([
                'modules' => 'At least one module row is required.',
            ]);
        }

        return DB::transaction(function () use ($blueprint, $modules, $user, $userId) {
            $test = Test::create([
                'title' => $blueprint['title'],
                'test_type' => $blueprint['test_type'] ?? 'custom_test',
                'break_duration_minutes' => (int) ($blueprint['break_duration_minutes'] ?? 0),
                'status' => $blueprint['status'] ?? 'draft',
                'created_by' => $userId,
                'is_public' => false,
            ]);

            $sections = [];
            $usedQuestionIds = collect();

            foreach ($modules as $index => $moduleData) {
                $sectionType = $moduleData['section_type'];
                if (! isset($sections[$sectionType])) {
                    $sections[$sectionType] = Section::create([
                        'test_id' => $test->id,
                        'type' => $sectionType,
                        'name' => $sectionType === Section::TYPE_RW ? 'Reading and Writing' : 'Math',
                        'order' => $sectionType === Section::TYPE_RW ? 1 : 2,
                        'created_by' => $userId,
                        'is_public' => false,
                    ]);
                }

                $section = $sections[$sectionType];
                $module = $this->createStandardModuleForSection(
                    $section,
                    (int) $moduleData['module_number'],
                    $moduleData['difficulty_level'],
                    (int) $moduleData['duration_minutes'],
                    (int) $moduleData['total_questions'],
                    $userId,
                    $index + 1
                );

                if (! empty($blueprint['populate_from_pool'])) {
                    $questions = $this->selectQuestionsForModule(
                        $sectionType,
                        (int) $moduleData['total_questions'],
                        $usedQuestionIds->all(),
                        $user
                    );

                    if ($questions->count() < (int) $moduleData['total_questions']) {
                        throw ValidationException::withMessages([
                            "modules.{$index}.total_questions" => sprintf(
                                'Not enough complete %s questions in the pool. Needed %d, found %d.',
                                $sectionType === Section::TYPE_RW ? 'Reading & Writing' : 'Math',
                                (int) $moduleData['total_questions'],
                                $questions->count()
                            ),
                        ]);
                    }

                    foreach ($questions->values() as $position => $question) {
                        $module->questions()->attach($question->id, ['position' => $position + 1]);
                        $usedQuestionIds->push($question->id);
                    }
                }
            }

            $test->refreshTotalDuration();

            if (($blueprint['status'] ?? 'draft') === 'active') {
                $this->structures->validateForPublication($test->fresh());
            }

            return $test->load('sections.modules.questions');
        });
    }

    private function defaultBlueprintModules(string $testType): array
    {
        if ($testType === 'module_only') {
            return [
                $this->moduleRow(Section::TYPE_RW, 1, Module::DIFFICULTY_STANDARD, Module::RW_DURATION, Module::RW_QUESTIONS),
            ];
        }

        if ($testType === 'short_test') {
            return [
                $this->moduleRow(Section::TYPE_RW, 1, Module::DIFFICULTY_STANDARD, 20, 15),
                $this->moduleRow(Section::TYPE_MATH, 1, Module::DIFFICULTY_STANDARD, 20, 12),
            ];
        }

        if ($testType === Test::TYPE_FULL) {
            return [
                $this->moduleRow(Section::TYPE_RW, 1, Module::DIFFICULTY_STANDARD, Module::RW_DURATION, Module::RW_QUESTIONS),
                $this->moduleRow(Section::TYPE_RW, 2, Module::DIFFICULTY_STANDARD, Module::RW_DURATION, Module::RW_QUESTIONS),
                $this->moduleRow(Section::TYPE_MATH, 1, Module::DIFFICULTY_STANDARD, Module::MATH_DURATION, Module::MATH_QUESTIONS),
                $this->moduleRow(Section::TYPE_MATH, 2, Module::DIFFICULTY_STANDARD, Module::MATH_DURATION, Module::MATH_QUESTIONS),
            ];
        }

        return [
            $this->moduleRow(Section::TYPE_RW, 1, Module::DIFFICULTY_STANDARD, Module::RW_DURATION, Module::RW_QUESTIONS),
            $this->moduleRow(Section::TYPE_RW, 2, Module::DIFFICULTY_EASY, Module::RW_DURATION, Module::RW_QUESTIONS),
            $this->moduleRow(Section::TYPE_RW, 2, Module::DIFFICULTY_HARD, Module::RW_DURATION, Module::RW_QUESTIONS),
            $this->moduleRow(Section::TYPE_MATH, 1, Module::DIFFICULTY_STANDARD, Module::MATH_DURATION, Module::MATH_QUESTIONS),
            $this->moduleRow(Section::TYPE_MATH, 2, Module::DIFFICULTY_EASY, Module::MATH_DURATION, Module::MATH_QUESTIONS),
            $this->moduleRow(Section::TYPE_MATH, 2, Module::DIFFICULTY_HARD, Module::MATH_DURATION, Module::MATH_QUESTIONS),
        ];
    }

    private function moduleRow(string $sectionType, int $moduleNumber, string $difficultyLevel, int $durationMinutes, int $totalQuestions): array
    {
        return [
            'section_type' => $sectionType,
            'module_number' => $moduleNumber,
            'difficulty_level' => $difficultyLevel,
            'duration_minutes' => $durationMinutes,
            'total_questions' => $totalQuestions,
        ];
    }

    private function selectQuestionsForModule(string $sectionType, int $limit, array $excludedIds, $user)
    {
        return Question::visibleTo($user)
            ->where('section_type', $sectionType)
            ->where('is_complete', true)
            ->when(! empty($excludedIds), fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->orderBy('is_pretest')
            ->orderBy('difficulty')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Create standard module helper.
     */
    private function createStandardModuleForSection(Section $section, int $moduleNumber, string $difficultyLevel, int $duration, int $totalQuestions, ?int $userId = null, ?int $order = null): Module
    {
        $uniqueKey = strtoupper(substr($section->type, 0, 2)).'_M'.$moduleNumber.'_'.strtoupper($difficultyLevel).'_'.strtoupper(Str::random(6));
        $order ??= ($moduleNumber === 1) ? 1 : (($difficultyLevel === Module::DIFFICULTY_EASY) ? 2 : 3);

        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => $moduleNumber,
            'difficulty_level' => $difficultyLevel,
            'duration_minutes' => $duration,
            'total_questions' => $totalQuestions,
            'key' => $uniqueKey,
            'order' => $order,
            'created_by' => $userId,
            'is_public' => false,
        ]);

        $module->sections()->syncWithoutDetaching([$section->id]);

        return $module;
    }

    /**
     * Clone a Test (Hierarchy only).
     */
    public function cloneTest(int $id, ?int $userId = null): Test
    {
        return $this->copies->copyTest(Test::findOrFail($id), $userId);
    }

    /**
     * Clone a Module (Hierarchy only).
     */
    public function cloneModule(int $id, ?int $sectionId = null, ?int $userId = null): Module
    {
        if ($sectionId) {
            $this->contentLock->ensureUnlocked(Section::findOrFail($sectionId)->test);
        }

        return $this->copies->copyModule(Module::findOrFail($id), $sectionId ? Section::findOrFail($sectionId) : null, $userId);
    }

    /**
     * Delete Test (with optional cascading deletion of children).
     */
    public function deleteTest(int $id, bool $deleteChildren, bool $forceDeleteAttempts = false): void
    {
        $test = Test::with('sections.modules.questions')->findOrFail($id);

        if ($forceDeleteAttempts) {
            $this->authorizeForceDelete();
        } else {
            $this->contentLock->ensureUnlocked($test);
            if (DB::table('user_tests')->where('test_id', $test->id)->exists()) {
                throw ValidationException::withMessages(['test' => 'Cannot delete test with existing student attempts.']);
            }
        }

        DB::transaction(function () use ($test, $deleteChildren, $forceDeleteAttempts) {
            if ($forceDeleteAttempts) {
                $this->purgeTestDependencies($test);
            }

            if ($deleteChildren) {
                $this->cascadeDeleteTestChildren($test, $forceDeleteAttempts);
            }

            $forceDeleteAttempts ? $test->forceDelete() : $test->delete();
        });
    }

    private function authorizeForceDelete(): void
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Only administrators can force-delete tests.');
        }
    }

    /**
     * Explicitly strip rows that block a hard delete but aren't covered by cascading FKs.
     */
    private function purgeTestDependencies(Test $test): void
    {
        $sectionIds = DB::table('sections')->where('test_id', $test->id)->pluck('id');
        $moduleIds = DB::table('modules')->whereIn('section_id', $sectionIds)->pluck('id');

        $questionIds = collect();
        if ($moduleIds->isNotEmpty()) {
            $questionIds = DB::table('module_questions')->whereIn('module_id', $moduleIds)->pluck('question_id');
        }

        $exclusiveQuestionIds = $questionIds->filter(function ($questionId) use ($moduleIds) {
            return ! DB::table('module_questions')
                ->where('question_id', $questionId)
                ->whereNotIn('module_id', $moduleIds)
                ->exists();
        })->values();

        $userTestIds = DB::table('user_tests')->where('test_id', $test->id)->pluck('id');

        if ($exclusiveQuestionIds->isNotEmpty()) {
            DB::table('user_test_answers')->whereIn('question_id', $exclusiveQuestionIds)->delete();
        }

        if ($userTestIds->isNotEmpty()) {
            DB::table('user_test_answers')->whereIn('user_test_id', $userTestIds)->delete();
            DB::table('user_test_module_submissions')->whereIn('user_test_id', $userTestIds)->delete();
            DB::table('user_test_score_revisions')->whereIn('user_test_id', $userTestIds)->delete();
        }

        if ($moduleIds->isNotEmpty()) {
            DB::table('user_test_module_submissions')->whereIn('module_id', $moduleIds)->delete();
            DB::table('user_test_module_submissions')->whereIn('issued_next_module_id', $moduleIds)->delete();
        }

        if ($userTestIds->isNotEmpty()) {
            DB::table('user_tests')->whereIn('id', $userTestIds)->delete();
        }

        $assignmentIds = DB::table('assignments')->where('test_id', $test->id)->pluck('id');
        if ($assignmentIds->isNotEmpty()) {
            DB::table('assignment_recipients')->whereIn('assignment_id', $assignmentIds)->delete();
            DB::table('assignments')->where('test_id', $test->id)->delete();
        }
    }

    private function cascadeDeleteTestChildren(Test $test, bool $force): void
    {
        $modules = $test->sections->flatMap(fn (Section $section) => $section->modules)->values();
        $this->deleteQuestionsOwnedByModules($modules, $force);

        foreach ($test->sections as $section) {
            foreach ($section->modules as $module) {
                $force ? $module->forceDelete() : $module->delete();
            }
            $force ? $section->forceDelete() : $section->delete();
        }
    }

    /**
     * Delete question rows only when the modules being deleted are their sole
     * owners. Test/module clones intentionally share question-bank rows.
     */
    private function deleteQuestionsOwnedByModules(iterable $modules, bool $force): void
    {
        $modules = collect($modules);
        $moduleIds = $modules->pluck('id')->filter()->values();

        if ($moduleIds->isEmpty()) {
            return;
        }

        $questionIds = DB::table('module_questions')
            ->whereIn('module_id', $moduleIds)
            ->distinct()
            ->pluck('question_id');

        foreach ($questionIds as $questionId) {
            $hasOtherModuleReference = DB::table('module_questions')
                ->where('question_id', $questionId)
                ->whereNotIn('module_id', $moduleIds)
                ->exists();

            if ($hasOtherModuleReference) {
                continue;
            }

            $question = Question::withTrashed()->find($questionId);
            if (! $question) {
                continue;
            }

            // Historical answers retain a restricted FK to the question. Keep
            // the row rather than breaking score history during a force purge.
            if ($force && DB::table('user_test_answers')->where('question_id', $questionId)->exists()) {
                continue;
            }

            $force ? $question->forceDelete() : $question->delete();
        }
    }

    /**
     * Delete Section (with optional cascading deletion of children).
     */
    public function deleteSection(int $id, bool $deleteChildren): void
    {
        $section = Section::with(['test', 'modules.questions'])->findOrFail($id);
        $test = $section->test;
        $this->contentLock->ensureUnlocked($test);

        if ($test && DB::table('user_tests')->where('test_id', $test->id)->exists()) {
            throw ValidationException::withMessages(['section' => 'Cannot delete section of a test with existing student attempts.']);
        }

        DB::transaction(function () use ($section, $deleteChildren) {
            if ($deleteChildren) {
                $this->deleteQuestionsOwnedByModules($section->modules, false);
                foreach ($section->modules as $module) {
                    $module->delete();
                }
            }
            $section->delete();
        });

        if ($test) {
            $test->refreshTotalDuration();
        }
    }

    /**
     * Delete Module (with optional cascading deletion of children).
     */
    public function deleteModule(int $id, bool $deleteChildren): void
    {
        $module = Module::with(['section.test', 'questions'])->findOrFail($id);
        $test = $module->section->test ?? null;
        $this->contentLock->ensureModuleUnlocked($module);

        if ($test && DB::table('user_tests')->where('test_id', $test->id)->exists()) {
            throw ValidationException::withMessages(['module' => 'Cannot delete module of a test with existing student attempts.']);
        }

        DB::transaction(function () use ($module, $deleteChildren) {
            if ($deleteChildren) {
                $this->deleteQuestionsOwnedByModules([$module], false);
            }
            $module->delete();
        });

        if ($test) {
            $test->refreshTotalDuration();
        }
    }
}
