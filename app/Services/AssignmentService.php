<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(private TestContentLockService $locks) {}

    public function publish(Assignment $assignment): Assignment
    {
        return DB::transaction(function () use ($assignment) {
            $assignment = Assignment::with(['classroom.activeMemberships', 'test'])->lockForUpdate()->findOrFail($assignment->id);
            if ($assignment->status !== 'draft') {
                throw ValidationException::withMessages(['assignment' => 'Only draft assignments can be published.']);
            }
            if ($assignment->classroom->status !== 'active' || $assignment->test->status !== 'active') {
                throw ValidationException::withMessages(['assignment' => 'Class and test must both be active.']);
            }
            if (!$assignment->test->isStructurallyComplete()) {
                throw ValidationException::withMessages(['assignment' => 'Test must contain questions in every module before publishing.']);
            }

            foreach ($assignment->classroom->activeMemberships as $membership) {
                AssignmentRecipient::updateOrCreate(
                    ['assignment_id' => $assignment->id, 'student_id' => $membership->student_id],
                    ['status' => 'active', 'assigned_at' => now(), 'withdrawn_at' => null]
                );
            }

            $assignment->update(['status' => 'published', 'published_at' => now(), 'closed_at' => null]);
            $this->locks->syncLock($assignment->test);
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
            $this->locks->syncLock($assignment->test);
        });
    }

    public function reopen(Assignment $assignment): void
    {
        if ($assignment->classroom->status !== 'active' || ($assignment->due_at && now()->gte($assignment->due_at))) {
            throw ValidationException::withMessages(['assignment' => 'Extend the due time and activate the class before reopening.']);
        }

        DB::transaction(function () use ($assignment) {
            $assignment->update(['status' => 'published', 'closed_at' => null]);
            $this->locks->syncLock($assignment->test);

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
            $this->locks->syncLock($test);
        });
    }
}
