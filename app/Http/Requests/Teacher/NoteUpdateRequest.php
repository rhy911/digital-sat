<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class NoteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ClassroomController::noteUpdate() calls $this->authorize('manage', $classroom) separately
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
