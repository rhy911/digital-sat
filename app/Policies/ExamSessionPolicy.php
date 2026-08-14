<?php

namespace App\Policies;

use App\Models\ExamSession;
use App\Models\User;

class ExamSessionPolicy
{
    public function view(User $user, ExamSession $examSession): bool
    {
        return $user->role === 'admin' || (int) $examSession->teacher_id === (int) $user->id;
    }

    public function manage(User $user, ExamSession $examSession): bool
    {
        return $user->role === 'admin' || (int) $examSession->teacher_id === (int) $user->id;
    }
}
