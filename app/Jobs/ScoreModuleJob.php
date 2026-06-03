<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\SatScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScoreModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userTestId;
    public $moduleId;
    public $sectionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userTestId, int $moduleId, int $sectionId)
    {
        $this->userTestId = $userTestId;
        $this->moduleId = $moduleId;
        $this->sectionId = $sectionId;
    }

    /**
     * Execute the job.
     */
    public function handle(SatScoringService $scoringService): void
    {
        try {
            Log::info("ScoreModuleJob started", [
                'user_test_id' => $this->userTestId,
                'module_id' => $this->moduleId
            ]);

            $userTest = UserTest::findOrFail($this->userTestId);
            $module = Module::with(['section', 'questions'])->findOrFail($this->moduleId);
            $section = $module->section;

            // 2. Logic for Routing or Finalizing
            if ($module->module_number == 1) {
                // End of Module 1: Calculate Theta for routing
                $m1Responses = UserTestAnswer::where('user_test_id', $userTest->id)
                    ->whereIn('question_id', $module->questions->pluck('id'))
                    ->with(['question' => function($q) {
                        $q->select('id', 'irt_a', 'irt_b', 'irt_c', 'is_pretest');
                    }])
                    ->get();

                $thetaM1 = $scoringService->estimateTheta($m1Responses);
                $path = $scoringService->routeModule2($thetaM1);

                Log::info("Module 1 completed via Job. UserTest: {$userTest->id}, Module: {$module->id}, Section: {$section->id}, Theta: {$thetaM1}, Path: {$path}");

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
                    Log::warning("Routed module (number 2, level {$path}) is missing or empty in section {$section->id}. Searching for fallback.");
                    
                    $fallbackModule = $section->modules()
                        ->where('module_number', 2)
                        ->where('difficulty_level', '!=', $path)
                        ->withCount('questions')
                        ->first();

                    if (!$fallbackModule || $fallbackModule->questions_count === 0) {
                        Log::error("NO FUNCTIONAL MODULE 2 FOUND for section {$section->id}.");
                        $result = [
                            'status' => 'error',
                            'error' => 'No functional modules found for this section.',
                            'details' => "Section ID: {$section->id}, Path: {$path}"
                        ];
                        Cache::put("scoring_result_{$userTest->id}", $result, 300);
                        return;
                    }

                    // Update the saved path to the fallback one so scoring works correctly later
                    if ($section->type === Section::TYPE_RW) {
                        $userTest->rw_m2_path = $fallbackModule->difficulty_level;
                    } else {
                        $userTest->math_m2_path = $fallbackModule->difficulty_level;
                    }
                    $userTest->save();

                    $result = [
                        'status' => 'success',
                        'next_module_id' => $fallbackModule->ulid,
                        'fallback_module_id' => $fallbackModule->ulid,
                        'path' => $path,
                        'message' => "Routed module unavailable. Falling back.",
                    ];
                } else {
                    $result = [
                        'status' => 'success',
                        'next_module_id' => $nextModule->ulid,
                        'path' => $path,
                        'message' => "Module 1 submitted. Routed to {$path} Module 2.",
                    ];
                }

                Cache::put("scoring_result_{$userTest->id}", $result, 300);

            } else {
                // End of Module 2
                $nextSection = Test::find($userTest->test_id)->sections()
                    ->where('order', '>', $section->order)
                    ->orderBy('order')
                    ->first();

                Log::info("Module 2 completed via Job. UserTest: {$userTest->id}, Module: {$module->id}, Next Section Found: " . ($nextSection ? $nextSection->id : 'None'));

                if ($nextSection) {
                    $nextModule = $nextSection->modules()->where('module_number', 1)->first();
                    
                    if (!$nextModule) {
                        Log::error("NO MODULE 1 FOUND for next section {$nextSection->id}.");
                        $result = [
                            'status' => 'error',
                            'error' => 'Module 1 not found for next section.',
                            'details' => "Section ID: {$nextSection->id}"
                        ];
                        Cache::put("scoring_result_{$userTest->id}", $result, 300);
                        return;
                    }

                    $result = [
                        'status' => 'success',
                        'next_module_id' => $nextModule->ulid,
                        'message' => 'Section completed. Moving to next section.',
                    ];
                    Cache::put("scoring_result_{$userTest->id}", $result, 300);

                } else {
                    // End of Test: Calculate Final Scores
                    $this->finalizeTest($userTest, $scoringService);
                    
                    $result = [
                        'status' => 'success',
                        'test_completed' => true,
                        'redirect_url' => route('my-practice.score', $userTest->id),
                        'message' => 'Test completed and scored.',
                    ];
                    Cache::put("scoring_result_{$userTest->id}", $result, 300);
                }
            }
        } catch (\Throwable $e) {
            Log::error("EXCEPTION in ScoreModuleJob", ['exception' => $e]);
            $result = [
                'status' => 'error',
                'error' => 'Server error during submission.',
                'message' => 'An unexpected server error occurred.'
            ];
            Cache::put("scoring_result_{$this->userTestId}", $result, 300);
        }
    }

    private function finalizeTest(UserTest $userTest, SatScoringService $scoringService)
    {
        if ($userTest->user?->role === 'admin') {
            $userTest->update([
                'score_reading_writing' => null,
                'score_math' => null,
                'rw_theta' => null,
                'math_theta' => null,
                'total_score' => null,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            return;
        }

        $test = Test::with('sections.modules.questions')->find($userTest->test_id);
        
        $rwSection = $test->sections->where('type', 'reading_writing')->first();
        $mathSection = $test->sections->where('type', 'math')->first();

        // Calculate R&W
        $rwScore = $this->calculateSectionScore($userTest, $rwSection, $userTest->rw_m2_path, $scoringService);
        
        // Calculate Math
        $mathScore = $this->calculateSectionScore($userTest, $mathSection, $userTest->math_m2_path, $scoringService);

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

    private function calculateSectionScore(UserTest $userTest, $section, $m2Path, SatScoringService $scoringService)
    {
        if (!$section) return ['scaled_score' => 200, 'theta' => -3.5];

        $m1 = $section->modules->where('module_number', 1)->first();
        // Specific difficulty only, no silent fallback
        $m2 = $section->modules->where('module_number', 2)->where('difficulty_level', $m2Path)->first();

        if (!$m1 || !$m2) {
            Log::warning("Missing module for final scoring in Section {$section->id}. M1: " . ($m1 ? 'found' : 'missing') . ", M2 ({$m2Path}): " . ($m2 ? 'found' : 'missing'));
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

        return $scoringService->scoreSection($m1Responses, $m2Responses, $m2Path);
    }
}
