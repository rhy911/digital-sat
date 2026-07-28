<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // SectionController::store() calls $this->authorize('update', $test) after resolving test_id
    }

    public function rules(): array
    {
        return [
            'test_id' => 'required|exists:tests,id',
            'name' => 'nullable|string|max:255',
            'type' => 'required|in:reading_writing,math',
            'is_public' => 'nullable|boolean',
        ];
    }
}
