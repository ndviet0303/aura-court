<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CompleteSocialEmailRequest extends FormRequest
{
    /** Only an account still holding the Facebook placeholder address may set its email this way. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->needsEmail();
    }

    /** BR-02: emails are compared trimmed and lower-cased. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users', 'not_regex:/@facebook\.auracourt\.vn$/'],
        ];
    }
}
