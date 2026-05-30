<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    public function update(User $user, Section $section): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        
        if ($user->role === 'teacher') {
            return $user->id === $section->created_by;
        }

        return false;
    }

    public function delete(User $user, Section $section): bool
    {
        return $this->update($user, $section);
    }
}
