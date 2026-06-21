<?php

namespace App\Http\Requests\Teacher;

class UpdateClassroomRequest extends StoreClassroomRequest
{
    public function authorize(): bool
    {
        $classroom = $this->route('classroom');
        return $classroom && $this->user()?->can('manage', $classroom);
    }
}
