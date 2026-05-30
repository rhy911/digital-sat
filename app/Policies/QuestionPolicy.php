<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function update(User $user, Question $question): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        
        if ($user->role === 'teacher') {
            return $user->id === $question->created_by;
        }

        return false;
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->update($user, $question);
    }
}
