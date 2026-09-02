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
                    'court' => 'Sân Cầu Lông Aura Q7',
                    'gateway' => 'VietQR Pro (MBBank)',
                    'expected_amount' => 2000000,
                    'actual_amount' => 4500000,
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
                    'court' => 'Sân Cầu Lông Aura Phú Nhuận',
                    'gateway' => 'VietQR (Napas 247)',
                    'expected_amount' => 12000000,
                    'actual_amount' => 11000000,
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
                    'court' => 'Sân Cầu Lông Aura Tân Bình',
                    'gateway' => 'Ví Điện Tử MoMo',
                    'expected_amount' => 2150000,
                    'actual_amount' => 2150000,
                ],
            ],
            [
                'reference' => 'TXN-88288D',
                'type' => 'voucher_hunting',
                'severity' => 'critical',
                'status' => 'open',
                'amount' => 50000000,
                'currency' => 'VND',
                'description' => 'Bất thường Nạp/Rút: Nghi vấn tạo lệnh ảo săn voucher',
                'detected_at' => $now->copy()->subHours(8),
                'due_at' => $now->copy()->addHours(16),
                'metadata' => [
                    'shift' => 'Ca Chiều (14:00 - 22:00)',
                    'user' => ['name' => 'Nguyễn Hoàng Nam', 'id' => 'UID-8831', 'tier' => 'Tài khoản mới'],
                    'reason' => 'Bất thường nạp rút liên tiếp',
                    'court' => 'Sân Cầu Lông Aura Q7',
                    'gateway' => 'Chuyển khoản VCB',
                    'expected_amount' => 0,
                    'actual_amount' => 50000000,
                ],
            ],
            [
                'reference' => 'TXN-88287E',
                'type' => 'deposit_hold_mismatch',
                'severity' => 'medium',
                'status' => 'open',
                'amount' => 500000,
                'currency' => 'VND',
                'description' => 'Lệch cọc giữ chỗ: Timeout cổng thanh toán giữ sân',
                'detected_at' => $now->copy()->subHours(10),
                'due_at' => $now->copy()->addHours(14),
                'metadata' => [
                    'shift' => 'Ca Tối (22:00 - 06:00)',
                    'user' => ['name' => 'Đỗ Tuấn Kiệt', 'id' => 'UID-512', 'tier' => 'Hội viên Bạc'],
                    'reason' => 'Lệch tiền cọc giữ sân cuối tuần',
                    'court' => 'Sân Cầu Lông Aura Bình Thạnh',
                    'gateway' => 'ZaloPay Gateway',
                    'expected_amount' => 500000,
                    'actual_amount' => 0,
                ],
            ],
            [
                'reference' => 'TXN-88286F',
                'type' => 'duplicate_charge',
                'severity' => 'low',
                'status' => 'open',
                'amount' => 1200000,
                'currency' => 'VND',
                'description' => 'Trùng lặp giao dịch quét mã QR Momo 2 lần liên tiếp',
                'detected_at' => $now->copy()->subHours(12),
                'due_at' => $now->copy()->addHours(12),
                'metadata' => [
                    'shift' => 'Ca Sáng (06:00 - 14:00)',
                    'user' => ['name' => 'Phạm Thu Hà', 'id' => 'UID-330', 'tier' => 'Hội viên Vàng'],
                    'reason' => 'Trùng lặp quét mã 2 lần',
                    'court' => 'Sân Cầu Lông Aura Q7',
                    'gateway' => 'MoMo QR',
                    'expected_amount' => 600000,
                    'actual_amount' => 1200000,
                ],
            ],
            [
                'reference' => 'TXN-88285G',
                'type' => 'court_flooded_refund',
                'severity' => 'low',
                'status' => 'resolved',
                'amount' => 3600000,
                'currency' => 'VND',
                'description' => 'Hoàn tiền do sự cố ngập sân đột xuất tại cụm sân Bình Thạnh',
                'detected_at' => $now->copy()->subHours(16),
                'due_at' => $now->copy()->addHours(8),
                'resolved_at' => $now->copy()->subHours(1),
                'resolution' => 'Đã can thiệp hoàn tiền vào ví hội viên do sự cố bất khả kháng ngập sân.',
                'metadata' => [
                    'shift' => 'Ca Tối (18:00 - 22:00)',
                    'user' => ['name' => 'Hoàng Văn Thái', 'id' => 'UID-704', 'tier' => 'Hội viên Kim Cương'],
                    'reason' => 'Sự cố thời tiết ngập sân',
                    'court' => 'Sân Cầu Lông Aura Bình Thạnh',
                    'gateway' => 'Ví Aura Pay',
                    'expected_amount' => 3600000,
                    'actual_amount' => 3600000,
                ],
            ],
        ];

        $allOrgs = Organization::all();
        if ($allOrgs->isEmpty() && $org) {
            $allOrgs = collect([$org]);
        }

        foreach ($allOrgs as $currentOrg) {
            foreach ($financeExceptions as $fEx) {
                FinanceException::updateOrCreate(
                    [
                        'reference' => $fEx['reference'],
                        'organization_id' => $currentOrg->id,
                    ],
                    array_merge($fEx, [
                        'organization_id' => $currentOrg->id,
                    ])
                );
            }
        }

        // 2b. Audit Logs for Immutable Ledger
        $adminUser = \App\Models\User::where('email', 'like', '%admin%')->first() ?? \App\Models\User::first();
        $auditLogs = [
            [
                'user_id' => $adminUser?->id,
                'action' => 'finance_exception.resolve',
                'auditable_type' => FinanceException::class,
                'auditable_id' => 1,
                'ip_address' => '118.69.182.45',
                'user_agent' => 'Aura-Admin-Client/2.0',
                'metadata' => [
                    'action_type' => 'Duyệt hoàn tiền',
                    'reference' => 'TXN-99412B',
                    'amount' => 3600000,
                    'author_name' => 'Admin_Sarah',
                    'author_role' => 'Risk Lead',
                    'resolution' => 'Đã can thiệp hoàn tiền cho TXN-99412B do sự cố ngập sân tại Bình Thạnh.',
                    'audit_hash' => '0x8F92A1B4',
                    'type' => 'override',
                ],
                'created_at' => $now->copy()->subMinutes(25),
            ],
            [
                'user_id' => $adminUser?->id,
                'action' => 'finance_exception.flag',
                'auditable_type' => FinanceException::class,
                'auditable_id' => 2,
                'ip_address' => '118.69.182.45',
                'user_agent' => 'Aura-Admin-Client/2.0',
                'metadata' => [
                    'action_type' => 'Gắn cờ cảnh báo',
                    'reference' => 'UID-8831',
                    'amount' => 50000000,
                    'author_name' => 'Risk_Officer_Tuan',
                    'author_role' => 'Risk Analyst',
                    'resolution' => 'Gắn cờ UID-8831 do hành vi tạo lệnh nạp rút ảo săn voucher.',
                    'audit_hash' => '0x3B17C89E',
                    'type' => 'flag',
                ],
                'created_at' => $now->copy()->subMinutes(55),
            ],
            [
                'user_id' => null,
                'action' => 'finance_exception.block',
                'auditable_type' => FinanceException::class,
                'auditable_id' => 3,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'System-Sentinel/1.0',
                'metadata' => [
                    'action_type' => 'Tự động chặn',
                    'reference' => 'TXN-88288D',
                    'amount' => 50000000,
                    'author_name' => 'System_Sentinel',
                    'author_role' => 'Security Guard',
                    'resolution' => 'Tự động chặn giao dịch nạp 50.000.000 ₫ do vượt hạn mức ngày.',
                    'audit_hash' => '0x7D42E9F1',
                    'type' => 'block',
                ],
                'created_at' => $now->copy()->subHours(1)->subMinutes(15),
            ],
            [
                'user_id' => $adminUser?->id,
                'action' => 'finance_exception.unlock',
                'auditable_type' => FinanceException::class,
                'auditable_id' => 4,
                'ip_address' => '118.69.182.45',
                'user_agent' => 'Aura-Admin-Client/2.0',
                'metadata' => [
                    'action_type' => 'Mở khóa hoàn cọc',
                    'reference' => 'TXN-88287E',
                    'amount' => 500000,
                    'author_name' => 'Admin_Duc',
                    'author_role' => 'Operations Lead',
                    'resolution' => 'Mở khóa hoàn cọc 500.000 ₫ sau khi xác minh biên lai ngân hàng.',
                    'audit_hash' => '0x12A9F450',
                    'type' => 'unlock',
                ],
                'created_at' => $now->copy()->subHours(2)->subMinutes(10),
            ],
        ];

        foreach ($auditLogs as $log) {
            \App\Models\AuditLog::firstOrCreate(
                [
                    'action' => $log['action'],
                    'auditable_id' => $log['auditable_id'],
                    'metadata->audit_hash' => $log['metadata']['audit_hash'],
                ],
                $log
            );
        }

        // 3. Multi-Day & Multi-Channel Reconciliation Runs
        $reconRuns = [
            [
                'run_date' => $now->toDateString(),
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
            ],
            [
                'run_date' => $now->copy()->subDays(1)->toDateString(),
                'status' => 'matched',
                'provider_total' => 789400000,
                'journal_total' => 789400000,
                'settlement_total' => 789400000,
                'payout_total' => 295000000,
                'mismatch_amount' => 0,
                'started_at' => $now->copy()->subDays(1)->setTime(6, 0),
                'completed_at' => $now->copy()->subDays(1)->setTime(6, 15),
                'details' => [
                    'channel' => 'MoMo Business',
                    'txnCode' => 'TXN-88288D',
                    'riskLevel' => 'low',
                    'statusLabel' => 'Đã Khớp 100%',
                    'reason' => 'Tất cả 1,420 giao dịch khớp hoàn toàn với sổ cái.',
                    'court' => 'Sân Cầu Lông Aura Tân Bình',
                    'customer' => 'Nguyễn Hoàng Nam (UID-8831)',
                ],
            ],
            [
                'run_date' => $now->copy()->subDays(2)->toDateString(),
                'status' => 'matched',
                'provider_total' => 912000000,
                'journal_total' => 912000000,
                'settlement_total' => 912000000,
                'payout_total' => 340000000,
                'mismatch_amount' => 0,
                'started_at' => $now->copy()->subDays(2)->setTime(6, 0),
                'completed_at' => $now->copy()->subDays(2)->setTime(6, 12),
                'details' => [
                    'channel' => 'VNPay QR',
                    'txnCode' => 'TXN-88285C',
                    'riskLevel' => 'low',
                    'statusLabel' => 'Đã Khớp 100%',
                    'reason' => 'Khớp đối soát tự động chu kỳ T+1.',
                    'court' => 'Sân Cầu Lông Aura Bình Thạnh',
                    'customer' => 'Phạm Thu Hà (UID-330)',
                ],
            ],
            [
                'run_date' => $now->copy()->subDays(3)->toDateString(),
                'status' => 'mismatch',
                'provider_total' => 654000000,
                'journal_total' => 654000000,
                'settlement_total' => 653990000,
                'payout_total' => 240000000,
                'mismatch_amount' => 10000,
                'started_at' => $now->copy()->subDays(3)->setTime(6, 0),
                'completed_at' => $now->copy()->subDays(3)->setTime(6, 20),
                'details' => [
                    'channel' => 'ZaloPay',
                    'txnCode' => 'TXN-88279B',
                    'riskLevel' => 'medium',
                    'statusLabel' => 'Lệch Làm Tròn Phí',
                    'reason' => 'Chênh lệch 10.000 ₫ do làm tròn phí đối tác cổng thanh toán.',
                    'court' => 'Sân Cầu Lông Aura Phú Thọ',
                    'customer' => 'Đỗ Tuấn Kiệt (UID-512)',
                ],
            ],
            [
                'run_date' => $now->copy()->subDays(4)->toDateString(),
                'status' => 'matched',
                'provider_total' => 820000000,
                'journal_total' => 820000000,
                'settlement_total' => 820000000,
                'payout_total' => 310000000,
                'mismatch_amount' => 0,
                'started_at' => $now->copy()->subDays(4)->setTime(6, 0),
                'completed_at' => $now->copy()->subDays(4)->setTime(6, 14),
                'details' => [
                    'channel' => 'VietQR Pro (MBBank)',
                    'txnCode' => 'TXN-88264A',
                    'riskLevel' => 'low',
                    'statusLabel' => 'Đã Khớp 100%',
                    'reason' => 'Khớp đối chiếu sao kê ngân hàng MT940.',
                    'court' => 'Sân Cầu Lông Aura Q7',
                    'customer' => 'Lê Hoàng Long (UID-102)',
                ],
            ],
            [
                'run_date' => $now->copy()->subDays(5)->toDateString(),
                'status' => 'matched',
                'provider_total' => 745000000,
                'journal_total' => 745000000,
                'settlement_total' => 745000000,
                'payout_total' => 280000000,
                'mismatch_amount' => 0,
                'started_at' => $now->copy()->subDays(5)->setTime(6, 0),
                'completed_at' => $now->copy()->subDays(5)->setTime(6, 10),
                'details' => [
                    'channel' => 'Stripe',
                    'txnCode' => 'TXN-88250X',
                    'riskLevel' => 'low',
                    'statusLabel' => 'Đã Khớp 100%',
                    'reason' => 'Thẻ quốc tế Visa/Mastercard đã quyết toán về VCB.',
                    'court' => 'Sân Cầu Lông Aura Tân Bình',
                    'customer' => 'Alex Lawson (UID-402)',
                ],
            ],
            [
                'run_date' => $now->copy()->subDays(6)->toDateString(),
                'status' => 'matched',
                'provider_total' => 690000000,
                'journal_total' => 690000000,
                'settlement_total' => 690000000,
                'payout_total' => 260000000,
                'mismatch_amount' => 0,
                'started_at' => $now->copy()->subDays(6)->setTime(6, 0),
                'completed_at' => $now->copy()->subDays(6)->setTime(6, 11),
                'details' => [
                    'channel' => 'VietQR Pro (MBBank)',
                    'txnCode' => 'TXN-88241Y',
                    'riskLevel' => 'low',
                    'statusLabel' => 'Đã Khớp 100%',
                    'reason' => 'Khớp đối soát tự động.',
                    'court' => 'Sân Cầu Lông Aura Q7',
                    'customer' => 'Vũ Minh Tuấn (UID-319)',
                ],
            ],
        ];

        foreach ($reconRuns as $rRun) {
            ReconciliationRun::updateOrCreate(
                ['organization_id' => $org->id, 'run_date' => $rRun['run_date']],
                $rRun
            );
        }

        // 4. Beneficiaries, Settlements, and Payouts
        $allOrgs = Organization::whereIn('id', [1, 2])->get();
        if ($allOrgs->isEmpty()) {
            $allOrgs = collect([$org]);
        }

        foreach ($allOrgs as $currentOrg) {
            $beneficiary = \App\Models\Beneficiary::updateOrCreate(
                ['organization_id' => $currentOrg->id, 'account_number' => '0071000888999'],
                [
                    'provider' => 'payos',
                    'provider_beneficiary_id' => 'ben_vcb_' . $currentOrg->id,
                    'bank_code' => 'VCB',
                    'account_holder' => 'CONG TY CP THE THAO AURA',
                    'tax_id' => '0317892110',
                    'status' => 'verified',
                    'verified_at' => $now->copy()->subMonths(1),
                    'verification_payload' => ['napas_verified' => true, 'score' => 100],
                ]
            );

            // Settlement 1 (Settled / Eligible in the past)
            $settlement1 = \App\Models\Settlement::firstOrCreate(
                [
                    'organization_id' => $currentOrg->id,
                    'period_start' => $now->copy()->subDays(30)->toDateString(),
                    'period_end' => $now->copy()->subDays(16)->toDateString(),
                ],
                [
                    'eligible_at' => $now->copy()->subDays(14),
                    'gross_amount' => 45000000,
                    'refund_amount' => 0,
                    'provider_fee_amount' => 900000,
                    'commission_amount' => 1350000,
                    'tenant_payable' => 42750000,
                    'currency' => 'VND',
                    'status' => 'eligible',
                    'snapshot' => [
                        'gross_amount' => 45000000,
                        'refund_amount' => 0,
                        'provider_fee_amount' => 900000,
                        'commission_amount' => 1350000,
                        'tenant_payable' => 42750000,
                        'captured_at' => $now->copy()->subDays(14)->toIso8601String(),
                    ],
                    'snapshotted_at' => $now->copy()->subDays(14),
                ]
            );

            // Settlement 2 (Eligible recently)
            $settlement2 = \App\Models\Settlement::firstOrCreate(
                [
                    'organization_id' => $currentOrg->id,
                    'period_start' => $now->copy()->subDays(15)->toDateString(),
                    'period_end' => $now->copy()->subDays(1)->toDateString(),
                ],
                [
                    'eligible_at' => $now->copy()->addDay(),
                    'gross_amount' => 58500000,
                    'refund_amount' => 500000,
                    'provider_fee_amount' => 1170000,
                    'commission_amount' => 1755000,
                    'tenant_payable' => 55075000,
                    'currency' => 'VND',
                    'status' => 'eligible',
                    'snapshot' => [
                        'gross_amount' => 58500000,
                        'refund_amount' => 500000,
                        'provider_fee_amount' => 1170000,
                        'commission_amount' => 1755000,
                        'tenant_payable' => 55075000,
                        'captured_at' => $now->copy()->subDays(1)->toIso8601String(),
                    ],
                    'snapshotted_at' => $now->copy()->subDays(1),
                ]
            );

            // Payout 1: Paid
            \App\Models\Payout::updateOrCreate(
                [
                    'organization_id' => $currentOrg->id,
                    'idempotency_key' => "settlement:{$settlement1->id}:payout",
                ],
                [
                    'settlement_id' => $settlement1->id,
                    'beneficiary_id' => $beneficiary->id,
                    'amount' => $settlement1->tenant_payable,
                    'currency' => 'VND',
                    'provider' => 'payos',
                    'provider_payout_id' => 'pout_vcb_001_' . $currentOrg->id,
                    'status' => 'paid',
                    'settlement_snapshot' => $settlement1->snapshot,
                    'approved_at' => $now->copy()->subDays(13),
                    'submitted_at' => $now->copy()->subDays(13)->addMinutes(10),
                    'paid_at' => $now->copy()->subDays(13)->addMinutes(30),
                ]
            );

            // Payout 2: Approved
            \App\Models\Payout::updateOrCreate(
                [
                    'organization_id' => $currentOrg->id,
                    'idempotency_key' => "settlement:{$settlement2->id}:payout",
                ],
                [
                    'settlement_id' => $settlement2->id,
                    'beneficiary_id' => $beneficiary->id,
                    'amount' => $settlement2->tenant_payable,
                    'currency' => 'VND',
                    'provider' => 'payos',
                    'provider_payout_id' => null,
                    'status' => 'approved',
                    'settlement_snapshot' => $settlement2->snapshot,
                    'approved_at' => $now->copy()->subHours(5),
                    'submitted_at' => null,
                    'paid_at' => null,
                ]
            );
        }
    }
}
