<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;

class ModulePolicy
{
    public function update(User $user, Module $module): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        
        if ($user->role === 'teacher') {
            return $user->id === $module->created_by;
        }

        return false;
    }

    public function delete(User $user, Module $module): bool
    {
        return $this->update($user, $module);
    }
}
