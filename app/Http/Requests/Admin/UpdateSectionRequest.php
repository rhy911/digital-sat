<?php

namespace App\Http\Requests\Admin;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = Section::find($this->route('id'));

        // Defer to the controller's own findOrFail() for a clean 404 when the section
        // doesn't exist; only enforce the ownership policy when it does.
        return ! $section || $this->user()->can('update', $section);
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:reading_writing,math',
            'is_public' => 'nullable|boolean',
        ];
    }
}
