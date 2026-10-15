<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** BR-03: 8–64 characters, at least one upper-case letter, one lower-case letter and one digit, no whitespace. */
class AccountPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(?=.*\p{Lu})(?=.*\p{Ll})(?=.*\d)\S{8,64}$/u', $value)) {
            $fail('Mật khẩu phải dài 8–64 ký tự, có ít nhất một chữ in hoa, một chữ thường, một chữ số và không chứa khoảng trắng.');
        }
    }
}
