<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => 'nullable|exists:sections,id',
            'test_id' => 'nullable|exists:tests,id',
            'section_type' => 'nullable|in:reading_writing,math',
            'key' => 'nullable|string|unique:modules,key|max:255',
            'module_number' => 'required|integer|min:1',
            'difficulty_level' => 'required|in:standard,easy,hard',
            'duration_minutes' => 'required|integer|min:1',
            'total_questions' => 'required|integer|min:1',
        ];
    }
}
