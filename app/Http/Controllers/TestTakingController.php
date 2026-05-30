<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Question;
use App\Models\Test;
use App\Models\Section;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\SatScoringService;
use App\Http\Requests\SubmitModuleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TestTakingController extends Controller
{
    protected $scoringService;

    public function __construct(SatScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    /**
     * Initialize or resume a test
     */
    public function startTest(Request $request, $testId)
    {
        $user = Auth::user();
        $test = Test::where('id', $testId)->where('status', 'active')->firstOrFail();

        $userTest = UserTest::updateOrCreate(
            [
                'user_id' => $user->id,
                'test_id' => $test->id,
            ],
            [
                'status' => 'in_progress',
            ]
        );

        return response()->json([
            'user_test_id' => $userTest->id,
            'message' => 'Test started',
        ]);
    }

    public function showModule($module_id = null)
    {
        $module = null;
        if ($module_id) {
            $module = \App\Models\Module::visibleTo(auth()->user())
                ->whereHas('section.test', function($q) {
                    $q->whereIn('status', ['active', 'draft']);
                })
                ->with([
                'section.test.sections.modules.questions.passage',
                'section.test.sections.modules.questions.answerChoices' => fn($q) => $q->orderBy('order'),
                'questions.passage',
                'questions.answerChoices' => fn($q) => $q->orderBy('order'),
            ])->findOrFail($module_id);

            if (!$module) {
                abort(404, 'Module not found.');
            }
            $test = $module->section->test;
            $section = $module->section;
        } else {
            $test = \App\Models\Test::with([
                'sections.modules.questions.passage',
                'sections.modules.questions.answerChoices' => fn($q) => $q->orderBy('order'),
            ])->whereIn('status', ['active', 'draft'])
              ->orderByRaw("CASE WHEN title = 'Test Preview' THEN 0 ELSE 1 END")
              ->first();

            if (! $test) {
                abort(404, 'No test available. Please create a test first.');
            }

            if ($test->sections->isEmpty()) {
                abort(404, 'Test has no sections. Please add sections and modules first.');
            }

            // Default to first module of first section
            $section = $test->sections->firstWhere('type', 'reading_writing') ?? $test->sections->first();
            $module = $section->modules->first() ?? null;
        }

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

        $testData = (object) [
            'id' => $test->id,
            'page_title' => "Section {$section->order}, Module {$module->module_number}: {$section->name}",
            'section_title' => "{$section->name} - Module {$module->module_number}",
            'section_number' => $section->order,
            'module_number' => $module->module_number,
            'module_id' => $module->id,
            'username' => \Illuminate\Support\Facades\Auth::user()?->username ?? 'Guest',
            'is_preview' => ($test->title === 'Test Preview'),
            'duration_minutes' => $module->duration_minutes ?? ($section->type === 'math' ? 35 : 32),
        ];

        // Determine next module for navigation (simple logic for now)
        $nextModule = null;
        if ($module->module_number == 1) {
            // Find Module 2 in same section (prefer hard for mock/preview if available)
            $nextModule = $section->modules->where('module_number', 2)->firstWhere('difficulty_level', 'hard');
        } else {
            // Move to next section's first module
            $nextSection = $test->sections->where('order', '>', $section->order)->sortBy('order')->first();
            if ($nextSection) {
                $nextModule = $nextSection->modules->where('module_number', 1)->first();
            }
        }

        // Determine which view to use based on section type
        $viewName = $section->type === 'math' ? 'tests.take.take-math' : 'tests.take.take-reading';

        // Get user test record
        $userTest = null;
        if (Auth::check()) {
            $userTest = \App\Models\UserTest::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'test_id' => $test->id,
                ],
                [
                    'status' => 'in_progress',
                ]
            );
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
            'nextModuleId' => $nextModule ? $nextModule->id : null,
            'nextModuleName' => $nextModule ? ($nextModule->module_number == 2 ? 'Module 2' : 'Section ' . ($nextModule->section?->order ?? '')) : null,
            'userTestId' => $userTest ? $userTest->id : null,
        ]);
    }

    /**
     * Submit answers for a module and get next routing
     */
    public function submitModule(SubmitModuleRequest $request)
    {
        try {
            \Illuminate\Support\Facades\Log::info("submitModule called", [
                'user_test_id' => $request->input('user_test_id'),
                'module_id' => $request->input('module_id')
            ]);

            $validated = $request->validated();

            return DB::transaction(function () use ($validated) {
                $userTest = UserTest::where('id', $validated['user_test_id'])
                    ->where('user_id', Auth::id())
                    ->where('status', 'in_progress')
                    ->firstOrFail();
                $module = Module::with(['section', 'questions'])->findOrFail($validated['module_id']);
                $section = $module->section;

                // 1. Save answers
                $submittedAnswers = $validated['answers'];
                $questionIds = array_keys($submittedAnswers);
                $questions = \App\Models\Question::with(['answerChoices', 'sprCorrectAnswers'])
                    ->whereIn('id', $questionIds)
                    ->get()
                    ->keyBy('id');

                foreach ($submittedAnswers as $questionId => $answer) {
                    $question = $questions->get($questionId);
                    if (!$question) continue;
                    
                    $isCorrect = $this->checkAnswer($question, $answer);

                    UserTestAnswer::updateOrCreate(
                        ['user_test_id' => $userTest->id, 'question_id' => $questionId],
                        ['selected_answer' => $answer, 'is_correct' => $isCorrect]
                    );
                }

                // 2. Logic for Routing or Finalizing - Moved to Background Job
                \App\Jobs\ScoreModuleJob::dispatch($userTest->id, $module->id, $section->id);

                return response()->json([
                    'status' => 'scoring',
                    'message' => 'Responses saved. Scoring in progress...',
                ]);
            });

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EXCEPTION in submitModule", ['exception' => $e]);
            return response()->json([
                'error' => 'Server error during submission.',
                'message' => 'An unexpected server error occurred.'
            ], 500);
        }
    }

    public function checkScoringStatus($userTestId)
    {
        $userTest = \App\Models\UserTest::where('user_id', Auth::id())->findOrFail($userTestId);
        $cacheKey = "scoring_result_{$userTest->id}";

        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            $result = \Illuminate\Support\Facades\Cache::pull($cacheKey);
            return response()->json($result);
        }

        return response()->json([
            'status' => 'scoring',
            'message' => 'Scoring in progress...',
        ]);
    }

    private function checkAnswer(Question $question, $userAnswer)
    {
        if ($question->question_type === 'multiple_choice') {
            $correctChoice = $question->answerChoices->where('is_correct', true)->first();
            return $correctChoice && trim($correctChoice->label) === trim($userAnswer);
        } else {
            // SPR
            $correctAnswers = $question->sprCorrectAnswers->pluck('answer')->map(fn($a) => trim($a))->toArray();
            return in_array(trim($userAnswer), $correctAnswers);
        }
    }
}
