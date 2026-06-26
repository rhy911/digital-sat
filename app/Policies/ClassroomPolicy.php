<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function view(User $user, Classroom $classroom): bool
    {
        return $user->role === 'admin'
            || $classroom->hasTeacher($user)
            || ($user->role === 'student' && $classroom->memberships()->where('student_id', $user->id)->exists());
    }

    public function manage(User $user, Classroom $classroom): bool
    {
        return $user->role === 'admin' || $classroom->hasTeacher($user);
    }

    public function manageTeam(User $user, Classroom $classroom): bool
    {
        return $user->role === 'admin' || (int) $classroom->owner_id === (int) $user->id;
    }
}
