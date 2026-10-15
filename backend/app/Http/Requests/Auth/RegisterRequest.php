<?php

namespace App\Http\Requests\Auth;

use App\Rules\AccountPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** BR-02: emails are compared trimmed and lower-cased. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Họ tên phải dài 2–100 ký tự.',
            'name.max' => 'Họ tên phải dài 2–100 ký tự.',
            'email.unique' => 'Email này đã được sử dụng cho một tài khoản Aura Court.',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users'],
            'password' => ['required', 'string', new AccountPassword, 'confirmed'],
        ];
    }
}
