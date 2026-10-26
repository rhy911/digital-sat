<?php

namespace App\Http\Controllers\Engine;

use App\Data\PreviewQuestions;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\AttemptEngine;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SessionController extends Controller
{
    public function __construct(
        private AttemptEngine $engine,
    ) {}

    public function show(?string $ulid = null): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        if ($ulid === null || $ulid === 'preview-rw') {
            return $this->showStaticPreview('reading_writing');
        }
        if ($ulid === 'preview-math') {
            return $this->showStaticPreview('math');
        }

        $attemptUlid = request()->query('attempt');
        if (!$attemptUlid) {
            throw new ConflictHttpException('An active test attempt is required.');
        }

        $user = Auth::user();
        $requestedAttempt = UserTest::where('ulid', $attemptUlid)->firstOrFail();
        abort_unless((int) $requestedAttempt->user_id === (int) Auth::id(), 403, 'Unauthorized.');

        // Second catch-up point, for a student who returns by URL (bookmark, back
        // into a stale tab) instead of through the assignment page. Reopening a
        // module whose deadline already passed would restart nothing — the clock is
        // server-side — but it would show them a live-looking test, so close the
        // attempt out first and send them to their score instead.
        if ($requestedAttempt->runsOnChainedClock() && $requestedAttempt->status === 'in_progress') {
            if ($this->engine->cascadeExpire($requestedAttempt) > 0) {
                $requestedAttempt->refresh();

                if ($requestedAttempt->status === 'completed') {
                    return redirect()->route('student.scores.show', $requestedAttempt)
                        ->with('success', 'Your time ran out, so this attempt was submitted automatically.');
                }
            }
        }

        abort_unless($requestedAttempt->status === 'in_progress', 409, 'This attempt is no longer active.');

        $hasIssuedContentEntitlement = $requestedAttempt->assignment_id || $requestedAttempt->exam_session_id;
        $moduleQuery = Module::query();
        if (! $hasIssuedContentEntitlement) {
            $moduleQuery->visibleTo($user);
        } else {
            $moduleQuery->whereHas('sections', fn ($query) => $query->where('test_id', $requestedAttempt->test_id));
        }
        $module = $moduleQuery->where('ulid', $ulid)->firstOrFail();

        [$section, $test] = $this->resolveModuleContext($module, $user, $requestedAttempt->test_id);
        if ($requestedAttempt->current_module_id && (int) $requestedAttempt->current_module_id !== (int) $module->id) {
            $currentModule = Module::find($requestedAttempt->current_module_id);
            if ($currentModule && $this->engine->pendingBreak($requestedAttempt, $currentModule)) {
                return redirect()->route('engine.session', [
                    'ulid' => $currentModule->ulid,
                    'attempt' => $requestedAttempt->ulid,
                ]);
            }
        }
        if ($hasIssuedContentEntitlement && ! $requestedAttempt->current_module_id) {
            abort_unless(
                (int) $this->engine->firstModule($test, $requestedAttempt)?->id === (int) $module->id,
                403,
                'This module has not been issued for the attempt.'
            );
        }
        $this->engine->assertIssued($requestedAttempt, $module);

        $pendingBreak = $this->engine->pendingBreak($requestedAttempt, $module);
        if ($pendingBreak !== null) {
            $breakData = (object) [
                'id' => $test->id,
                'page_title' => 'Break - Digital SAT',
                'section_title' => 'Scheduled Break',
                'section_number' => $section->order,
                'module_number' => $module->module_number,
                'module_id' => $module->id,
                'username' => ($user = Auth::user()) ? ($user->name ?? $user->username ?? 'Student') : 'Student',
                'is_preview' => false,
                'duration_minutes' => (int) ceil($pendingBreak['duration_seconds'] / 60),
            ];

            return view('engine.break', [
                'testData' => $breakData,
                'sectionNumber' => $section->order,
                'moduleNumber' => $module->module_number,
                'sectionName' => $section->name,
                'sectionType' => $section->type,
                'module' => $module,
                'userTest' => $requestedAttempt,
                'breakDurationSeconds' => $pendingBreak['duration_seconds'],
                'remainingBreakSeconds' => $pendingBreak['remaining_seconds'],
                'nextModuleUrl' => route('engine.session', [
                    'ulid' => $module->ulid,
                    'attempt' => $requestedAttempt->ulid,
                ]),
            ]);
        }

        $this->engine->assertIssued($requestedAttempt, $module);
        $this->loadCurrentModuleQuestions($module);

        // Get questions ordered by position (defined in Module::questions relationship)
        $questions = $module->questions;
        if ($questions->isEmpty()) {
            abort(404, 'Module has no questions.');
        }

        // Security: Hide 'is_correct' attribute to prevent leaking answers to students.
        $questions->each(function($question) {
            $question->answerChoices->makeHidden('is_correct');
        });

        $currentQuestion = 1;
        $totalQuestions = $questions->count();

        $isPreview = $test->isPreview();
        $durationMinutes = $isPreview ? 0 : (int) ($this->engine->durationSeconds($module) / 60);

        // Determine which view to use based on section type
        $viewName = $section->type === 'math' ? 'engine.module.math' : 'engine.module.reading';

        // Get user test record
        $userTest = null;
        $savedAnswers = collect();
        $runsOnChainedClock = false;
        $serverRemainingSeconds = null;
        if (Auth::check()) {
            $userTest = $requestedAttempt;
            if ((int) $userTest->test_id !== (int) $test->id) {
                abort(400, 'Attempt does not belong to this test.');
            }

            $timing = $this->engine->startOrResumeModule($userTest, $module, $isPreview);
            $runsOnChainedClock = $userTest->runsOnChainedClock();

            if (! $isPreview) {
                $runsOnChainedClock
                    ? ($serverRemainingSeconds = $timing['remaining_seconds'])
                    : ($durationMinutes = $timing['remaining_seconds'] / 60);
            }

            $savedAnswersData = UserTestAnswer::where('user_test_id', $userTest->id)
                ->where('module_id', $module->id)
                ->whereIn('question_id', $questions->pluck('id'))
                ->get(['question_id', 'selected_answer', 'time_spent']);

            $savedAnswers = $savedAnswersData->pluck('selected_answer', 'question_id');
            $savedQuestionTimes = $savedAnswersData->pluck('time_spent', 'question_id');
        } else {
            $savedQuestionTimes = collect();
        }

        $testData = (object) [
            'id' => $test->id,
            'page_title' => "Section {$section->order}, Module {$module->module_number}: {$section->name}",
            'section_title' => "{$section->name} - Module {$module->module_number}",
            'section_number' => $section->order,
            'module_number' => $module->module_number,
            'module_id' => $module->id,
            'username' => ($user = Auth::user()) ? ($user->name ?? $user->username ?? 'Guest') : 'Guest',
            'is_preview' => $isPreview,
            'duration_minutes' => $durationMinutes,
        ];

        $isFinalModule = $this->engine->isTerminalSubmission($requestedAttempt, $module);

        return view($viewName, [
            'testData' => $testData,
            'questions' => $questions,
            'currentQuestion' => $currentQuestion,
            'totalQuestions' => $totalQuestions,
            'sectionNumber' => $section->order,
            'moduleNumber' => $module->module_number,
            'sectionName' => $section->name,
            'sectionType' => $section->type,
            'nextModuleId' => null,
            'nextModuleName' => null,
            'userTestId' => $userTest ? $userTest->id : null,
            'userTestUlid' => $userTest ? $userTest->ulid : null,
            'userTest' => $userTest,
            'savedAnswers' => $savedAnswers,
            'savedQuestionTimes' => $savedQuestionTimes,
            'isAssignmentAttempt' => $runsOnChainedClock,
            'serverRemainingSeconds' => $serverRemainingSeconds,
            'isFinalModule' => $isFinalModule,
        ]);
    }

    /** @return array{0: Section, 1: Test} */
    private function resolveModuleContext(Module $module, User $user, ?int $attemptTestId = null): array
    {
        $activeVisibleTest = fn($query) => $attemptTestId
            ? $query->whereKey($attemptTestId)
            : $query->visibleTo($user)->whereIn('status', ['active', 'draft']);

        $sectionQuery = $module->sections()
            ->with('test')
            ->whereHas('test', $activeVisibleTest);

        if ($module->section_id) {
            $sectionQuery->orderByRaw('CASE WHEN sections.id = ? THEN 0 ELSE 1 END', [$module->section_id]);
        }

        $section = $sectionQuery
            ->orderBy('sections.order')
            ->first();

        if (! $section && $module->section_id) {
            $section = Section::with('test')
                ->whereKey($module->section_id)
                ->whereHas('test', $activeVisibleTest)
                ->first();
        }

        if (! $section || ! $section->test) {
            abort(404, 'Module is not attached to an available test.');
        }

        return [$section, $section->test];
    }

    private function loadCurrentModuleQuestions(Module $module): void
    {
        $module->load([
            'questions.passage',
            'questions.answerChoices' => fn($q) => $q->orderBy('order'),
        ]);
    }

    public function teacherPreview(\Illuminate\Http\Request $request, string $testUlid): \Illuminate\Contracts\View\View
    {
        $user = Auth::user();
        abort_unless($user && in_array($user->role, ['admin', 'teacher'], true), 403, 'Unauthorized.');

        $test = Test::where('ulid', $testUlid)->firstOrFail();

        $testExists = Test::visibleTo($user)->where('id', $test->id)->exists();
        abort_unless($testExists, 403, 'Unauthorized test access.');

        $moduleUlid = $request->query('module');
        if ($moduleUlid) {
            $module = Module::where('ulid', $moduleUlid)
                ->whereHas('sections', fn($q) => $q->where('test_id', $test->id))
                ->firstOrFail();
        } else {
            $firstSection = $test->sections()->orderBy('order')->first();
            if (!$firstSection) {
                abort(404, 'This test has no sections.');
            }
            $module = $firstSection->modules()->orderBy('module_number')->orderBy('id')->first();
            if (!$module) {
                abort(404, 'This test has no modules.');
            }
        }

        $section = $module->sections()->where('test_id', $test->id)->first();
        if (!$section) {
            abort(404, 'Module section not found.');
        }

        $this->loadCurrentModuleQuestions($module);
        $questions = $module->questions;

        $questions->each(function($question) {
            $question->answerChoices->makeHidden('is_correct');
        });

        $testData = (object) [
            'id' => $test->id,
            'page_title' => "Teacher Preview - Section {$section->order}, Module {$module->module_number}: {$section->name}",
            'section_title' => "{$section->name} - Module {$module->module_number}",
            'section_number' => $section->order,
            'module_number' => $module->module_number,
            'module_id' => $module->id,
            'username' => $user->name ?? $user->username ?? 'Teacher',
            'is_preview' => true,
            'duration_minutes' => 0,
            'is_teacher_preview' => true,
            'test_ulid' => $test->ulid,
        ];

        $testModules = [];
        $test->loadMissing('sections.modules');
        foreach ($test->sections as $sec) {
            foreach ($sec->modules as $mod) {
                $testModules[] = [
                    'ulid' => $mod->ulid,
                    'name' => "Section {$sec->order}: {$sec->name} - Module {$mod->module_number} ({$mod->difficulty_level})",
                    'is_current' => $mod->id === $module->id,
                ];
            }
        }

        $viewName = $section->type === 'math' ? 'engine.module.math' : 'engine.module.reading';

        return view($viewName, [
            'testData' => $testData,
            'questions' => $questions,
            'currentQuestion' => 1,
            'totalQuestions' => $questions->count(),
            'sectionNumber' => $section->order,
            'moduleNumber' => $module->module_number,
            'sectionName' => $section->name,
            'sectionType' => $section->type,
            'nextModuleId' => null,
            'nextModuleName' => null,
            'userTestId' => null,
            'userTestUlid' => null,
            'userTest' => null,
            'savedAnswers' => collect(),
            'savedQuestionTimes' => collect(),
            'isAssignmentAttempt' => false,
            'serverRemainingSeconds' => null,
            'isTeacherPreview' => true,
            'testModules' => $testModules,
        ]);
    }

    private function showStaticPreview(string $type): \Illuminate\Contracts\View\View
    {
        $testData = (object) [
            'id' => 99999,
            'page_title' => $type === 'math' ? 'Section 2, Module 1: Math' : 'Section 1, Module 1: Reading and Writing',
            'section_title' => $type === 'math' ? 'Math - Module 1' : 'Reading and Writing - Module 1',
            'section_number' => $type === 'math' ? 2 : 1,
            'module_number' => 1,
            'module_id' => $type === 'math' ? 99992 : 99991,
            'username' => ($user = Auth::user()) ? ($user->name ?? $user->username ?? 'Guest') : 'Guest',
            'is_preview' => true,
            'duration_minutes' => 0,
        ];

        $questions = $this->getStaticPreviewQuestions($type);
        $viewName = $type === 'math' ? 'engine.module.math' : 'engine.module.reading';

        return view($viewName, [
            'testData' => $testData,
            'questions' => $questions,
            'currentQuestion' => 1,
            'totalQuestions' => $questions->count(),
            'sectionNumber' => $type === 'math' ? 2 : 1,
            'moduleNumber' => 1,
            'sectionName' => $type === 'math' ? 'Math' : 'Reading and Writing',
            'sectionType' => $type === 'math' ? 'math' : 'reading_writing',
            'nextModuleId' => $type === 'math' ? null : 'preview-math',
            'nextModuleName' => $type === 'math' ? null : 'Math Module 1',
            'userTestId' => null,
            'userTestUlid' => null,
            'userTest' => null,
            'savedAnswers' => collect([]),
            'savedQuestionTimes' => collect([]),
            'isAssignmentAttempt' => false,
            'serverRemainingSeconds' => null,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, \stdClass> */
    private function getStaticPreviewQuestions(string $type): \Illuminate\Support\Collection
    {
        return $type === 'reading_writing'
            ? PreviewQuestions::readingWriting()
            : PreviewQuestions::math();
    }
}
