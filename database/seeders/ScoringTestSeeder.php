<?php

namespace Database\Seeders;

use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\SatScoringService;
use Illuminate\Database\Seeder;

class ScoringTestSeeder extends Seeder
{
    public function run()
    {
        $user = User::first();
        if (! $user) {
            $user = User::create([
                'username' => 'testuser',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        $test = Test::with('sections.modules.questions.answerChoices')->first();
        if (! $test) {
            echo "No test found to run simulation.\n";

            return;
        }

        echo "--- SCENARIO A: ROUTING TEST ---\n";

        // Clean up previous test runs for this user/test to ensure fresh state
        UserTest::where('user_id', $user->id)->where('test_id', $test->id)->delete();

        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        $section = $test->sections->where('type', 'reading_writing')->first();
        $m1 = $section->modules->where('module_number', 1)->first();

        echo "Simulating perfect M1 for section: {$section->name}\n";
        foreach ($m1->questions as $q) {
            $correctLabel = $q->answerChoices->where('is_correct', true)->first()?->label ?? 'A';
            UserTestAnswer::updateOrCreate(
                ['user_test_id' => $userTest->id, 'question_id' => $q->id],
                ['selected_answer' => $correctLabel, 'is_correct' => true]
            );
        }

        $scoringService = new SatScoringService;
        $m1Responses = UserTestAnswer::where('user_test_id', $userTest->id)
            ->whereIn('question_id', $m1->questions->pluck('id'))
            ->with('question')
            ->get();

        $thetaM1 = $scoringService->estimateTheta($m1Responses);
        $path = $scoringService->routeModule2($thetaM1);

        echo "Theta M1: $thetaM1\n";
        echo "Routed Path: $path (Expected: hard)\n";

        $userTest->rw_m2_path = $path;
        $userTest->save();

        echo "\n--- SCENARIO B: SCORING TEST ---\n";
        $m2 = $section->modules->where('module_number', 2)->where('difficulty_level', $path)->first();

        echo "Simulating 50% correct in M2: {$m2->difficulty_level}\n";
        $m2Questions = $m2->questions;
        foreach ($m2Questions as $index => $q) {
            $isCorrect = ($index % 2 == 0);
            $correctLabel = $q->answerChoices->where('is_correct', true)->first()?->label ?? 'A';
            $wrongLabel = ($correctLabel === 'A') ? 'B' : 'A';

            UserTestAnswer::updateOrCreate(
                ['user_test_id' => $userTest->id, 'question_id' => $q->id],
                ['selected_answer' => $isCorrect ? $correctLabel : $wrongLabel, 'is_correct' => $isCorrect]
            );
        }

        $m2Responses = UserTestAnswer::where('user_test_id', $userTest->id)
            ->whereIn('question_id', $m2->questions->pluck('id'))
            ->with('question')
            ->get();

        $result = $scoringService->scoreSection($m1Responses, $m2Responses);

        echo "Final RW Theta: {$result['theta']}\n";
        echo "Final RW Raw Score: {$result['raw_score']} / {$result['scored_questions']}\n";
        echo "Scaled score requires an approved form-specific conversion set.\n";
    }
}
