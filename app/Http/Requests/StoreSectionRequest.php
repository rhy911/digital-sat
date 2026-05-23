<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'test_id' => 'required|exists:tests,id',
            'name' => 'nullable|string|max:255',
            'type' => 'required|in:reading_writing,math',
        ];
    }
}
