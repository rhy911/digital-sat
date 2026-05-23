<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_id' => 'required|exists:modules,id',
            'section_id' => 'nullable|exists:sections,id',
            'test_id' => 'nullable|exists:tests,id',
            'section_type' => 'nullable|in:reading_writing,math',
        ];
    }
}
