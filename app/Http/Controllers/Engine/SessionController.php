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
use App\Services\AssignmentAttemptTimeoutService;
use App\Services\AssignmentModuleTimingService;
use App\Services\AttemptProgressionService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SessionController extends Controller
{
    public function __construct(
        private AssignmentModuleTimingService $assignmentTiming,
        private AttemptProgressionService $progression,
        private AssignmentAttemptTimeoutService $timeouts,
    ) {}

    public function show($ulid = null)
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
        if ($requestedAttempt->assignment_id && $requestedAttempt->status === 'in_progress') {
            if ($this->timeouts->finalizeExpired($requestedAttempt) > 0) {
                $requestedAttempt->refresh();

                if ($requestedAttempt->status === 'completed') {
                    return redirect()->route('student.scores.show', $requestedAttempt)
                        ->with('success', 'Your time ran out, so this attempt was submitted automatically.');
                }
            }
        }

        abort_unless($requestedAttempt->status === 'in_progress', 409, 'This attempt is no longer active.');

        $moduleQuery = Module::query();
        if (!$requestedAttempt->assignment_id) {
            $moduleQuery->visibleTo($user);
        } else {
            $moduleQuery->whereHas('sections', fn ($query) => $query->where('test_id', $requestedAttempt->test_id));
        }
        $module = $moduleQuery->where('ulid', $ulid)->firstOrFail();

        [$section, $test] = $this->resolveModuleContext($module, $user, $requestedAttempt->test_id);
        $this->progression->assertIssued($requestedAttempt, $module);
        $this->loadCurrentModuleQuestions($module);

        if (! $module) {
            abort(404, 'No module found. Please add modules first.');
        }

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

        $isPreview = ($test->title === 'Test Preview');
        $durationMinutes = $isPreview
            ? 0
            : ($module->duration_minutes ?? ($section->type === 'math' ? Module::MATH_DURATION : Module::RW_DURATION));

        // Determine which view to use based on section type
        $viewName = $section->type === 'math' ? 'engine.module.math' : 'engine.module.reading';

        // Get user test record
        $userTest = null;
        $savedAnswers = collect();
        $isAssignmentAttempt = false;
        $serverRemainingSeconds = null;
        if (Auth::check()) {
            $userTest = $requestedAttempt;
            if ((int) $userTest->test_id !== (int) $test->id) {
                abort(400, 'Attempt does not belong to this test.');
            }

            if (!$userTest->current_module_started_at) {
                $userTest->current_module_started_at = now();
                $userTest->current_module_elapsed_seconds = 0;
                $userTest->save();
            } else if ($userTest->current_module_started_at && !$isPreview) {
                if (!$userTest->assignment_id) {
                    // Practice resumption pauses while away and resumes from saved elapsed time.
                    $userTest->current_module_started_at = now();
                    $userTest->save();

                    $elapsedSeconds = $userTest->current_module_elapsed_seconds;
                    $totalSeconds = $durationMinutes * 60;
                    $remainingSeconds = max(0, $totalSeconds - $elapsedSeconds);
                    $durationMinutes = $remainingSeconds / 60;
                }
            }

            $isAssignmentAttempt = (bool) $userTest->assignment_id;
            if ($isAssignmentAttempt && !$isPreview) {
                $timing = $this->assignmentTiming->syncElapsed($userTest, $module);
                $serverRemainingSeconds = $timing['remaining_seconds'];
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
            'username' => Auth::user()?->name ?? Auth::user()?->username ?? 'Guest',
            'is_preview' => $isPreview,
            'duration_minutes' => $durationMinutes,
        ];

        $isFinalModule = false;
        try {
            $isFinalModule = $this->progression->isTerminalSubmission($requestedAttempt, $module);
        } catch (\Throwable $e) {
            $isFinalModule = false;
        }

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
            'isAssignmentAttempt' => $isAssignmentAttempt,
            'serverRemainingSeconds' => $serverRemainingSeconds,
            'isFinalModule' => $isFinalModule,
        ]);
    }

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

    public function teacherPreview(\Illuminate\Http\Request $request, string $testUlid)
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

    private function showStaticPreview($type)
    {
        $testData = (object) [
            'id' => 99999,
            'page_title' => $type === 'math' ? 'Section 2, Module 1: Math' : 'Section 1, Module 1: Reading and Writing',
            'section_title' => $type === 'math' ? 'Math - Module 1' : 'Reading and Writing - Module 1',
            'section_number' => $type === 'math' ? 2 : 1,
            'module_number' => 1,
            'module_id' => $type === 'math' ? 99992 : 99991,
            'username' => Auth::user()?->name ?? Auth::user()?->username ?? 'Guest',
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
            'savedAnswers' => collect(),
            'savedQuestionTimes' => collect(),
            'isAssignmentAttempt' => false,
            'serverRemainingSeconds' => null,
        ]);
    }

    private function getStaticPreviewQuestions(string $type): \Illuminate\Support\Collection
    {
        return $type === 'reading_writing'
            ? PreviewQuestions::readingWriting()
            : PreviewQuestions::math();
    }
}
