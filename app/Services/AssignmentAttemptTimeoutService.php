<?php

namespace App\Services;

use App\Models\UserTest;

/**
 * Sweep running assignment attempts and submit any modules whose server-side
 * time has elapsed. Delegates execution to ExamAttemptEngine.
 */
class AssignmentAttemptTimeoutService
{
    public function __construct(
        private ExamAttemptEngine $engine,
    ) {}

    /**
     * Candidate attempts for a sweep: every running assignment attempt whose clock
     * has started.
     *
     * @return \Illuminate\Database\Eloquent\Builder<UserTest>
     */
    public function candidateAttemptsQuery()
    {
        return UserTest::query()
            ->where(fn ($q) => $q->whereNotNull('assignment_id')->orWhereNotNull('exam_session_id'))
            ->where('status', 'in_progress')
            ->whereNotNull('current_module_id')
            ->whereNotNull('current_module_started_at');
    }

    /**
     * Submit and advance every module of this attempt whose time has already run
     * out, in order, until one is still live or the attempt finishes.
     *
     * @return int modules submitted
     */
    public function finalizeExpired(UserTest $attempt): int
    {
        return $this->engine->cascadeExpire($attempt);
    }
}
