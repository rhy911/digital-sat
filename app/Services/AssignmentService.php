<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(
        private AttemptEngine $engine,
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
                if ($inProgress->current_module_id || $this->engine->firstModule($assignment->test, $inProgress)) {
                    $this->engine->issueInitialModule($inProgress, $assignment->test);
                }

                return $inProgress;
            }

            if (! $assignment->acceptsNewStarts()) {
                throw ValidationException::withMessages(['assignment' => 'This assignment is not open for a new attempt.']);
            }
            if ($assignment->test->status !== 'active') {
                throw ValidationException::withMessages(['assignment' => 'The assigned test is no longer active.']);
            }

            $attemptNumber = (int) UserTest::where('assignment_id', $assignment->id)
                ->where('user_id', $student->id)->max('attempt_number') + 1;
            $maxAllowed = (int) ($assignment->attempt_limit + ($recipient->extra_attempts ?? 0));
            if ($attemptNumber > $maxAllowed) {
                throw ValidationException::withMessages(['assignment' => 'You have used every allowed attempt.']);
            }

            $attempt = UserTest::create([
                'user_id' => $student->id,
                'test_id' => $assignment->test_id,
                'assignment_id' => $assignment->id,
                'attempt_number' => $attemptNumber,
                'status' => 'in_progress',
                'attempt_type' => $assignment->assign_type ?? 'full',
                'section_type' => $assignment->section_type,
            ]);

            $this->engine->issueInitialModule($attempt, $assignment->test);

            return $attempt;
        });
    }

    public function publish(Assignment $assignment, ?array $targetStudentIds = null): Assignment
    {
        return DB::transaction(function () use ($assignment, $targetStudentIds) {
            $assignment = Assignment::with(['classroom.activeMemberships', 'test'])->lockForUpdate()->findOrFail($assignment->id);
            if ($assignment->classroom->status !== 'active' || $assignment->test->status !== 'active') {
                throw ValidationException::withMessages(['assignment' => 'Class and test must both be active.']);
            }
            if (! $assignment->test->isStructurallyComplete()) {
                throw ValidationException::withMessages(['assignment' => 'Test must contain questions in every module before publishing.']);
            }

            $eligibleMemberships = $assignment->classroom->activeMemberships;
            if (!empty($targetStudentIds)) {
                $eligibleMemberships = $eligibleMemberships->whereIn('student_id', $targetStudentIds);
            }

            foreach ($eligibleMemberships as $membership) {
                AssignmentRecipient::updateOrCreate(
                    ['assignment_id' => $assignment->id, 'student_id' => $membership->student_id],
                    ['status' => 'active', 'assigned_at' => now(), 'withdrawn_at' => null]
                );
            }

            $assignment->update(['status' => 'published', 'published_at' => now(), 'closed_at' => null]);
            $assignment->test->syncContentLock();

            return $assignment->fresh(['recipients.student', 'classroom', 'test']);
        });
    }

    public function close(Assignment $assignment): void
    {
        if ($assignment->classroom->status !== 'active') {
            throw ValidationException::withMessages(['assignment' => 'Archived classes are read-only.']);
        }
        DB::transaction(function () use ($assignment) {
            $assignment = Assignment::lockForUpdate()->findOrFail($assignment->id);
            $assignment->update(['status' => 'closed', 'closed_at' => now()]);
            $assignment->test->syncContentLock();
        });
    }

    public function reopen(Assignment $assignment): void
    {
        if ($assignment->classroom->status !== 'active' || ($assignment->due_at && now()->gte($assignment->due_at))) {
            throw ValidationException::withMessages(['assignment' => 'Extend the due time and activate the class before reopening.']);
        }

        DB::transaction(function () use ($assignment) {
            $assignment->update(['status' => 'published', 'closed_at' => null]);
            $assignment->test->syncContentLock();

            $assignment->classroom->load('activeMemberships');
            foreach ($assignment->classroom->activeMemberships as $membership) {
                AssignmentRecipient::updateOrCreate(
                    ['assignment_id' => $assignment->id, 'student_id' => $membership->student_id],
                    ['status' => 'active', 'assigned_at' => now(), 'withdrawn_at' => null]
                );
            }
        });
    }

    public function delete(Assignment $assignment): void
    {
        DB::transaction(function () use ($assignment) {
            $assignment = Assignment::lockForUpdate()->findOrFail($assignment->id);
            $test = $assignment->test;
            $assignment->delete();
            $test->syncContentLock();
        });
    }
}
