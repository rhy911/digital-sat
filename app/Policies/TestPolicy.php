<?php

namespace App\Policies;

use App\Models\Test;
use App\Models\User;

class TestPolicy
{
    public function update(User $user, Test $test): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        
        if ($user->role === 'teacher') {
            return $user->id === $test->created_by;
        }

        return false;
    }

    public function delete(User $user, Test $test): bool
    {
        return $this->update($user, $test);
    }

    public function clone(User $user, Test $test): bool
    {
        // For clone, maybe anyone can clone a visible test?
        // But the check in TestDashboardController was:
        // $originalTest = Test::visibleTo(auth()->user())->findOrFail($id);
        // It didn't have an explicit created_by check for cloning in the controller.
        // We'll leave clone as a controller check if it's just visibility, or return true here.
        return true;
    }
}
