<?php

namespace App\Domain\Tournament\Rules;

use App\Models\Tournament;
use Illuminate\Validation\Rule;

/**
 * FR-120, BR-149: what a tournament's setup must hold. Shared by the public and internal setup requests; a public
 * tournament also needs capacity, fee and dates, and every descriptive field (eligibility, prizes).
 */
final class SetupRules
{
    /** @return array<string, mixed> */
    public static function rules(bool $public): array
    {
        $publicOnly = $public ? 'required' : 'prohibited';

        return [
            'name' => ['required', 'string', 'max:180'],
            'sport' => ['required', Rule::in(Tournament::SPORTS)],
            'entry_type' => ['required', Rule::in(Tournament::ENTRY_TYPES)],
            'team_main' => ['nullable', 'required_if:entry_type,team', 'integer', 'between:2,30'],
            'team_reserve' => ['nullable', 'required_if:entry_type,team', 'integer', 'between:0,20'],
            'format' => ['required', Rule::in([Tournament::FORMAT_GROUP, Tournament::FORMAT_KNOCKOUT])],
            'group_count' => ['nullable', 'required_if:format,group', 'integer', 'between:1,16'],
            'advance_per_group' => ['nullable', 'required_if:format,group', 'integer', 'between:0,16'],
            'games_to_win' => ['nullable', 'required_if:sport,badminton', 'integer', 'between:1,3'],
            'points' => ['required', 'array:win,draw,loss'],
            'points.win' => ['required', 'integer', 'between:0,10'],
            'points.draw' => ['required', 'integer', 'between:0,10'],
            'points.loss' => ['required', 'integer', 'between:0,10'],
            'tiebreakers' => ['present', 'array', 'max:3'],
            'tiebreakers.*' => ['distinct', Rule::in(Tournament::TIEBREAKERS)],
            'rules' => ['required', 'string', 'max:5000'],
            'eligibility' => [$public ? 'required' : 'nullable', 'string', 'max:2000'],
            'athlete_fields' => ['present', 'array', 'max:10'],
            'athlete_fields.*' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'forfeit_rule' => ['required', 'string', 'max:2000'],
            'prizes' => [$public ? 'required' : 'nullable', 'string', 'max:2000'],
            'max_entries' => [$publicOnly, 'integer', 'between:2,128'],
            'fee' => [$publicOnly, 'integer', 'between:1000,100000000'],
            'registration_deadline' => [$publicOnly, 'date'],
            'payment_deadline' => [$publicOnly, 'date'],
            'starts_at' => [$publicOnly, 'date'],
            'ends_at' => [$publicOnly, 'date', 'after:starts_at'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên giải.',
            'sport.in' => 'Giải đấu chỉ có cầu lông hoặc bóng đá.',
            'team_main.required_if' => 'Thi đấu đồng đội cần số người chính thức.',
            'team_reserve.required_if' => 'Thi đấu đồng đội cần số người dự bị tối đa.',
            'group_count.required_if' => 'Vòng bảng cần số bảng.',
            'advance_per_group.required_if' => 'Vòng bảng cần số đội đi tiếp mỗi bảng (0 nếu chỉ xếp hạng).',
            'games_to_win.required_if' => 'Cầu lông cần số ván thắng.',
            'rules.required' => 'Vui lòng nhập thể lệ.',
            'eligibility.required' => 'Vui lòng nhập điều kiện tham gia.',
            'forfeit_rule.required' => 'Vui lòng nhập quy định xử thua.',
            'prizes.required' => 'Vui lòng nhập giải thưởng.',
            'max_entries.required' => 'Vui lòng nhập số suất tối đa.',
            'fee.required' => 'Vui lòng nhập lệ phí.',
            'fee.between' => 'Lệ phí từ 1.000đ.',
            'ends_at.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
            '*.prohibited' => 'Giải nội bộ không có lệ phí, số suất hay hạn đăng ký.',
        ];
    }
}
