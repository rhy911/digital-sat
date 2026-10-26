<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamSessionStatusRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isApprovedTeacher() || $this->user()?->role === 'admin'; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:active,paused,closed',
        ];
    }
}
