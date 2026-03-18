<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (!hash_equals(
                (string) $this->route('hash'),
                sha1($this->user()->getEmailForVerification())
            )) {
                $validator->errors()->add('hash', 'Link xác minh không hợp lệ.');
            }
        });
    }
}
