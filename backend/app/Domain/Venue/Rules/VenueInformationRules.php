<?php

namespace App\Domain\Venue\Rules;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * BR-26: the one rule source for venue information. The venue write request (task 2.2) and the open and resume
 * conditions (task 2.4) both validate with it; every text value is trimmed first and stored trimmed.
 */
final class VenueInformationRules
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.User::PHONE_PATTERN],
            'email' => ['required', 'string', 'max:254', 'not_regex:/\s/', 'email'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amenities' => ['nullable', 'array', 'max:20'],
            'amenities.*' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * One message per field, whichever rule fails. The item keys name each rule: a bare "amenities.*" key would
     * also match "amenities.max" and hide the list message.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $amenity = 'Mỗi tiện ích phải có từ 1 đến 50 ký tự.';

        return [
            'name' => 'Tên cơ sở phải có từ 2 đến 100 ký tự.',
            'address' => 'Địa chỉ phải có từ 10 đến 255 ký tự.',
            'phone' => 'Số điện thoại phải gồm số 0 và 9 chữ số, hoặc +84 và 9 chữ số.',
            'email' => 'Email phải đúng định dạng, không chứa khoảng trắng và tối đa 254 ký tự.',
            'description' => 'Mô tả tối đa 2000 ký tự.',
            'amenities' => 'Tiện ích tối đa 20 mục.',
            'amenities.*.required' => $amenity,
            'amenities.*.string' => $amenity,
            'amenities.*.max' => $amenity,
        ];
    }

    /**
     * Trims every string, list items included, before validation.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function trim(array $data): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            is_string($value) => Str::trim($value),
            is_array($value) => self::trim($value),
            default => $value,
        }, $data);
    }
}
