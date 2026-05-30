<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_test_id' => 'required|exists:user_tests,id',
            'module_id' => 'required|exists:modules,id',
            'answers' => 'present|array|max:100',
            'answers.*' => 'nullable|string|max:100',
        ];
    }
}
