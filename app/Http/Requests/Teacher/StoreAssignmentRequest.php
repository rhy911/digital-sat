<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isApprovedTeacher() || $this->user()?->role === 'admin'; }
    public function rules(): array
    {
        return [
            'test_id' => 'required|integer|exists:tests,id',
            'title' => 'required|string|max:180',
            'instructions' => 'nullable|string|max:4000',
            'available_at' => 'nullable|date',
            'due_at' => 'nullable|date',
            'attempt_limit' => 'required|integer|min:1|max:10',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('available_at') && $this->filled('due_at') && strtotime($this->input('due_at')) <= strtotime($this->input('available_at'))) {
                $validator->errors()->add('due_at', 'Due time must be after availability time.');
            }
        }];
    }
}
