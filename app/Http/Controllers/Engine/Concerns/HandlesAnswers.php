<?php

namespace App\Http\Controllers\Engine\Concerns;

use App\Models\Module;
use App\Models\Question;
use App\Models\UserTest;
use App\Services\ExamAttemptEngine;

/**
 * Backward compatibility trait delegating attempt answer operations to ExamAttemptEngine.
 */
trait HandlesAnswers
{
    protected function checkAnswer(Question $question, $userAnswer): bool
    {
        return app(ExamAttemptEngine::class)->checkAnswer($question, $userAnswer);
    }

    protected function parseNumericAnswer(string $value): ?float
    {
        return app(ExamAttemptEngine::class)->parseNumericAnswer($value);
    }

    protected function resolveSubmissionContext(array $validated, ?UserTest $lockedAttempt = null): array
    {
        return app(ExamAttemptEngine::class)->resolveSubmissionContext($validated, $lockedAttempt);
    }

    protected function saveModuleAnswers(
        UserTest $userTest,
        Module $module,
        array $submittedAnswers,
        array $submittedTimes = [],
        bool $onlyMissing = false
    ): int {
        return app(ExamAttemptEngine::class)->saveModuleAnswers(
            $userTest,
            $module,
            $submittedAnswers,
            $submittedTimes,
            $onlyMissing
        );
    }
}
