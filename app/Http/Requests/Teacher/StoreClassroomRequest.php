<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isApprovedTeacher() ?? false; }
    public function rules(): array { return ['name' => 'required|string|max:150', 'description' => 'nullable|string|max:2000']; }
}
