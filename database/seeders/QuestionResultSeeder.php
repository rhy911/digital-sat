<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Models\Question;
use App\Models\AnswerChoice;

/**
 * QuestionResultSeeder
 *
 * Creates a realistic completed UserTest with UserTestAnswer rows so
 * the Score Details page has meaningful data to display.
 *
 * Run:  php artisan db:seed --class=QuestionResultSeeder
 *
 * Safe to run multiple times (uses firstOrCreate for UserTest,
 * updateOrCreate for every answer row).
 */
class QuestionResultSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Resolve user & test ─────────────────────────────────────
        $user = User::first();
        $test = Test::first();

        if (! $user || ! $test) {
            $this->command->warn('No user or test found. Aborting QuestionResultSeeder.');
            return;
        }

        // ── 2. Create (or find) a completed UserTest ───────────────────
        $userTest = UserTest::updateOrCreate(
            [
                'user_id' => $user->id,
                'test_id' => $test->id,
                'status'  => 'completed',
            ],
            [
                'score_reading_writing' => 680,
                'score_math'            => 720,
                'total_score'           => 1400,
                'completed_at'          => now()->subDays(2),
            ]
        );

        // ── 3. Pull all non-pretest questions (up to 44) ───────────────
        $questions = Question::where('is_pretest', false)
            ->with('answerChoices', 'sprCorrectAnswers')
            ->take(44)
            ->get();

        if ($questions->isEmpty()) {
            $this->command->warn('No non-pretest questions found. Aborting.');
            return;
        }

        // ── 4. Simulate varied answer patterns ─────────────────────────
        // We deliberately distribute correct / wrong / omitted answers so
        // every performance level (High / Medium / Low) appears in the UI.
        $patterns = [
            'correct',   // 0
            'correct',   // 1
            'wrong',     // 2
            'correct',   // 3
            'omitted',   // 4
            'correct',   // 5
            'wrong',     // 6
            'correct',   // 7
            'correct',   // 8
            'wrong',     // 9
            'omitted',   // 10
            'correct',   // 11  (repeats cyclically)
        ];

        foreach ($questions as $index => $question) {
            $outcome = $patterns[$index % count($patterns)];

            [$selectedAnswer, $isCorrect] = $this->resolveAnswer($question, $outcome);

            UserTestAnswer::updateOrCreate(
                [
                    'user_test_id' => $userTest->id,
                    'question_id'  => $question->id,
                ],
                [
                    'selected_answer' => $selectedAnswer,
                    'is_correct'      => $isCorrect,
                ]
            );
        }

        $total    = $questions->count();
        $correct  = $questions->filter(fn ($q) => $this->resolveAnswer($q, $patterns[$questions->search($q) % count($patterns)])[1])->count();

        $this->command->info("✓ QuestionResultSeeder done.");
        $this->command->info("  UserTest ID : {$userTest->id}");
        $this->command->info("  User        : {$user->email}");
        $this->command->info("  Questions   : {$total}");
        $this->command->info("  URL         : /my-practice/{$userTest->id}/score");
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * Given a question and a desired outcome ('correct' | 'wrong' | 'omitted'),
     * return [$selectedAnswer, $isCorrect].
     */
    private function resolveAnswer(Question $question, string $outcome): array
    {
        if ($outcome === 'omitted') {
            return [null, false];
        }

        if ($question->question_type === 'spr') {
            // Student-produced response
            $correct = $question->sprCorrectAnswers->first()?->answer ?? '42';
            if ($outcome === 'correct') {
                return [$correct, true];
            }
            // Wrong SPR: give a clearly wrong number
            return ['999', false];
        }

        // MCQ
        $correctChoice = $question->answerChoices->firstWhere('is_correct', true);
        $wrongChoice   = $question->answerChoices->firstWhere('is_correct', false);

        if ($outcome === 'correct' && $correctChoice) {
            return [$correctChoice->label, true];
        }

        if ($outcome === 'wrong' && $wrongChoice) {
            return [$wrongChoice->label, false];
        }

        // Fallback
        return [$correctChoice?->label ?? 'A', (bool) $correctChoice];
    }
}
