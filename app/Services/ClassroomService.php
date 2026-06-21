<?php

namespace App\Services;

use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassroomService
{
    public function requestMembership(User $student, string $joinCode): ClassroomMembership
    {
        if ($student->role !== 'student') {
            throw ValidationException::withMessages(['join_code' => 'Only student accounts can join classes.']);
        }

        $classroom = Classroom::where('join_code', strtoupper(trim($joinCode)))->where('status', 'active')->first();
        if (!$classroom) {
            throw ValidationException::withMessages(['join_code' => 'This class code is invalid or no longer active.']);
        }

        return DB::transaction(function () use ($classroom, $student) {
            $membership = ClassroomMembership::where('classroom_id', $classroom->id)
                ->where('student_id', $student->id)->lockForUpdate()->first();

            if ($membership?->status === 'active') {
                throw ValidationException::withMessages(['join_code' => 'You already belong to this class.']);
            }
            if ($membership?->status === 'pending') {
                throw ValidationException::withMessages(['join_code' => 'Your request is already awaiting approval.']);
            }

            return ClassroomMembership::updateOrCreate(
                ['classroom_id' => $classroom->id, 'student_id' => $student->id],
                ['status' => 'pending', 'requested_at' => now(), 'decided_at' => null, 'ended_at' => null, 'decided_by' => null]
            );
        });
    }

    public function decide(ClassroomMembership $membership, User $actor, bool $approve): ClassroomMembership
    {
        return DB::transaction(function () use ($membership, $actor, $approve) {
            $membership = ClassroomMembership::lockForUpdate()->findOrFail($membership->id);
            $membership->update([
                'status' => $approve ? 'active' : 'rejected',
                'decided_at' => now(),
                'ended_at' => $approve ? null : now(),
                'decided_by' => $actor->id,
            ]);

            if ($approve) {
                $membership->classroom->assignments()
                    ->where('status', 'published')
                    ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>', now()))
                    ->each(fn ($assignment) => AssignmentRecipient::updateOrCreate(
                        ['assignment_id' => $assignment->id, 'student_id' => $membership->student_id],
                        ['status' => 'active', 'assigned_at' => now(), 'withdrawn_at' => null]
                    ));
            }

            return $membership->fresh(['classroom', 'student']);
        });
    }

    public function endMembership(ClassroomMembership $membership, User $actor, string $status): void
    {
        DB::transaction(function () use ($membership, $actor, $status) {
            $membership = ClassroomMembership::lockForUpdate()->findOrFail($membership->id);
            $membership->update(['status' => $status, 'ended_at' => now(), 'decided_by' => $actor->id]);

            AssignmentRecipient::where('student_id', $membership->student_id)
                ->whereHas('assignment', fn ($query) => $query->where('classroom_id', $membership->classroom_id))
                ->where('status', 'active')
                ->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        });
    }

    public function archive(Classroom $classroom): void
    {
        DB::transaction(function () use ($classroom) {
            $classroom->update(['status' => 'archived']);
            $classroom->assignments()->where('status', 'published')->update(['status' => 'closed', 'closed_at' => now()]);
        });
    }
}
