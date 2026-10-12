<?php

namespace Database\Seeders;

use App\Models\CashShift;
use App\Models\Organization;
use App\Models\ShiftReconciliation;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * POS cash shifts at the venues where the POS staff work. Only roles allowed to open shifts appear
 * (cashier, venue manager, owner; never staff). Closed shifts start with zero sales: PosSalesSeeder adds
 * each shift's cash sales to expected/declared cash, so the counted variance below stays as reviewed.
 */
class CashShiftSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'))->startOfMinute();
        $today = $now->copy()->startOfDay();

        $shifts = [
            // [org slug, venue slug, cashier, opened day, open, close, opening cash, variance, status, close note, reviewer, review note]
            ['aura-badminton-club', 'aura-court', 'cashier@demo.auracourt.vn', $today->copy()->subMonths(13), '14:00', '22:00', 1000000, 0, 'matched', 'Bàn giao két đủ.', null, null],
            ['aura-badminton-club', 'aura-court', 'cashier@demo.auracourt.vn', $today->copy()->subDays(8), '14:00', '22:05', 1000000, 0, 'matched', 'Bàn giao két đủ, đã niêm phong.', null, null],
            ['aura-badminton-club', 'aura-court', 'cashier@demo.auracourt.vn', $today->copy()->subDays(7), '14:00', '22:10', 1000000, 0, 'matched', null, null, null],
            ['aura-badminton-club', 'aura-court', 'manager.caugiay@demo.auracourt.vn', $today->copy()->subDays(6), '14:00', '22:00', 1000000, 0, 'matched', 'Quản lý trực thay ca thu ngân nghỉ phép.', null, null],
            ['aura-badminton-club', 'aura-court', 'cashier@demo.auracourt.vn', $today->copy()->subDays(5), '14:00', '22:05', 1000000, 20000, 'approved', 'Thừa 20.000₫ do khách không lấy tiền thối.', 'manager.caugiay@demo.auracourt.vn', 'Đã duyệt: nhập quỹ lẻ của quầy.'],
            ['aura-badminton-club', 'aura-court', 'cashier@demo.auracourt.vn', $today->copy()->subDays(4), '14:00', '22:15', 1000000, 0, 'matched', null, null, null],
            ['aura-badminton-club', 'aura-court', 'cashier@demo.auracourt.vn', $today->copy()->subDays(3), '14:00', '22:00', 1000000, 0, 'matched', 'Bàn giao két đủ.', null, null],
            ['aura-badminton-club', 'aura-court', 'manager.caugiay@demo.auracourt.vn', $today->copy()->subDays(2), '14:00', '22:10', 1000000, 0, 'matched', null, null, null],
            ['aura-badminton-club', 'aura-court', 'cashier@demo.auracourt.vn', $today->copy()->subDay(), '14:00', '22:05', 1000000, -50000, 'exception', 'Thiếu 50.000₫ khi kiểm két cuối ca, đang đối chiếu camera quầy.', null, null],
            ['hoang-gia-sports', 'hoang-gia-phu-my-hung', 'owner.hoanggia@demo.auracourt.vn', $today->copy()->subDays(3), '15:00', '21:30', 500000, 0, 'matched', 'Chủ sân tự đứng quầy, két đủ.', null, null],
            ['hoang-gia-sports', 'hoang-gia-phu-my-hung', 'owner.hoanggia@demo.auracourt.vn', $today->copy()->subDay(), '15:00', '21:30', 500000, 0, 'matched', null, null, null],
        ];

        foreach ($shifts as [$orgSlug, $venueSlug, $cashierEmail, $day, $open, $close, $openingCash, $variance, $status, $note, $reviewerEmail, $reviewNote]) {
            [$org, $venueId, $cashier] = $this->context($orgSlug, $venueSlug, $cashierEmail);
            if (! $org || ! $cashier) {
                continue;
            }
            $openedAt = $day->copy()->setTimeFromTimeString($open);
            $closedAt = $day->copy()->setTimeFromTimeString($close);
            $reviewer = $reviewerEmail ? User::where('email', $reviewerEmail)->first() : null;

            $shift = CashShift::firstOrNew(['organization_id' => $org->id, 'cashier_id' => $cashier->id, 'opened_at' => $openedAt]);
            $shift->forceFill([
                'venue_id' => $venueId,
                'register_id' => null,
                'opening_cash' => $openingCash,
                'expected_cash' => $openingCash,
                'declared_cash' => $openingCash + $variance,
                'variance' => $variance,
                'status' => 'closed',
                'closed_at' => $closedAt,
                'cash_movements' => [],
                'created_at' => $openedAt,
                'updated_at' => $closedAt,
            ])->save();

            $reviewedAt = $reviewer ? $closedAt->copy()->addDay()->setTime(9, 15) : null;
            ShiftReconciliation::firstOrNew(['cash_shift_id' => $shift->id])->forceFill([
                'organization_id' => $org->id,
                'variance' => $variance,
                'status' => $status,
                // Review decisions are appended to the close note, as PosController::reviewShift does.
                'note' => $reviewNote ? trim(($note ?? '').' | '.$reviewNote, ' |') : $note,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => $reviewedAt,
                'created_at' => $closedAt,
                'updated_at' => $reviewedAt ?? $closedAt,
            ])->save();
        }

        // Today's open shift for the cashier; one open shift per cashier is enforced by PosService::openShift.
        [$org, $venueId, $cashier] = $this->context('aura-badminton-club', 'aura-court', 'cashier@demo.auracourt.vn');
        if ($org && $cashier) {
            $openedAt = $now->gte($today->copy()->setTime(8, 0)) ? $today->copy()->setTime(7, 0) : $now->copy()->subHour();
            CashShift::firstOrNew(['organization_id' => $org->id, 'cashier_id' => $cashier->id, 'status' => 'open'])->forceFill([
                'venue_id' => $venueId,
                'register_id' => null,
                'opening_cash' => 1000000,
                // Like PosService::openShift: stored as 0, the shift endpoints compute it live while open.
                'expected_cash' => 0,
                'declared_cash' => null,
                'variance' => null,
                'opened_at' => $openedAt,
                'closed_at' => null,
                'cash_movements' => [],
                'created_at' => $openedAt,
                'updated_at' => $openedAt,
            ])->save();
        }
    }

    /** @return array{0: ?Organization, 1: ?int, 2: ?User} */
    private function context(string $orgSlug, string $venueSlug, string $cashierEmail): array
    {
        return [
            Organization::where('slug', $orgSlug)->first(),
            Venue::where('slug', $venueSlug)->value('id'),
            User::where('email', $cashierEmail)->first(),
        ];
    }
}
