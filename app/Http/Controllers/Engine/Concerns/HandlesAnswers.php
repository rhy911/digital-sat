<?php

namespace App\Http\Controllers\Engine\Concerns;

use App\Models\Module;
use App\Models\Question;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

trait HandlesAnswers
{
    protected function checkAnswer(Question $question, $userAnswer)
    {
        if ($question->question_type === 'multiple_choice') {
            $correctChoice = $question->answerChoices->where('is_correct', true)->first();

            return $correctChoice && trim($correctChoice->label) === trim($userAnswer);
        }

        if ($question->question_type === 'student_produced_response' || $question->question_type === 'spr') {
            $submitted = trim((string) $userAnswer);
            $submittedNumber = $this->parseNumericAnswer($submitted);

            foreach ($question->sprCorrectAnswers as $accepted) {
                $acceptedText = trim((string) $accepted->answer);
                $acceptedNumber = $this->parseNumericAnswer($acceptedText);

                if ($submittedNumber !== null && $acceptedNumber !== null) {
                    $tolerance = $accepted->tolerance !== null
                        ? max(0.0, (float) $accepted->tolerance)
                        : 0.0001;

                    if (abs($submittedNumber - $acceptedNumber) <= $tolerance) {
                        return true;
                    }

                    continue;
                }

                if ($acceptedText === $submitted) {
                    return true;
                }
            }

            return false;
        }

        throw new \InvalidArgumentException("Unsupported question type '{$question->question_type}' for question ID {$question->id}");
    }

