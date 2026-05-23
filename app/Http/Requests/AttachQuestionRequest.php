<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_id' => 'required|exists:modules,id',
            'question_id' => 'required|exists:questions,id',
            'position' => 'nullable|integer|min:1',
        ];
    }
}
