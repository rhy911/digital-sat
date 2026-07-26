<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classroom = $this->route('classroom');
        $announcement = $this->route('announcement');

        // Must be able to view the class and the announcement must belong to it.
        return $this->user()?->can('view', $classroom)
            && $announcement
            && (int) $announcement->classroom_id === (int) $classroom->id;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
