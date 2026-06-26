<?php

namespace App\Policies;

use App\Models\ClassroomDocument;
use App\Models\User;

class ClassroomDocumentPolicy
{
    public function view(User $user, ClassroomDocument $document): bool
    {
        if ($user->role === 'admin' || $document->classroom->hasTeacher($user)) {
            return true;
        }

        return $user->role === 'student'
            && $document->classroom->memberships()
                ->where('student_id', $user->id)
                ->where('status', 'active')
                ->exists();
    }

    public function manage(User $user, ClassroomDocument $document): bool
    {
        return $user->role === 'admin' || $document->classroom->hasTeacher($user);
    }
}
