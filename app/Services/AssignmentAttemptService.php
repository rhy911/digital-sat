<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentAttemptService
{
    public function __construct(
        private AttemptProgressionService $progression,
        private TestStructureService $structures,
    ) {}

    public function startOrResume(Assignment $assignment, User $student): UserTest
    {
        return DB::transaction(function () use ($assignment, $student) {
            $assignment = Assignment::with(['classroom', 'test'])->lockForUpdate()->findOrFail($assignment->id);
            $recipient = AssignmentRecipient::where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)->lockForUpdate()->first();

            if (! $recipient || $recipient->status !== 'active') {
                throw ValidationException::withMessages(['assignment' => 'This assignment is not available to you.']);
            }

            $inProgress = UserTest::where('assignment_id', $assignment->id)
                ->where('user_id', $student->id)->where('status', 'in_progress')->latest()->first();
            if ($inProgress) {
                if ($inProgress->current_module_id || $this->progression->firstModule($assignment->test)) {
                    $this->progression->issueInitialModule($inProgress, $assignment->test);
                }

                return $inProgress;
            }

            if (! $assignment->acceptsNewStarts()) {
                throw ValidationException::withMessages(['assignment' => 'This assignment is not open for a new attempt.']);
            }
            if ($assignment->test->status !== 'active') {
                throw ValidationException::withMessages(['assignment' => 'The assigned test is no longer active.']);
            }
            $this->structures->validateForPublication($assignment->test);

            $attemptNumber = (int) UserTest::where('assignment_id', $assignment->id)
                ->where('user_id', $student->id)->max('attempt_number') + 1;
            if ($attemptNumber > $assignment->attempt_limit) {
                throw ValidationException::withMessages(['assignment' => 'You have used every allowed attempt.']);
            }

            $attempt = UserTest::create([
                'user_id' => $student->id,
                'test_id' => $assignment->test_id,
                'assignment_id' => $assignment->id,
                'attempt_number' => $attemptNumber,
                'status' => 'in_progress',
            ]);

            $this->progression->issueInitialModule($attempt, $assignment->test);

            return $attempt;
        });
    }
}
