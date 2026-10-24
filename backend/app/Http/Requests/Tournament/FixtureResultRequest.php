<?php

namespace App\Http\Requests\Tournament;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-7.32, UC-7.33, BR-156: a score (badminton games, football goals and shoot-out) or the absent side of a walkover.
 * The score's shape is judged with the sport's rules by the domain; a correction also needs a reason.
 */
class FixtureResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $walkover = $this->routeIs('tournament-fixtures.walkover');

        return [
            'reason' => [$this->routeIs('tournament-fixtures.correction') ? 'required' : 'prohibited', 'string', 'max:1000'],
            'absent_entry_id' => [$walkover ? 'required' : 'nullable', 'integer', 'min:1'],
            'games' => [$walkover ? 'prohibited' : 'nullable', 'array', 'max:5'],
            'goals' => [$walkover ? 'prohibited' : 'nullable', 'array'],
            'shootout' => [$walkover ? 'prohibited' : 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Vui lòng nhập lý do sửa kết quả.', 'absent_entry_id.required' => 'Chọn bên vắng mặt.'];
    }

    /** @return array<string, mixed> */
    public function outcome(): array
    {
        return array_filter($this->safe()->only(['absent_entry_id', 'games', 'goals', 'shootout']), fn ($value): bool => $value !== null);
    }
}
