<?php

namespace App\Http\Requests\Admin;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $module = Module::find($this->route('id'));

        // Defer to the controller's own findOrFail() for a clean 404 when the module
        // doesn't exist; only enforce the ownership policy when it does.
        return ! $module || $this->user()->can('update', $module);
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
