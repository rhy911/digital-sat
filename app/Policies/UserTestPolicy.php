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
            return $userTest->assignment()
                ->whereHas('classroom', fn ($query) => $query
                    ->where('owner_id', $user->id)
                    ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($user->id)))
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
}
