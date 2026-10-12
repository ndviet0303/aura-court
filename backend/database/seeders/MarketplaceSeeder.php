<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reservation\ReservationService;
use App\Services\Social\MarketplaceService;
use Carbon\Carbon;
use Database\Seeders\Concerns\FindsFreeCourtTime;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    use FindsFreeCourtTime;

    /**
     * [reservation code, seller (0 = user@example.com, N = khachNN), court slug, days ahead, start hour, hours, listed slot positions, asking % of the slot price, badge, notes].
     * Every start is 4+ days out, so the venues' 24h transfer cutoff keeps each listing open for days after seeding.
     */
    private const RESALES = [
        ['RES-P2P-001', 3, 'san-1-tham-bwf-cau-giay', 4, 19, 1, [0], 90, 'urgent', 'Bận việc gia đình đột xuất, pass gấp lại cho anh em giao lưu.'],
        ['RES-P2P-002', 6, 'san-pickleball-3-sunset-tay-ho', 5, 7, 1, [0], 80, 'deal', 'Sân pickleball view hồ Tây, nhượng lại do trùng lịch công tác.'],
        ['RES-P2P-003', 9, 'san-bong-da-5v5-a1-quan-ngua', 5, 19, 1, [0], 95, 'hot', 'Đội thiếu người nên nhượng lại sân, có sẵn bóng và áo bib.'],
        ['RES-P2P-004', 12, 'san-tennis-3-mat-cung-landmark', 6, 17, 1, [0], 85, 'deal', 'Đổi lịch tập với huấn luyện viên nên pass lại sân tennis mặt cứng.'],
        ['RES-P2P-005', 15, 'san-bong-ro-1-streetball-tay-ho', 7, 18, 1, [0], 90, 'hot', 'Sân streetball đèn sáng, nhượng lại cho nhóm nào muốn đấu 3x3.'],
        ['RES-P2P-006', 18, 'san-bong-ban-1-double-fish-cau-giay', 8, 20, 1, [0], 75, 'deal', 'Bàn thi đấu tiêu chuẩn, nhượng lại giá mềm cho bạn nào muốn luyện tập.'],
        ['RES-P2P-007', 21, 'san-bong-chuyen-1-taraflex-landmark', 9, 18, 2, [0, 1], 90, 'hot', 'Nhượng lại trọn 2 giờ sân bóng chuyền thảm Taraflex cho đội cần sân tập.'],
        ['RES-P2P-008', 24, 'san-11-sai-gon-central-bwf', 10, 20, 1, [0], 85, 'deal', 'Chuyến công tác vào Sài Gòn bị hủy nên pass lại sân cầu lông chuẩn BWF.'],
        ['RES-P2P-009', 2, 'san-bong-da-7v7-c1-quan-ngua', 11, 19, 1, [0], 90, 'hot', 'Sân 7 người cỏ mới, nhượng lại vì đội nghỉ đá tuần này.'],
        // The demo customer resells the second hour and keeps the first, which stays sellable in the Sell tab.
        ['RES-MY-001', 0, 'san-tennis-2-hard-court-tay-ho', 6, 18, 2, [1], 90, 'deal', 'Chỉ chơi được giờ đầu, nhượng lại giờ sau cho bạn nào cần sân.'],
    ];

    public function run(): void
    {
        $reservations = app(ReservationService::class);
        $marketplace = app(MarketplaceService::class);
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        foreach (self::RESALES as [$code, $sellerNo, $slug, $daysAhead, $hour, $hours, $listed, $percent, $badge, $notes]) {
            $seller = User::where('email', $sellerNo === 0 ? 'user@example.com' : sprintf('khach%02d@demo.auracourt.vn', $sellerNo))->first();
            $court = Court::with(['venue', 'sportZone'])->where('slug', $slug)->first();
            // The code is the idempotency key: a seeded resale is never rebuilt or moved.
            if (! $seller || ! $court || Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->exists()) {
                continue;
            }

            $day = $today->copy()->addDays($daysAhead);
            [$court, $startHour] = $this->firstFit($court, $hour, $hours, fn (Court $candidate, int $at): bool => $this->isFree(
                $candidate->id, $day->copy()->setTime($at, 0), $day->copy()->setTime($at + $hours, 0), $seller->id
            ));
            $minutes = (int) ($court->sportZone?->slot_duration ?: 60);
            $slots = array_map(
                fn (int $position): string => $day->copy()->setTime($startHour, 0)->addMinutes($position * $minutes)->toIso8601String(),
                range(0, intdiv($hours * 60, $minutes) - 1)
            );

            // Quoted, held and wallet-paid through checkout; the seeded code is set before payment so the debit and journal carry it.
            $reservation = $reservations->hold($seller, (int) $court->venue->organization_id, ['court_id' => $court->id, 'slots' => $slots]);
            $reservation->forceFill(['code' => $code])->saveQuietly();
            $reservation = $reservations->confirm($reservation);

            foreach ($reservation->entitlements->sortBy('starts_at')->values() as $position => $entitlement) {
                if (in_array($position, $listed, true)) {
                    $asking = intdiv($entitlement->amount * $percent, 100 * 5000) * 5000;
                    $marketplace->list($entitlement, $seller, $asking)
                        ->forceFill(['metadata' => ['badge' => $badge, 'notes' => $notes]])
                        ->saveQuietly();
                }
            }
        }
    }
}
