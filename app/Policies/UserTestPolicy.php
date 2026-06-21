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
            return $userTest->assignment()->where('teacher_id', $user->id)->exists();
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
}
