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
        $test = Test::findOrFail($testId);

        $userTest = UserTest::firstOrCreate([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        return response()->json([
            'user_test_id' => $userTest->id,
            'message' => 'Test started',
        ]);
    }

    /**
     * Submit answers for a module and get next routing
     */
    public function submitModule(SubmitModuleRequest $request)
    {
        try {
            \Illuminate\Support\Facades\Log::info("Raw submitModule input: " . json_encode($request->all()));

            $validated = $request->validated();

            return DB::transaction(function () use ($validated) {
                $userTest = UserTest::findOrFail($validated['user_test_id']);
                $module = Module::with(['section', 'questions'])->findOrFail($validated['module_id']);
                $section = $module->section;

                // 1. Save answers
                $submittedAnswers = $validated['answers'];
                foreach ($submittedAnswers as $questionId => $answer) {
                    $question = Question::find($questionId);
                    if (!$question) continue;
                    
                    $isCorrect = $this->checkAnswer($question, $answer);

                    UserTestAnswer::updateOrCreate(
                        ['user_test_id' => $userTest->id, 'question_id' => $questionId],
                        ['selected_answer' => $answer, 'is_correct' => $isCorrect]
                    );
                }

                // 2. Logic for Routing or Finalizing
                if ($module->module_number == 1) {
                    // End of Module 1: Calculate Theta for routing
                    $m1Responses = UserTestAnswer::where('user_test_id', $userTest->id)
                        ->whereIn('question_id', $module->questions->pluck('id'))
                        ->with(['question' => function($q) {
                            $q->select('id', 'irt_a', 'irt_b', 'irt_c', 'is_pretest');
                        }])
                        ->get();

                    $thetaM1 = $this->scoringService->estimateTheta($m1Responses);
                    $path = $this->scoringService->routeModule2($thetaM1);

                    \Illuminate\Support\Facades\Log::info("Module 1 completed. UserTest: {$userTest->id}, Module: {$module->id}, Section: {$section->id}, Theta: {$thetaM1}, Path: {$path}");

                    // Save routing decision
                    if ($section->type === Section::TYPE_RW) {
                        $userTest->rw_m2_path = $path;
                    } else {
                        $userTest->math_m2_path = $path;
                    }
                    $userTest->save();

                    // Find next module
                    $nextModule = $section->modules()
                        ->where('module_number', 2)
                        ->where('difficulty_level', $path)
                        ->withCount('questions')
                        ->first();
                    
                    if (!$nextModule || $nextModule->questions_count === 0) {
                        \Illuminate\Support\Facades\Log::warning("Routed module (number 2, level {$path}) is missing or empty in section {$section->id}. Searching for fallback.");
                        
                        $fallbackModule = $section->modules()
                            ->where('module_number', 2)
                            ->where('difficulty_level', '!=', $path)
                            ->withCount('questions')
                            ->first();

                        if (!$fallbackModule || $fallbackModule->questions_count === 0) {
                            \Illuminate\Support\Facades\Log::error("NO FUNCTIONAL MODULE 2 FOUND for section {$section->id}.");
                            return response()->json([
                                'error' => 'No functional modules found for this section.',
                                'details' => "Section ID: {$section->id}, Path: {$path}"
                            ], 404);
                        }

                        // Update the saved path to the fallback one so scoring works correctly later
                        if ($section->type === Section::TYPE_RW) {
                            $userTest->rw_m2_path = $fallbackModule->difficulty_level;
                        } else {
                            $userTest->math_m2_path = $fallbackModule->difficulty_level;
                        }
                        $userTest->save();

                        return response()->json([
                            'next_module_id' => $fallbackModule->id,
                            'fallback_module_id' => $fallbackModule->id,
                            'path' => $path,
                            'message' => "Routed module unavailable. Falling back.",
                        ]);
                    }

                    return response()->json([
                        'next_module_id' => $nextModule->id,
                        'path' => $path,
                        'message' => "Module 1 submitted. Routed to {$path} Module 2.",
                    ]);
                } else {
                    // End of Module 2
                    $nextSection = Test::find($userTest->test_id)->sections()
                        ->where('order', '>', $section->order)
                        ->orderBy('order')
                        ->first();

                    \Illuminate\Support\Facades\Log::info("Module 2 completed. UserTest: {$userTest->id}, Module: {$module->id}, Next Section Found: " . ($nextSection ? $nextSection->id : 'None'));

                    if ($nextSection) {
                        $nextModule = $nextSection->modules()->where('module_number', 1)->first();
                        
                        if (!$nextModule) {
                            \Illuminate\Support\Facades\Log::error("NO MODULE 1 FOUND for next section {$nextSection->id}.");
                            return response()->json([
                                'error' => 'Module 1 not found for next section.',
                                'details' => "Section ID: {$nextSection->id}"
                            ], 404);
                        }

                        return response()->json([
                            'next_module_id' => $nextModule->id,
                            'message' => 'Section completed. Moving to next section.',
                        ]);
                    } else {
                        // End of Test: Calculate Final Scores
                        $this->finalizeTest($userTest);
                        return response()->json([
                            'test_completed' => true,
                            'redirect_url' => route('home'),
                            'message' => 'Test completed and scored.',
                        ]);
                    }
                }

                // Fallback for logical gaps
                return response()->json([
                    'error' => 'Incomplete test structure or routing failure.',
                    'message' => 'Could not determine next step.'
                ], 422);
            });

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EXCEPTION in submitModule: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'error' => 'Server error during submission.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function checkAnswer(Question $question, $userAnswer)
    {
        if ($question->question_type === 'multiple_choice') {
            $correctChoice = $question->answerChoices()->where('is_correct', true)->first();
            return $correctChoice && trim($correctChoice->label) === trim($userAnswer);
        } else {
            // SPR
            $correctAnswers = $question->sprCorrectAnswers->pluck('answer')->map(fn($a) => trim($a))->toArray();
            return in_array(trim($userAnswer), $correctAnswers);
        }
    }

    private function finalizeTest(UserTest $userTest)
    {
        $test = Test::with('sections.modules.questions')->find($userTest->test_id);
        
        $rwSection = $test->sections->where('type', 'reading_writing')->first();
        $mathSection = $test->sections->where('type', 'math')->first();

        // Calculate R&W
        $rwScore = $this->calculateSectionScore($userTest, $rwSection, $userTest->rw_m2_path);
        
        // Calculate Math
        $mathScore = $this->calculateSectionScore($userTest, $mathSection, $userTest->math_m2_path);

        $userTest->update([
            'score_reading_writing' => $rwScore['scaled_score'],
            'score_math' => $mathScore['scaled_score'],
            'rw_theta' => $rwScore['theta'],
            'math_theta' => $mathScore['theta'],
            'total_score' => $rwScore['scaled_score'] + $mathScore['scaled_score'],
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    private function calculateSectionScore(UserTest $userTest, $section, $m2Path)
    {
        if (!$section) return ['scaled_score' => 200, 'theta' => -3.5];

        $m1 = $section->modules->where('module_number', 1)->first();
        // Specific difficulty only, no silent fallback
        $m2 = $section->modules->where('module_number', 2)->where('difficulty_level', $m2Path)->first();

        if (!$m1 || !$m2) {
            \Illuminate\Support\Facades\Log::warning("Missing module for final scoring in Section {$section->id}. M1: " . ($m1 ? 'found' : 'missing') . ", M2 ({$m2Path}): " . ($m2 ? 'found' : 'missing'));
            return ['scaled_score' => 200, 'theta' => -3.5];
        }

        $m1Responses = UserTestAnswer::where('user_test_id', $userTest->id)
            ->whereIn('question_id', $m1->questions->pluck('id'))
            ->with(['question' => function($q) {
                $q->select('id', 'irt_a', 'irt_b', 'irt_c', 'is_pretest');
            }])
            ->get();
            
        $m2Responses = UserTestAnswer::where('user_test_id', $userTest->id)
            ->whereIn('question_id', $m2->questions->pluck('id'))
            ->with(['question' => function($q) {
                $q->select('id', 'irt_a', 'irt_b', 'irt_c', 'is_pretest');
            }])
            ->get();

        return $this->scoringService->scoreSection($m1Responses, $m2Responses);
    }
}
