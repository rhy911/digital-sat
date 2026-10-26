<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isApprovedTeacher() || $this->user()?->role === 'admin'; }
    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return [
                'title' => 'required|string|max:180',
                'instructions' => 'nullable|string|max:4000',
                'available_at' => 'nullable|date',
                'due_at' => 'nullable|date',
                'attempt_limit' => 'required|integer|min:1|max:10',
                'test_id' => 'sometimes|integer',
                'target_audience' => 'prohibited',
                'student_ids' => 'prohibited',
                'clock_mode' => 'prohibited',
                'assign_type' => 'prohibited',
                'section_type' => 'prohibited',
            ];
        }

        return [
            'test_id' => 'required|integer|exists:tests,id',
            'title' => 'required|string|max:180',
            'instructions' => 'nullable|string|max:4000',
            'available_at' => 'nullable|date',
            'due_at' => 'nullable|date',
            'attempt_limit' => 'required|integer|min:1|max:10',
            'assign_type' => 'nullable|string|in:full,section',
            'section_type' => 'required_if:assign_type,section|nullable|string|in:reading_writing,math',
            'target_audience' => 'nullable|string|in:all,specific',
            'student_ids' => 'required_if:target_audience,specific|nullable|array',
            'student_ids.*' => 'integer|exists:users,id',
            'clock_mode' => 'nullable|string|in:chained,paused',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('available_at') && $this->filled('due_at') && strtotime($this->input('due_at')) <= strtotime($this->input('available_at'))) {
                $validator->errors()->add('due_at', 'Due time must be after availability time.');
            }
        }];
    }
}
