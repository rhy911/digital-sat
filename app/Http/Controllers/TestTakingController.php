<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Question;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\SatScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    public function submitModule(Request $request)
    {
        $request->validate([
            'user_test_id' => 'required|exists:user_tests,id',
            'module_id' => 'required|exists:modules,id',
            'answers' => 'required|array', // [question_id => selected_answer]
        ]);

        $userTest = UserTest::findOrFail($request->user_test_id);
        $module = Module::with('section')->findOrFail($request->module_id);
        $section = $module->section;

        // 1. Save answers
        foreach ($request->answers as $questionId => $answer) {
            $question = Question::findOrFail($questionId);
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
                ->with('question')
                ->get();

            $thetaM1 = $this->scoringService->estimateTheta($m1Responses);
            $path = $this->scoringService->routeModule2($thetaM1);

            // Save routing decision
            if ($section->type === 'reading_writing') {
                $userTest->rw_m2_path = $path;
            } else {
                $userTest->math_m2_path = $path;
            }
            $userTest->save();

            // Find next module
            $nextModule = $section->modules()
                ->where('module_number', 2)
                ->where('difficulty_level', $path)
                ->first() 
                ?? $section->modules()->where('module_number', 2)->first();

            if (!$nextModule) {
                return response()->json([
                    'error' => 'Next module not found for this section.',
                ], 404);
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

            if ($nextSection) {
                $nextModule = $nextSection->modules()->where('module_number', 1)->first();
                return response()->json([
                    'next_module_id' => $nextModule->id,
                    'message' => 'Section completed. Moving to next section.',
                ]);
            } else {
                // End of Test: Calculate Final Scores
                $this->finalizeTest($userTest);
                return response()->json([
                    'test_completed' => true,
                    'redirect_url' => route('home'), // Or a results page
                    'message' => 'Test completed and scored.',
                ]);
            }
        }
    }

    private function checkAnswer(Question $question, $userAnswer)
    {
        if ($question->question_type === 'multiple_choice') {
            $correctChoice = $question->answerChoices()->where('is_correct', true)->first();
            return $correctChoice && $correctChoice->label === $userAnswer;
        } else {
            // SPR
            $correctAnswers = $question->sprCorrectAnswers->pluck('answer')->toArray();
            return in_array($userAnswer, $correctAnswers);
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
        $m2 = $section->modules->where('module_number', 2)->where('difficulty_level', $m2Path)->first();

        $m1Responses = UserTestAnswer::where('user_test_id', $userTest->id)
            ->whereIn('question_id', $m1->questions->pluck('id'))
            ->with('question')
            ->get();
            
        $m2Responses = UserTestAnswer::where('user_test_id', $userTest->id)
            ->whereIn('question_id', $m2->questions->pluck('id'))
            ->with('question')
            ->get();

        return $this->scoringService->scoreSection($m1Responses, $m2Responses);
    }
}
