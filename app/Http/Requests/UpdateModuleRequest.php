<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => 'sometimes|required|string|max:255',
            'module_number' => 'sometimes|required|integer|min:1',
            'difficulty_level' => 'sometimes|required|in:standard,easy,hard',
            'duration_minutes' => 'sometimes|required|integer|min:1',
            'total_questions' => 'sometimes|required|integer|min:1',
            'is_public' => 'nullable|boolean',
        ];
    }
}
