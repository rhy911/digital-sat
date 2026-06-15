<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'teacher']);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'test_type' => 'required|in:full_length,section_only,module_only,short_test,custom_test',
            'break_duration_minutes' => 'required|integer|min:0',
            'status' => 'required|in:draft,active,archived',
            'is_public' => 'nullable|boolean',
        ];
    }
}
