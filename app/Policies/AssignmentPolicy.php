<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->role === 'admin' || $assignment->classroom->hasTeacher($user)) return true;
        return $user->role === 'student' && $assignment->recipients()->where('student_id', $user->id)->exists();
    }

    public function manage(User $user, Assignment $assignment): bool
    {
        return $user->role === 'admin' || $assignment->classroom->hasTeacher($user);
    }
}
