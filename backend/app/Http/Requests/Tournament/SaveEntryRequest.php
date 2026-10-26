<?php

namespace App\Http\Requests\Tournament;

use App\Models\TournamentEntryMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** FR-124, BR-150: a unit's name and its athletes (full name, birth date, the tournament's extra fields, role). */
class SaveEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'venue_credit_amount' => ['prohibited'],
            'members' => ['required', 'array', 'min:1', 'max:50'],
            'members.*.full_name' => ['required', 'string', 'max:120'],
            'members.*.birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'members.*.extra' => ['nullable', 'array'],
            'members.*.extra.*' => ['nullable', 'string', 'max:255'],
            'members.*.role' => ['required', Rule::in([TournamentEntryMember::ROLE_MAIN, TournamentEntryMember::ROLE_RESERVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên đơn vị.',
            'members.required' => 'Cần ít nhất một vận động viên.',
            'members.*.full_name.required' => 'Vui lòng nhập họ tên vận động viên.',
            'members.*.birth_date.required' => 'Vui lòng nhập ngày sinh.',
            'members.*.birth_date.date_format' => 'Ngày sinh không hợp lệ.',
            'members.*.birth_date.before' => 'Ngày sinh phải ở quá khứ.',
        ];
    }
}
