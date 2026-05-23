<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'test_type' => 'required|in:full_length,section_only,module_only,short_test',
            'break_duration_minutes' => 'required|integer|min:0',
            'status' => 'required|in:draft,active,archived',
        ];
    }
}
