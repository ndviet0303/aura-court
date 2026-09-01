<?php

namespace Database\Seeders;

use App\Models\DisputeCase;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\ReconciliationRun;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DisputeFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $org = Organization::where('slug', 'aura-badminton-club')->first() ?? Organization::first();
        if (! $org) {
            return;
        }

        // 1. Dispute Cases
        $disputeCases = [
            [
                'dispute_code' => 'DSP-8924',
                'court_name' => 'Sân số 4 (Phú Thọ)',
                'dispute_type' => 'double_booking',
                'severity' => 'critical',
                'title' => 'Xung đột Trùng Lịch Sân số 4',
                'summary' => 'Hệ thống ghi nhận đặt qua App của Alex Lawson và nhân viên quầy chèn tay lịch của Trần Quốc Bảo lúc 19:00.',
                'status' => 'investigating',
                'sla_deadline' => $now->copy()->addMinutes(15),
                'amount_disputed' => 360000,
                'currency' => 'VND',
                'complainant_name' => 'Alex Lawson',
                'complainant_tier' => 'Hạng Vàng',
                'complainant_reputation_score' => 98,
                'complainant_phone' => '0909 *** 892',
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
                        'description' => 'Alex Lawson thanh toán thành công đơn qua PayOS (Slot 19:00 - 21:00 Sân 4).',
                        'type' => 'primary',
                    ],
                    [
                        'time' => '20 Thg 10, 18:45:22',
                        'title' => 'Cảnh báo: Độ trễ Webhook Facility API',
                        'description' => 'Độ trễ đồng bộ 45 giây từ đối tác khiến trạng thái slot chưa cập nhật kịp thời trên bảng lễ tân.',
                        'type' => 'warning',
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
            ],
            [
                'dispute_code' => 'DSP-8921',
                'court_name' => 'Sân Tân Bình Sport - Court 2',
                'dispute_type' => 'match_stake',
                'severity' => 'high',
                'title' => 'Tranh chấp Tiền cọc Kèo Giao Lưu Matchmaking',
                'summary' => 'Đối thủ đơn nam tự ý bỏ cuộc giữa chừng ở Set 3 nhưng không chịu xác nhận hủy cọc trên ví nội bộ.',
                'status' => 'pending',
                'sla_deadline' => $now->copy()->addHours(2),
                'amount_disputed' => 200000,
                'currency' => 'VND',
                'complainant_name' => 'Vũ Minh Tuấn',
                'complainant_tier' => 'Hạng Bạc',
                'complainant_reputation_score' => 92,
                'complainant_phone' => '0938 *** 112',
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
            ],
        ];

        foreach ($disputeCases as $dispData) {
            DisputeCase::updateOrCreate(
                ['dispute_code' => $dispData['dispute_code']],
                array_merge($dispData, [
                    'organization_id' => $org->id,
                ])
            );
        }

        // 2. Finance Exceptions
        $financeExceptions = [
            [
                'reference' => 'TXN-88291A',
                'type' => 'limit_exceeded',
                'severity' => 'critical',
                'status' => 'open',
                'amount' => 4500000,
                'currency' => 'VND',
                'description' => 'Vượt hạn mức nạp 24h từ tài khoản hội viên Vàng',
                'detected_at' => $now->copy()->subHours(3),
                'due_at' => $now->copy()->addHours(21),
                'metadata' => [
                    'shift' => 'Ca Sáng (06:00 - 14:00)',
                    'user' => ['name' => 'Lê Văn B.', 'id' => 'UID-442', 'tier' => 'Hội viên Vàng'],
                    'reason' => 'Vượt hạn mức nạp 24h',
                ],
            ],
            [
                'reference' => 'TXN-88290B',
                'type' => 'settlement_mismatch',
                'severity' => 'medium',
                'status' => 'open',
                'amount' => 12000000,
                'currency' => 'VND',
                'description' => 'Lệch đối soát VietQR giữa cổng thanh toán và sổ cái',
                'detected_at' => $now->copy()->subHours(4),
                'due_at' => $now->copy()->addHours(20),
                'metadata' => [
                    'shift' => 'Ca Sáng (06:00 - 14:00)',
                    'user' => ['name' => 'Trần Thị C.', 'id' => 'UID-119', 'tier' => 'Khách vãng lai'],
                    'reason' => 'Lệch đối soát VietQR',
                ],
            ],
            [
                'reference' => 'TXN-88289C',
                'type' => 'late_refund',
                'severity' => 'low',
                'status' => 'open',
                'amount' => 2150000,
                'currency' => 'VND',
                'description' => 'Hoàn tiền hủy sân quá 48h',
                'detected_at' => $now->copy()->subHours(6),
                'due_at' => $now->copy()->addHours(18),
                'metadata' => [
                    'shift' => 'Ca Sáng (06:00 - 14:00)',
                    'user' => ['name' => 'Minh Quân', 'id' => 'UID-992', 'tier' => 'Hội viên Kim Cương'],
                    'reason' => 'Hoàn tiền hủy sân quá 48h',
                ],
            ],
        ];

        foreach ($financeExceptions as $fEx) {
            FinanceException::updateOrCreate(
                ['reference' => $fEx['reference']],
                array_merge($fEx, [
                    'organization_id' => $org->id,
                ])
            );
        }

        // 3. Reconciliation Run
        ReconciliationRun::updateOrCreate(
            ['organization_id' => $org->id, 'run_date' => $now->toDateString()],
            [
                'status' => 'mismatch',
                'provider_total' => 842560000,
                'journal_total' => 842560000,
                'settlement_total' => 841160000,
                'payout_total' => 318400000,
                'mismatch_amount' => 1400000,
                'started_at' => $now->copy()->subHours(1),
                'completed_at' => $now,
                'details' => [
                    'channel' => 'VietQR Pro (MBBank)',
                    'txnCode' => 'TXN-88291A',
                    'riskLevel' => 'high',
                    'statusLabel' => 'Lệch Quyết Toán',
                    'reason' => 'Webhook Napas delay lúc 06:42:15, tài khoản trung gian chưa ghi có settlement.',
                    'court' => 'Sân Cầu Lông Aura Q7 - Sân VIP 1',
                    'customer' => 'Trần Minh Quang (UID-8821)',
                ],
            ]
        );
    }
}
