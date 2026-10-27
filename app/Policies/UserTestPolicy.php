<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserTest;

class UserTestPolicy
{
    public function view(User $user, UserTest $userTest): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'teacher' && $userTest->assignment_id) {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Assignment, \App\Models\UserTest> $assignmentQuery */
            $assignmentQuery = $userTest->assignment();

            return $assignmentQuery
                ->whereHas('classroom', function (\Illuminate\Database\Eloquent\Builder $query) use ($user) {
                    /** @var \Illuminate\Database\Eloquent\Builder<\App\Models\Classroom> $query */
                    $query->where('owner_id', $user->id)
                        ->orWhereHas('coTeachers', fn (\Illuminate\Database\Eloquent\Builder $teachers) => $teachers->whereKey($user->id));
                })
                ->exists();
        }

        // A standalone exam session is the teacher's own event, so its results
        // are theirs to read — but only the teacher who hosts that session, and
        // only for attempts belonging to it. Without this the attempt-detail
        // page could not link to a score report a teacher plainly owns.
        if ($user->role === 'teacher' && $userTest->exam_session_id) {
            return $userTest->examSession()
                ->where('teacher_id', $user->id)
                ->exists();
        }

        return (int) $userTest->user_id === (int) $user->id;
    }

    public function delete(User $user, UserTest $userTest): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return (int) $userTest->user_id === (int) $user->id && $userTest->status === 'in_progress';
    }

    public function viewAnalysis(User $user, UserTest $userTest): bool
    {
        return $this->view($user, $userTest)
            && ! ($user->is_guest && $userTest->is_public_trial);
    }
}
