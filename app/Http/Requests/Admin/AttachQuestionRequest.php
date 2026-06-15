<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AttachQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $module = \App\Models\Module::findOrFail($this->input('module_id'));
        return $this->user()?->can('update', $module) ?? false;
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
