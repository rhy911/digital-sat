<?php

namespace App\Http\Requests\Admin;

use App\Models\Test;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $test = Test::find($this->route('id'));

        // Defer to the controller's own findOrFail() for a clean 404 when the test
        // doesn't exist; only enforce the ownership policy when it does.
        return ! $test || $this->user()->can('update', $test);
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'test_type' => 'sometimes|required|in:full_length,adaptive_full_length,section_only,module_only,short_test,custom_test',
            'break_duration_minutes' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|required|in:draft,active,archived',
            'is_public' => 'nullable|boolean',
        ];
    }
}
