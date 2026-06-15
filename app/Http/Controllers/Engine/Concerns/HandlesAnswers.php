<?php

namespace App\Http\Controllers\Engine\Concerns;

use App\Models\Module;
use App\Models\Question;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

trait HandlesAnswers
{
    protected function checkAnswer(Question $question, $userAnswer)
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

    protected function resolveSubmissionContext(array $validated): array
    {
        $userTest = UserTest::where('id', $validated['user_test_id'])
            ->where('user_id', Auth::id())
            ->where('status', 'in_progress')
            ->first();

        if (!$userTest) {
            throw new AuthorizationException('This test attempt is no longer available.');
        }

        $module = Module::with(['section.test', 'questions'])->findOrFail($validated['module_id']);
        $section = $module->section;

        if (!$section || (int) $section->test_id !== (int) $userTest->test_id) {
            throw new AuthorizationException('This module does not belong to the active test attempt.');
        }

        $questionIds = collect(array_keys($validated['answers']))
            ->map(fn($id) => (string) $id);

        if ($questionIds->contains(fn($id) => !ctype_digit($id))) {
            throw ValidationException::withMessages([
                'answers' => 'Submitted answers contain invalid question ids.',
            ]);
        }

        $questionIds = $questionIds->map(fn($id) => (int) $id)->unique()->values();
        if ($questionIds->isNotEmpty()) {
            $validQuestionIds = $module->questions()
                ->whereIn('questions.id', $questionIds->all())
                ->pluck('questions.id')
                ->map(fn($id) => (int) $id);

            if ($validQuestionIds->count() !== $questionIds->count()) {
                throw ValidationException::withMessages([
                    'answers' => 'Submitted answers include questions outside the current module.',
                ]);
            }
        }

        return [$userTest, $module];
    }

    protected function saveModuleAnswers(UserTest $userTest, Module $module, array $submittedAnswers): int
    {
        $questionIds = collect(array_keys($submittedAnswers))
            ->map(fn($id) => (int) $id)
            ->values();

        if ($questionIds->isEmpty()) {
            return 0;
        }

        $questions = Question::with(['answerChoices', 'sprCorrectAnswers'])
            ->whereIn('id', $questionIds->all())
            ->get()
            ->keyBy('id');

        $savedCount = 0;
        $upsertData = [];

        foreach ($submittedAnswers as $questionId => $answer) {
            $question = $questions->get((int) $questionId);
            if (!$question) {
                continue;
            }

            $normalizedAnswer = $answer === null ? null : trim((string) $answer);
            if ($normalizedAnswer === '') {
                $normalizedAnswer = null;
            }
            $isCorrect = $normalizedAnswer !== null && $normalizedAnswer !== ''
                ? $this->checkAnswer($question, $normalizedAnswer)
                : false;

            $row = [
                'user_test_id' => $userTest->id,
                'question_id' => (int) $questionId,
                'selected_answer' => $normalizedAnswer,
                'is_correct' => $isCorrect,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('user_test_answers', 'module_id')) {
                $row['module_id'] = $module->id;
            }

            $upsertData[] = $row;
            $savedCount++;
        }

        if (!empty($upsertData)) {
            $uniqueBy = Schema::hasColumn('user_test_answers', 'module_id')
                ? ['user_test_id', 'module_id', 'question_id']
                : ['user_test_id', 'question_id'];

            UserTestAnswer::upsert(
                $upsertData,
                $uniqueBy,
                ['selected_answer', 'is_correct', 'updated_at']
            );
        }

        return $savedCount;
    }
}
