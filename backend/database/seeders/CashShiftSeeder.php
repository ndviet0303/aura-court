<?php

namespace Database\Seeders;

use App\Models\CashShift;
use App\Models\Organization;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\ShiftReconciliation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CashShiftSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $org = Organization::where('slug', 'aura-badminton-club')->first() ?? Organization::first();
        if (! $org) {
            return;
        }

        $adminUser = User::where('role', 'admin')->first() ?? User::first();
        if (! $adminUser) {
            return;
        }

        // Active Open Shift (Today)
        $activeShift = CashShift::updateOrCreate(
            [
                'organization_id' => $org->id,
                'status' => 'open',
            ],
            [
                'cashier_id' => $adminUser->id,
                'opening_cash' => 1000000,
                'expected_cash' => 1000000,
                'declared_cash' => null,
                'variance' => null,
                'opened_at' => $now->copy()->startOfDay()->addHours(7),
                'closed_at' => null,
            ]
        );

        // Past Shift 1: Matched (Yesterday Evening)
        $shift1 = CashShift::updateOrCreate(
            [
                'organization_id' => $org->id,
                'opened_at' => $now->copy()->subDay()->startOfDay()->addHours(14),
            ],
            [
                'cashier_id' => $adminUser->id,
                'opening_cash' => 1000000,
                'expected_cash' => 2450000,
                'declared_cash' => 2450000,
                'variance' => 0,
                'status' => 'closed',
                'closed_at' => $now->copy()->subDay()->startOfDay()->addHours(22)->addMinutes(30),
            ]
        );
        ShiftReconciliation::updateOrCreate(
            ['cash_shift_id' => $shift1->id],
            [
                'organization_id' => $org->id,
                'variance' => 0,
                'status' => 'matched',
                'note' => 'Kiểm đếm khớp 100%, bàn giao két niêm phong đầy đủ.',
                'reviewed_by' => $adminUser->id,
                'reviewed_at' => $now->copy()->subDay()->startOfDay()->addHours(23),
            ]
        );

        // Past Shift 2: Approved Variance (2 Days Ago)
        $shift2 = CashShift::updateOrCreate(
            [
                'organization_id' => $org->id,
                'opened_at' => $now->copy()->subDays(2)->startOfDay()->addHours(6),
            ],
            [
                'cashier_id' => $adminUser->id,
                'opening_cash' => 1000000,
                'expected_cash' => 3120000,
                'declared_cash' => 3140000,
                'variance' => 20000,
                'status' => 'closed',
                'closed_at' => $now->copy()->subDays(2)->startOfDay()->addHours(14),
            ]
        );
        ShiftReconciliation::updateOrCreate(
            ['cash_shift_id' => $shift2->id],
            [
                'organization_id' => $org->id,
                'variance' => 20000,
                'status' => 'approved',
                'note' => 'Khách bo tiền lẻ thừa không lấy lại. Quản lý đã duyệt nhập quỹ dự phòng.',
                'reviewed_by' => $adminUser->id,
                'reviewed_at' => $now->copy()->subDays(2)->startOfDay()->addHours(15)->addMinutes(30),
            ]
        );

        // Past Shift 3: Shortage Exception (3 Days Ago)
        $shift3 = CashShift::updateOrCreate(
            [
                'organization_id' => $org->id,
                'opened_at' => $now->copy()->subDays(3)->startOfDay()->addHours(14),
            ],
            [
                'cashier_id' => $adminUser->id,
                'opening_cash' => 1000000,
                'expected_cash' => 4500000,
                'declared_cash' => 4450000,
                'variance' => -50000,
                'status' => 'closed',
                'closed_at' => $now->copy()->subDays(3)->startOfDay()->addHours(22),
            ]
        );
        ShiftReconciliation::updateOrCreate(
            ['cash_shift_id' => $shift3->id],
            [
                'organization_id' => $org->id,
                'variance' => -50000,
                'status' => 'exception',
                'note' => 'Thối nhầm tiền thừa cho khách mua ống cầu lông buổi tối. Đang chờ đối soát camera.',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        // Past Shift 4: Morning Matched (4 Days Ago)
        $shift4 = CashShift::updateOrCreate(
            [
                'organization_id' => $org->id,
                'opened_at' => $now->copy()->subDays(4)->startOfDay()->addHours(6),
            ],
            [
                'cashier_id' => $adminUser->id,
                'opening_cash' => 500000,
                'expected_cash' => 1850000,
                'declared_cash' => 1850000,
                'variance' => 0,
                'status' => 'closed',
                'closed_at' => $now->copy()->subDays(4)->startOfDay()->addHours(14),
            ]
        );
        ShiftReconciliation::updateOrCreate(
            ['cash_shift_id' => $shift4->id],
            [
                'organization_id' => $org->id,
                'variance' => 0,
                'status' => 'matched',
                'note' => 'Bàn giao ca sáng hoàn tất 100%.',
                'reviewed_by' => $adminUser->id,
                'reviewed_at' => $now->copy()->subDays(4)->startOfDay()->addHours(14)->addMinutes(15),
            ]
        );
    }
}
