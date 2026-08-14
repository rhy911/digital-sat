<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamSessionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isApprovedTeacher() || $this->user()?->role === 'admin'; }

    public function rules(): array
    {
        return [
            'test_id' => 'required|integer|exists:tests,id',
            'title' => 'required|string|max:180',
            'expires_at' => 'nullable|date|after:now',
        ];
    }
}
