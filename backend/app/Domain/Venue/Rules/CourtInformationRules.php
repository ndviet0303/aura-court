<?php

namespace App\Domain\Venue\Rules;

use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Models\Court;
use App\Rules\StoredImageUrl;
use Closure;
use Illuminate\Validation\Rule;

/**
 * BR-27, D9–D11: the court fields an owner sets on create and may change later, for StoreCourtRequest and
 * UpdateCourtRequest. The code is the court's display name: 1–50 characters after trimming and unique within
 * its venue case-insensitively. The description is one plain string of at most 2000 characters (task 2.17).
 */
final class CourtInformationRules
{
    public const SURFACE_TYPES = ['wood', 'synthetic', 'acrylic', 'concrete', 'grass', 'clay', 'hard', 'other'];

    /** @return array<string, list<mixed>> */
    public static function rules(int $venueId, ?int $exceptCourtId = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', function (string $attribute, mixed $value, Closure $fail) use ($venueId, $exceptCourtId): void {
                if (is_string($value) && app(CourtRepositoryInterface::class)->codeTaken($venueId, $value, $exceptCourtId)) {
                    $fail('Mã sân đã có ở một sân khác của cơ sở này.');
                }
            }],
            'description' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['string', new StoredImageUrl],
            'slot_duration' => ['integer', Rule::in(Court::SLOT_DURATIONS)],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string'],
            'surface_type' => ['nullable', Rule::in(self::SURFACE_TYPES)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        $code = 'Mã sân phải có từ 1 đến 50 ký tự.';
        $venue = 'Vui lòng chọn cơ sở của sân.';

        // Rule-specific keys for code, venue_id and sport_type: a bare field key would also replace the closure
        // messages (code taken, venue or sport changed).
        return [
            'code.required' => $code,
            'code.string' => $code,
            'code.max' => $code,
            'description' => 'Mô tả sân tối đa 2000 ký tự.',
            'images' => 'Mỗi sân tối đa 10 ảnh.',
            'venue_id.required' => $venue,
            'venue_id.integer' => $venue,
            'venue_id.exists' => $venue,
            'sport_type.required' => 'Vui lòng chọn môn thể thao của sân.',
            'sport_type.in' => 'Môn thể thao không được hỗ trợ.',
            'slot_duration' => 'Thời lượng mỗi lượt đặt phải là 30, 45, 60, 90 hoặc 120 phút.',
            'surface_type' => 'Loại mặt sân không được hỗ trợ.',
            'capacity' => 'Sức chứa phải từ 1 đến 100 người.',
            'amenities.*' => 'Tiện ích phải là danh sách văn bản.',
        ];
    }

    /**
     * The validated fields as the model stores them: a description string goes into both locales as before
     * (task 2.17 keeps that), a cleared one clears both, and no images is an empty list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toAttributes(array $data): array
    {
        // A bare null would clear only the request locale and the other one would show through the fallback.
        if (array_key_exists('description', $data)) {
            $data['description'] = ['vi' => $data['description'], 'en' => $data['description']];
        }
        if (array_key_exists('images', $data)) {
            $data['images'] ??= [];
        }

        return $data;
    }
}
