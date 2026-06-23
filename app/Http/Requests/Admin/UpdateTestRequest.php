<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'test_type' => 'sometimes|required|in:full_length,adaptive_full_length,section_only,module_only,short_test,custom_test',
            'break_duration_minutes' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|required|in:draft,active,archived',
            'is_public' => 'nullable|boolean',
        ];
    }
}
