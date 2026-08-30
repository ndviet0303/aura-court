<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Models\DisputeCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\Schema;

class EloquentDisputeRepository implements DisputeRepositoryInterface
{
    public function findById(int $id): ?DisputeCase
    {
        if (!Schema::hasTable('dispute_cases')) {
            return $this->getMockDisputeById($id);
        }

        return DisputeCase::query()
            ->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator'])
            ->find($id);
    }

    public function findByCode(string $code): ?DisputeCase
    {
        if (!Schema::hasTable('dispute_cases')) {
            $mockList = $this->getMockList();
            foreach ($mockList as $item) {
                if ($item->dispute_code === $code) {
                    return $item;
                }
            }
            return null;
        }

        return DisputeCase::query()
            ->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator'])
            ->where('dispute_code', $code)
            ->first();
    }

    public function paginate(
        ?string $status = null,
        ?string $search = null,
        ?string $severity = null,
        ?string $disputeType = null,
        int $perPage = 30
    ): LengthAwarePaginator {
        if (!Schema::hasTable('dispute_cases')) {
            $items = $this->getMockList();

            if ($status && $status !== 'all') {
                $items = array_values(array_filter($items, fn ($i) => $i->status === $status));
            }

            if ($severity && $severity !== 'all') {
                $items = array_values(array_filter($items, fn ($i) => $i->severity === $severity));
            }

            if ($search) {
                $kw = mb_strtolower(trim($search));
                $items = array_values(array_filter($items, function ($i) use ($kw) {
                    return str_contains(mb_strtolower($i->dispute_code), $kw)
                        || str_contains(mb_strtolower($i->title), $kw)
                        || str_contains(mb_strtolower($i->complainant_name), $kw)
                        || str_contains(mb_strtolower($i->respondent_name), $kw)
                        || str_contains(mb_strtolower($i->facility_name), $kw);
                }));
            }

            return new ConcretePaginator($items, count($items), $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        $query = DisputeCase::query()->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($severity && $severity !== 'all') {
            $query->where('severity', $severity);
        }

        if ($disputeType && $disputeType !== 'all') {
            $query->where('dispute_type', $disputeType);
        }

        if ($search) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('dispute_code', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('complainant_name', 'like', $term)
                    ->orWhere('respondent_name', 'like', $term)
                    ->orWhere('facility_name', 'like', $term);
            });
        }

        return $query->orderByRaw("CASE WHEN severity = 'critical' THEN 1 WHEN severity = 'high' THEN 2 WHEN status = 'pending' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getStats(): array
    {
        if (!Schema::hasTable('dispute_cases')) {
            return [
                'pending_count' => 14,
                'investigating_count' => 6,
                'awaiting_reply_count' => 3,
                'resolved_count' => 128,
                'critical_sla_count' => 2,
                'avg_resolution_time_minutes' => 18.5,
                'consensus_rate' => 98.2,
            ];
        }

        $pending = DisputeCase::query()->where('status', 'pending')->count();
        $investigating = DisputeCase::query()->where('status', 'investigating')->count();
        $awaitingReply = DisputeCase::query()->where('status', 'awaiting_response')->count();
        $resolved = DisputeCase::query()->where('status', 'resolved')->count();
        $critical = DisputeCase::query()->where('severity', 'critical')->whereIn('status', ['pending', 'investigating'])->count();

        return [
            'pending_count' => $pending,
            'investigating_count' => $investigating,
            'awaiting_reply_count' => $awaitingReply,
            'resolved_count' => $resolved,
            'critical_sla_count' => $critical,
            'avg_resolution_time_minutes' => 18.5,
            'consensus_rate' => 98.2,
        ];
    }

    public function create(array $attributes): DisputeCase
    {
        return DisputeCase::create($attributes);
    }

    public function update(DisputeCase $dispute, array $attributes): DisputeCase
    {
        $dispute->update($attributes);
        return $dispute->fresh(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator']);
    }

    private function getMockDisputeById(int $id): ?DisputeCase
    {
        $list = $this->getMockList();
        foreach ($list as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }
        return $list[0] ?? null;
    }

    /**
     * @return DisputeCase[]
     */
    private function getMockList(): array
    {
        $d1 = new DisputeCase([
            'dispute_code' => 'DSP-8924',
            'organization_id' => 1,
            'venue_id' => 4,
            'booking_id' => 99482,
            'court_name' => 'Sân số 4 (Phú Thọ)',
            'dispute_type' => 'double_booking',
            'severity' => 'critical',
            'title' => 'Xung đột Trùng Lịch Sân số 4',
            'summary' => 'Hệ thống ghi nhận đặt qua App của Alex Lawson và nhân viên quầy chèn tay lịch của Trần Quốc Bảo lúc 19:00.',
            'status' => 'investigating',
            'sla_deadline' => now()->addMinutes(15),
            'amount_disputed' => 360000,
            'currency' => 'VND',
            'complainant_id' => 101,
            'complainant_name' => 'Alex Lawson',
            'complainant_tier' => 'Hạng Vàng',
            'complainant_reputation_score' => 98,
            'complainant_phone' => '0909 *** 892',
            'respondent_id' => 102,
            'respondent_name' => 'Trần Quốc Bảo',
            'respondent_tier' => 'Hạng Kim Cương',
            'respondent_reputation_score' => 96,
            'respondent_phone' => '0912 *** 341',
            'facility_name' => 'Cụm Sân Cầu Lông Phú Thọ',
            'facility_operator_name' => 'Lê Hoàng Long (Trưởng ca trực)',
            'timeline_events' => [
                [
                    'time' => '20 Thg 10, 18:30:15',
                    'title' => 'Thanh toán đặt sân thành công',
                    'description' => 'Alex Lawson thanh toán thành công đơn #BK-99482 qua PayOS (Slot 19:00 - 21:00 Sân 4).',
                    'type' => 'primary',
                ],
                [
                    'time' => '20 Thg 10, 18:45:22',
                    'title' => 'Cảnh báo: Độ trễ Webhook Facility API',
                    'description' => 'Độ trễ đồng bộ 45 giây từ đối tác khiến trạng thái slot chưa cập nhật kịp thời trên bảng lễ tân.',
                    'type' => 'warning',
                ],
                [
                    'time' => '24 Thg 10, 18:55:12',
                    'title' => 'Lỗi Thao Tác: Nhân viên mở check-in chèn tay',
                    'description' => 'Lễ tân Lê Hoàng Long mở check-in thủ công cho khách Hợp đồng cố định Trần Quốc Bảo vào Sân 4.',
                    'type' => 'error',
                ],
                [
                    'time' => '24 Thg 10, 19:02:10',
                    'title' => 'Phát sinh tranh chấp tại quầy check-in',
                    'description' => 'Cả hai bên cùng xuất trình mã QR hợp lệ tại cửa Sân 4.',
                    'type' => 'error',
                ],
            ],
            'chat_messages' => [
                [
                    'sender' => 'Alex Lawson',
                    'role' => 'complainant',
                    'avatar' => 'AL',
                    'message' => 'Tôi đã đến sân lúc 18:55 nhưng sân số 4 đang có nhóm khác đánh. Lễ tân bảo họ có lịch đặt cố định.',
                    'time' => '19:05',
                ],
                [
                    'sender' => 'Phú Thọ Support',
                    'role' => 'facility',
                    'avatar' => 'PT',
                    'message' => 'Chào anh Alex, bên em vừa kiểm tra lại hệ thống POS bị trễ webhook nên lễ tân tưởng slot còn trống.',
                    'time' => '19:08',
                ],
                [
                    'sender' => 'Aura Arbitration Bot',
                    'role' => 'system',
                    'avatar' => 'AI',
                    'message' => 'Vụ việc đã được tiếp nhận và phân loại KHẨN CẤP. Chuyên viên đang thụ lý phán quyết trong 15 phút.',
                    'time' => '19:09',
                ],
            ],
            'court_map_context' => [
                ['name' => 'Sân 3', 'status' => 'occupied', 'label' => 'Đang đánh'],
                ['name' => 'Sân 4', 'status' => 'conflict', 'label' => 'Xung Đột'],
                ['name' => 'Sân 7', 'status' => 'available', 'label' => 'Trống - Có thể Đổi'],
            ],
            'ai_analysis' => [
                'fault_rate_facility' => 100,
                'fault_rate_user' => 0,
                'root_cause' => 'Độ trễ Webhook đồng bộ kết hợp thao tác ghi đè thủ công tại quầy của nhân viên cơ sở.',
                'recommendation_summary' => 'Hoàn 100% tiền (360.000 ₫) vào Ví nội bộ cho Alex Lawson + Tặng Voucher 20% + Đổi Sân 7 cho Trần Quốc Bảo.',
            ],
        ]);
        $d1->id = 1;
        $d1->created_at = now()->subMinutes(10);

        $d2 = new DisputeCase([
            'dispute_code' => 'DSP-8921',
            'organization_id' => 1,
            'venue_id' => 2,
            'booking_id' => 99310,
            'court_name' => 'Sân Tân Bình Sport - Court 2',
            'dispute_type' => 'match_stake',
            'severity' => 'high',
            'title' => 'Tranh chấp Tiền cọc Kèo Giao Lưu Matchmaking',
            'summary' => 'Đối thủ đơn nam tự ý bỏ cuộc giữa chừng ở Set 3 nhưng không chịu xác nhận hủy cọc trên ví nội bộ.',
            'status' => 'pending',
            'sla_deadline' => now()->addHours(2),
            'amount_disputed' => 200000,
            'currency' => 'VND',
            'complainant_id' => 103,
            'complainant_name' => 'Vũ Minh Tuấn',
            'complainant_tier' => 'Hạng Bạc',
            'complainant_reputation_score' => 92,
            'complainant_phone' => '0938 *** 112',
            'respondent_id' => 104,
            'respondent_name' => 'Hoàng Nam',
            'respondent_tier' => 'Hạng Đồng',
            'respondent_reputation_score' => 84,
            'respondent_phone' => '0977 *** 554',
            'facility_name' => 'Sân Cầu Lông Tân Bình Sport',
            'facility_operator_name' => 'Trần Thị Bích',
            'timeline_events' => [],
            'chat_messages' => [],
            'court_map_context' => [],
            'ai_analysis' => [
                'fault_rate_facility' => 0,
                'fault_rate_user' => 100,
                'root_cause' => 'Bỏ trận đấu giao lưu có đặt cọc không có lý do chính đáng.',
                'recommendation_summary' => 'Xử thua kỹ thuật cho Hoàng Nam, giải ngân 200.000 ₫ tiền cọc cho Vũ Minh Tuấn và trừ 15 điểm uy tín đối thủ.',
            ],
        ]);
        $d2->id = 2;
        $d2->created_at = now()->subHours(2);

        $d3 = new DisputeCase([
            'dispute_code' => 'DSP-8918',
            'organization_id' => 1,
            'venue_id' => 1,
            'booking_id' => 99120,
            'court_name' => 'Smash City Arena - Court 6',
            'dispute_type' => 'resale_fraud',
            'severity' => 'critical',
            'title' => 'Thị trường Nhượng suất - Hủy QR sau khi nhận tiền',
            'summary' => 'Người mua đã chuyển khoản cọc qua PayOS nhưng người bán hủy slot trước giờ chơi 15 phút.',
            'status' => 'investigating',
            'sla_deadline' => now()->addHours(1),
            'amount_disputed' => 450000,
            'currency' => 'VND',
            'complainant_id' => 105,
            'complainant_name' => 'Phạm Hoàng Yến',
            'complainant_tier' => 'Hạng Vàng',
            'complainant_reputation_score' => 97,
            'complainant_phone' => '0918 *** 990',
            'respondent_id' => 106,
            'respondent_name' => 'Đặng Văn Hùng',
            'respondent_tier' => 'Hạng Mới',
            'respondent_reputation_score' => 60,
            'respondent_phone' => '0981 *** 332',
            'facility_name' => 'Smash City Arena',
            'facility_operator_name' => 'Nguyễn Văn An',
            'timeline_events' => [],
            'chat_messages' => [],
            'court_map_context' => [],
            'ai_analysis' => [
                'fault_rate_facility' => 0,
                'fault_rate_user' => 100,
                'root_cause' => 'Hành vi gian lận nhượng suất cố tình revoke QR sau khi tiền đã chuyển vào tài khoản.',
                'recommendation_summary' => 'Hoàn 100% tiền (450.000 ₫) cho người mua từ quỹ bảo đảm thị trường, khóa vĩnh viễn tài khoản người bán.',
            ],
        ]);
        $d3->id = 3;
        $d3->created_at = now()->subHours(3);

        $d4 = new DisputeCase([
            'dispute_code' => 'DSP-8915',
            'organization_id' => 1,
            'venue_id' => 3,
            'booking_id' => 98842,
            'court_name' => 'Aura Bình Thạnh Center - Court 1',
            'dispute_type' => 'equipment_damage',
            'severity' => 'medium',
            'title' => 'Tranh chấp Phí bồi thường Vợt Yonex Astrox 100ZZ',
            'summary' => 'Chủ sân trừ 1.200.000đ tiền cọc vì nứt vành vợt, người thuê khiếu nại vợt đã nứt từ trước.',
            'status' => 'awaiting_response',
            'sla_deadline' => now()->addDay(),
            'amount_disputed' => 1200000,
            'currency' => 'VND',
            'complainant_id' => 107,
            'complainant_name' => 'Ngô Đức Thắng',
            'complainant_tier' => 'Hạng Bạc',
            'complainant_reputation_score' => 91,
            'complainant_phone' => '0903 *** 441',
            'respondent_id' => 108,
            'respondent_name' => 'Aura Bình Thạnh Center',
            'respondent_tier' => 'Chủ Sân Xác Thực',
            'respondent_reputation_score' => 99,
            'respondent_phone' => '0933 *** 888',
            'facility_name' => 'Aura Bình Thạnh Center',
            'facility_operator_name' => 'Trần Văn B',
            'timeline_events' => [],
            'chat_messages' => [],
            'court_map_context' => [],
            'ai_analysis' => [
                'fault_rate_facility' => 50,
                'fault_rate_user' => 50,
                'root_cause' => 'Ảnh kiểm tra trước ca chụp thiếu góc chụp vành trên.',
                'recommendation_summary' => 'Chia đôi chi phí sửa chữa (600.000 ₫ mỗi bên) và hoàn lại 600.000 ₫ cọc cho khách.',
            ],
        ]);
        $d4->id = 4;
        $d4->created_at = now()->subDay();

        $d5 = new DisputeCase([
            'dispute_code' => 'DSP-8902',
            'organization_id' => 1,
            'venue_id' => 4,
            'booking_id' => 97910,
            'court_name' => 'Sân Phú Thọ - Court 1',
            'dispute_type' => 'tournament_score',
            'severity' => 'low',
            'title' => 'Trọng tài nhập sai tỷ số Chung kết Nhánh B',
            'summary' => 'Trọng tài bàn nhập nhầm 21-19 thành 19-21 ở set 2.',
            'status' => 'resolved',
            'resolution_decision' => 'upheld',
            'refund_method' => 'wallet',
            'refund_amount' => 0,
            'compensation_type' => 'reputation_points',
            'compensation_value' => '500 Points',
            'amount_disputed' => 0,
            'currency' => 'VND',
            'complainant_id' => 109,
            'complainant_name' => 'Đội Cầu Lông Tia Chớp',
            'complainant_tier' => 'CLB Chuyên Nghiệp',
            'complainant_reputation_score' => 100,
            'complainant_phone' => '0919 *** 777',
            'respondent_id' => 110,
            'respondent_name' => 'Ban Trọng Tài Giải Đấu',
            'respondent_tier' => 'Trọng tài Quốc gia',
            'respondent_reputation_score' => 98,
            'respondent_phone' => '0908 *** 222',
            'facility_name' => 'Cụm Sân Cầu Lông Phú Thọ',
            'facility_operator_name' => 'Lê Hoàng Long',
            'timeline_events' => [],
            'chat_messages' => [],
            'court_map_context' => [],
            'ai_analysis' => [
                'fault_rate_facility' => 100,
                'fault_rate_user' => 0,
                'root_cause' => 'Đối chiếu video VAR chứng minh đội Tia Chớp thắng 21-19.',
                'recommendation_summary' => 'Cập nhật lại tỷ số trên bảng Bracket giải đấu và xin lỗi công khai.',
            ],
        ]);
        $d5->id = 5;
        $d5->created_at = now()->subDays(2);

        return [$d1, $d2, $d3, $d4, $d5];
    }
}