    private function parseNumericAnswer(string $value): ?float
    {
        $value = trim(str_replace(["\u{2212}", "\u{2013}"], '-', $value));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^([+-]?\d+)\s+(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', $value, $matches)) {
            $whole = (float) $matches[1];
            $numerator = (float) $matches[2];
            $denominator = (float) $matches[3];
            if ($denominator == 0.0) {
                return null;
            }

            $fraction = $numerator / $denominator;

            return $whole < 0 ? $whole - $fraction : $whole + $fraction;
        }

        if (preg_match('/^([+-]?(?:\d+(?:\.\d*)?|\.\d+))\s*\/\s*([+-]?(?:\d+(?:\.\d*)?|\.\d+))$/', $value, $matches)) {
            $denominator = (float) $matches[2];
            if ($denominator == 0.0) {
                return null;
            }

            return (float) $matches[1] / $denominator;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    protected function resolveSubmissionContext(array $validated, ?UserTest $lockedAttempt = null): array
    {
        $userTest = $lockedAttempt ?? UserTest::where('id', $validated['user_test_id'])
            ->where('user_id', Auth::id())
            ->lockForUpdate()
            ->first();

        if (! $userTest || $userTest->status !== 'in_progress') {
            throw new AuthorizationException('This test attempt is no longer available.');
        }

        if ($userTest->exam_session_id) {
            $userTest->loadMissing('examSession');
            if ($userTest->examSession && $userTest->examSession->status === 'paused') {
                throw new ConflictHttpException('session_paused');
            }
        }

        $module = Module::with(['section.test', 'questions'])->findOrFail($validated['module_id']);
        $section = $module->section;

        if (! $section || (int) $section->test_id !== (int) $userTest->test_id) {
            throw new AuthorizationException('This module does not belong to the active test attempt.');
        }

        if ((int) $userTest->current_module_id !== (int) $module->id) {
            // One of three distinct causes behind a module_progression_conflict.
            // Classified so a load test can prove which one actually fires
            // instead of leaving it to inference. Safe scalars only.
            Log::channel('queue')->info('conflict.module_mismatch', [
                'user_test_id' => $userTest->id,
                'requested_module_id' => (int) $module->id,
                'current_module_id' => (int) $userTest->current_module_id,
                'attempt_status' => $userTest->status,
            ]);

            throw new ConflictHttpException('This module is not active for the test attempt.');
        }

        $questionIds = collect(array_keys($validated['answers']))
            ->map(fn ($id) => (string) $id);

        if ($questionIds->contains(fn ($id) => ! ctype_digit($id))) {
            throw ValidationException::withMessages([
                'answers' => 'Submitted answers contain invalid question ids.',
            ]);
        }

        $questionIds = $questionIds->map(fn ($id) => (int) $id)->unique()->values();
        if ($questionIds->isNotEmpty()) {
            $validQuestionIds = $module->questions()
                ->whereIn('questions.id', $questionIds->all())
                ->pluck('questions.id')
                ->map(fn ($id) => (int) $id);

            if ($validQuestionIds->count() !== $questionIds->count()) {
                throw ValidationException::withMessages([
                    'answers' => 'Submitted answers include questions outside the current module.',
                ]);
            }
        }

        return [$userTest, $module];
    }

    protected function saveModuleAnswers(UserTest $userTest, Module $module, array $submittedAnswers, array $submittedTimes = [], bool $onlyMissing = false): int
    {
        $questions = $module->questions()
            ->with(['answerChoices', 'sprCorrectAnswers', 'passage', 'explanation'])
            ->get();
        $existingQuestionIds = $onlyMissing
            ? UserTestAnswer::where('user_test_id', $userTest->id)
                ->where('module_id', $module->id)
                ->pluck('question_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $savedCount = 0;
        $upsertData = [];

        foreach ($questions as $question) {
            if ($onlyMissing && in_array((int) $question->id, $existingQuestionIds, true)) {
                continue;
            }

            $questionId = (int) $question->id;
            $answer = $submittedAnswers[$questionId] ?? null;

            $normalizedAnswer = $answer === null ? null : trim((string) $answer);
            if ($normalizedAnswer === '') {
                $normalizedAnswer = null;
            }
            $isCorrect = $normalizedAnswer !== null && $normalizedAnswer !== ''
                ? $this->checkAnswer($question, $normalizedAnswer)
                : false;

            $snapshot = [
                'stem' => $question->stem,
                'question_type' => $question->question_type,
                'difficulty' => $question->difficulty,
                'is_pretest' => (bool) $question->is_pretest,
                'skill_domain' => $question->skill_domain,
                'skill_subdomain' => $question->skill_subdomain,
                'spr_hint' => $question->spr_hint,
                'module_position' => $question->pivot?->position,
                'calculator_allowed' => (bool) $question->calculator_allowed,
                'expected_time' => $question->expected_time,
                'irt_a' => (float) $question->irt_a,
                'irt_b' => (float) $question->irt_b,
                'irt_c' => (float) $question->irt_c,
                'irt_calibration_status' => $question->irt_calibration_status ?? 'provisional',
                'irt_calibration_version' => $question->irt_calibration_version,
                'section_type' => $question->section_type,
                'passage' => $question->passage ? [
                    'content' => $question->passage->content,
                ] : null,
                'choices' => $question->answerChoices->map(fn ($choice) => [
                    'label' => $choice->label,
                    'content' => $choice->content,
                    'is_correct' => (bool) $choice->is_correct,
                    'order' => $choice->order,
                ])->toArray(),
                'spr_answers' => $question->sprCorrectAnswers->map(fn ($ans) => [
                    'answer' => $ans->answer,
                    'answer_type' => $ans->answer_type,
                    'tolerance' => $ans->tolerance,
                ])->toArray(),
                'explanation' => $question->explanation ? [
                    'explanation' => $question->explanation->explanation,
                    'rationale_a' => $question->explanation->rationale_a,
                    'rationale_b' => $question->explanation->rationale_b,
                    'rationale_c' => $question->explanation->rationale_c,
                    'rationale_d' => $question->explanation->rationale_d,
                ] : null,
            ];

            $row = [
                'user_test_id' => $userTest->id,
                'module_id' => $module->id,
                'question_id' => (int) $questionId,
                'selected_answer' => $normalizedAnswer,
                'is_correct' => $isCorrect,
                'time_spent' => (int) ($submittedTimes[$questionId] ?? 0),
                'question_snapshot' => json_encode($snapshot),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $upsertData[] = $row;
            $savedCount++;
        }

        if (! empty($upsertData)) {
            $uniqueBy = ['user_test_id', 'module_id', 'question_id'];

            UserTestAnswer::upsert(
                $upsertData,
                $uniqueBy,
                ['selected_answer', 'is_correct', 'time_spent', 'updated_at', 'question_snapshot']
            );
        }

        return $savedCount;
    }
}
