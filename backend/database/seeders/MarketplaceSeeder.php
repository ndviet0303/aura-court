<?php

namespace Database\Seeders;

use App\Models\CashShift;
use App\Models\Court;
use App\Models\MarketplaceListing;
use App\Models\OwnerPaymentAllocation;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCreditEntry;
use App\Services\Finance\VenueCreditCashOutService;
use App\Services\Finance\VenueCreditService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Social\MarketplaceService;
use App\Services\Social\ResaleTransfer;
use Carbon\Carbon;
use Database\Seeders\Concerns\FindsFreeCourtTime;
use Database\Seeders\Concerns\PaysVenueOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Owner-collected resale. Each seller paid the venue owner for the slot, then listed it; a buyer paid the asking price
 * into the venue's account, the slot moved and the seller got that much venue credit. The demo customer then pays part
 * of a booking with credit and another seller cashes credit out at the open counter shift.
 * Rows are written in the shapes MarketplaceService::list, OwnerCollectedResaleService and VenueCreditService write,
 * so seeding neither depends on feature flags nor sends the resale notifications.
 */
class MarketplaceSeeder extends Seeder
{
    use FindsFreeCourtTime;
    use PaysVenueOwner;

    /**
     * [reservation code, seller (0 = user@example.com, N = khachNN), court code, days ahead, start hour, hours, listed slot positions, asking % of the slot price, badge, notes].
     * Every start is 4+ days out, so the venues' 24h transfer cutoff keeps each listing open for days after seeding.
     */
    private const RESALES = [
        ['RES-P2P-001', 3, 'Sân 1 - Thảm BWF Tiêu Chuẩn', 4, 19, 1, [0], 90, 'urgent', 'Bận việc gia đình đột xuất, pass gấp lại cho anh em giao lưu.'],
        ['RES-P2P-002', 6, 'Sân Pickleball 3 - Sunset View Hồ Tây', 5, 7, 1, [0], 80, 'deal', 'Sân pickleball view hồ Tây, nhượng lại do trùng lịch công tác.'],
        ['RES-P2P-003', 9, 'Sân Bóng Đá 5v5 A1 Quần Ngựa', 5, 19, 1, [0], 95, 'hot', 'Đội thiếu người nên nhượng lại sân, có sẵn bóng và áo bib.'],
        ['RES-P2P-004', 12, 'Sân Tennis 3 - Mặt Cứng US Open Landmark', 6, 17, 1, [0], 85, 'deal', 'Đổi lịch tập với huấn luyện viên nên pass lại sân tennis mặt cứng.'],
        ['RES-P2P-005', 15, 'Sân Bóng Rổ 1 - Streetball Zone Tây Hồ', 7, 18, 1, [0], 90, 'hot', 'Sân streetball đèn sáng, nhượng lại cho nhóm nào muốn đấu 3x3.'],
        ['RES-P2P-006', 18, 'Sân Bóng Bàn 1 - Double Fish ITTF', 8, 20, 1, [0], 75, 'deal', 'Bàn thi đấu tiêu chuẩn, nhượng lại giá mềm cho bạn nào muốn luyện tập.'],
        ['RES-P2P-007', 21, 'Sân Bóng Chuyền 1 - Thảm Taraflex Landmark', 9, 18, 2, [0, 1], 90, 'hot', 'Nhượng lại trọn 2 giờ sân bóng chuyền thảm Taraflex cho đội cần sân tập.'],
        ['RES-P2P-008', 24, 'Sân 11 - Sài Gòn Central BWF', 10, 20, 1, [0], 85, 'deal', 'Chuyến công tác vào Sài Gòn bị hủy nên pass lại sân cầu lông chuẩn BWF.'],
        ['RES-P2P-009', 2, 'Sân Bóng Đá 7v7 C1 Quần Ngựa', 11, 19, 1, [0], 90, 'hot', 'Sân 7 người cỏ mới, nhượng lại vì đội nghỉ đá tuần này.'],
        // The demo customer resells the second hour and keeps the first, which stays sellable in the Sell tab.
        ['RES-MY-001', 0, 'Sân Tennis 2 - Hard Court Pro Tây Hồ', 6, 18, 2, [1], 90, 'deal', 'Chỉ chơi được giờ đầu, nhượng lại giờ sau cho bạn nào cần sân.'],
    ];

    /** Completed resales: [reservation code, seller, buyer, court code, days ahead, start hour, asking % of the slot price, notes]. */
    private const SOLD = [
        ['RES-P2P-101', 5, 11, 'Sân 2 - Tiêu Chuẩn', 4, 20, 90, 'Lịch làm thêm giờ nên nhượng lại sân tối.'],
        ['RES-P2P-102', 17, 0, 'Sân Pickleball 1 - Aura Neon', 5, 19, 85, 'Bạn tập cùng bị ốm, pass lại sân cho người cần.'],
        ['RES-P2P-103', 20, 7, 'Sân Tennis 1 - Đất Nện French Open Tây Hồ', 6, 17, 100, 'Đi công tác đột xuất, nhượng lại đúng giá gốc.'],
        // The demo customer sold one too, so their profile shows venue credit.
        ['RES-MY-101', 0, 14, 'Sân 2 - Tiêu Chuẩn', 5, 18, 100, 'Trùng lịch họp nên nhượng lại sân, đúng giá gốc.'],
    ];

    /** [reservation code, customer, court code, days ahead, start hour]: half of it, at most their balance, paid with credit. */
    private const CREDIT_BOOKING = ['RES-CREDIT-001', 0, 'Sân 2 - Tiêu Chuẩn', 3, 20];

    /** [seller, cashier email, amount]: cashed out at the cashier's open shift an hour ago. */
    private const CASH_OUT = [5, 'cashier@demo.auracourt.vn', 50000];

    private Carbon $now;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone'))->startOfMinute();
        $marketplace = app(MarketplaceService::class);

        foreach (self::RESALES as $index => [$code, $sellerNo, $courtCode, $daysAhead, $hour, $hours, $listed, $percent, $badge, $notes]) {
            $seller = $this->customer($sellerNo);
            $court = Court::with(['venue'])->where('code', $courtCode)->first();
            // The code is the idempotency key: a seeded resale is never rebuilt or moved.
            if (! $seller || ! $court || Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->exists()) {
                continue;
            }

            $reservation = $this->paidReservation($code, $seller, null, $court, $daysAhead, $hour, $hours, $index);
            $listedAt = $this->now->copy()->subDay()->setTime(19, 0)->addMinutes($index * 7);
            foreach ($reservation->entitlements->sortBy('starts_at')->values() as $position => $entitlement) {
                if (in_array($position, $listed, true)) {
                    $this->list($marketplace, $entitlement, $seller, $percent, $listedAt, ['badge' => $badge, 'notes' => $notes]);
                }
            }
        }

        foreach (self::SOLD as $index => [$code, $sellerNo, $buyerNo, $courtCode, $daysAhead, $hour, $percent, $notes]) {
            $seller = $this->customer($sellerNo);
            $buyer = $this->customer($buyerNo);
            $court = Court::with(['venue'])->where('code', $courtCode)->first();
            if (! $seller || ! $buyer || ! $court || Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->exists()) {
                continue;
            }

            $reservation = $this->paidReservation($code, $seller, $buyer, $court, $daysAhead, $hour, 1, count(self::RESALES) + $index);
            $listedAt = $this->now->copy()->subDays(2)->setTime(20, 0)->addMinutes($index * 13);
            $listing = $this->list($marketplace, $reservation->entitlements->first(), $seller, $percent, $listedAt, ['badge' => 'deal', 'notes' => $notes]);
            $this->sell($court->venue, $listing, $buyer, $this->now->copy()->subDay()->setTime(12, 0)->addMinutes($index * 17));
        }

        $this->creditBooking();
        $this->cashOut();
    }

    /** Booked three hours ago with credit from the sale; the QR paid the rest within minutes. */
    private function creditBooking(): void
    {
        [$code, $customerNo, $courtCode, $daysAhead, $hour] = self::CREDIT_BOOKING;
        $customer = $this->customer($customerNo);
        $court = Court::with(['venue'])->where('code', $courtCode)->first();
        if (! $customer || ! $court || Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->exists()) {
            return;
        }
        $balance = app(VenueCreditService::class)->balance($court->venue->organization_id, $customer->id);
        if ($balance > 0) {
            $this->paidReservation($code, $customer, null, $court, $daysAhead, $hour, 1, 0, $this->now->copy()->subHours(3), $balance);
        }
    }

    /** The cashier counted the cash out after the seller showed their one-time code (design D7). */
    private function cashOut(): void
    {
        [$sellerNo, $cashierEmail, $amount] = self::CASH_OUT;
        $seller = $this->customer($sellerNo);
        $cashier = User::where('email', $cashierEmail)->first();
        $shift = $cashier ? CashShift::where('cashier_id', $cashier->id)->where('status', 'open')->first() : null;
        if (! $seller || ! $shift || VenueCreditEntry::withoutGlobalScope('tenant_isolation')->where('user_id', $seller->id)->where('type', 'cash_out')->exists()
            || app(VenueCreditService::class)->balance($shift->organization_id, $seller->id) < $amount) {
            return;
        }
        $this->atClock($this->now->copy()->subHour()->max($shift->opened_at), function () use ($seller, $cashier, $shift, $amount): void {
            $cashOuts = app(VenueCreditCashOutService::class);
            $cashOuts->confirm($shift, $cashier, $cashOuts->create($seller, $shift->organization()->firstOrFail(), $amount)['code']);
        });
    }

    /**
     * A booking the seller held and paid to the venue owner two to four days ago, shaped like ReservationService::hold.
     * With $credit, up to half the total was paid with venue credit, reserved at booking and captured on payment.
     */
    private function paidReservation(string $code, User $seller, ?User $buyer, Court $court, int $daysAhead, int $hour, int $hours, int $index, ?Carbon $bookedAt = null, int $credit = 0): Reservation
    {
        $day = $this->now->copy()->startOfDay()->addDays($daysAhead);
        [$court, $startHour] = $this->firstFit($court, $hour, $hours, fn (Court $candidate, int $at): bool => $this->isFree(
            $candidate->id, $day->copy()->setTime($at, 0), $day->copy()->setTime($at + $hours, 0), $seller->id
        ) && (! $buyer || $this->isFree($candidate->id, $day->copy()->setTime($at, 0), $day->copy()->setTime($at + $hours, 0), $buyer->id)));
        $starts = array_map(fn (int $offset): Carbon => $day->copy()->setTime($startHour + $offset, 0), range(0, $hours - 1));
        $snapshots = app(PriceQuoteService::class)->priceSnapshots($court, $starts, 60);
        $total = (int) array_sum(array_column($snapshots, 'unit_price'));
        $bookedAt ??= $this->now->copy()->subDays(2 + $index % 3)->setTime(10, 0)->addMinutes($index * 11);
        $paidAt = $bookedAt->copy()->addMinutes(3);
        $credit = min($credit, intdiv($total, 2));

        $reservation = Reservation::query()->forceCreateQuietly([
            'organization_id' => $court->venue->organization_id,
            'user_id' => $seller->id,
            'court_id' => $court->id,
            'code' => $code,
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => $total,
            'hold_expires_at' => $bookedAt->copy()->addMinutes(15),
            'confirmed_at' => $paidAt,
            'payment_model' => 'direct_owner_v1',
            'metadata' => $credit > 0 ? ['venue_credit_amount' => $credit] : null,
            'created_at' => $bookedAt,
            'updated_at' => $paidAt,
        ]);
        foreach ($starts as $position => $slotStart) {
            $slot = ReservationSlot::forceCreate([
                'organization_id' => $reservation->organization_id,
                'reservation_id' => $reservation->id,
                'court_id' => $court->id,
                'starts_at' => $slotStart,
                'ends_at' => $slotStart->copy()->addHour(),
                'unit_price' => $snapshots[$position]['unit_price'],
                'status' => 'confirmed',
                'snapshot' => $snapshots[$position],
                'created_at' => $bookedAt,
                'updated_at' => $paidAt,
            ]);
            ReservationEntitlement::forceCreate([
                'organization_id' => $reservation->organization_id,
                'reservation_id' => $reservation->id,
                'reservation_slot_id' => $slot->id,
                'user_id' => $seller->id,
                'court_id' => $court->id,
                'starts_at' => $slotStart,
                'ends_at' => $slotStart->copy()->addHour(),
                'amount' => $snapshots[$position]['unit_price'],
                'status' => 'active',
                'created_at' => $bookedAt,
                'updated_at' => $paidAt,
            ]);
        }
        if ($credit > 0) {
            $this->atClock($bookedAt, fn () => app(VenueCreditService::class)->reserve($reservation, $credit));
            $this->atClock($paidAt, fn () => app(VenueCreditService::class)->capture($reservation));
        }
        $this->payOwner($court->venue, $seller, 'booking', $code, $total - $credit, $bookedAt, $paidAt);

        return $reservation->load('entitlements');
    }

    /** Listed through MarketplaceService at $listedAt. */
    private function list(MarketplaceService $marketplace, ReservationEntitlement $entitlement, User $seller, int $percent, Carbon $listedAt, array $metadata): MarketplaceListing
    {
        $asking = intdiv($entitlement->amount * $percent, 100 * 5000) * 5000;

        return $this->atClock($listedAt, function () use ($marketplace, $entitlement, $seller, $asking, $metadata): MarketplaceListing {
            $listing = $marketplace->list($entitlement, $seller, $asking);
            // Keeps the max_resale_price list() stored (D23).
            $listing->forceFill(['metadata' => [...(array) $listing->metadata, ...$metadata]])->saveQuietly();

            return $listing;
        });
    }

    /**
     * The buyer held the listing and paid the venue's QR within minutes; the receipt moved the slot and issued the
     * seller credit, as OwnerCollectedResaleService::confirmPaidAttempt does.
     */
    private function sell(Venue $venue, MarketplaceListing $listing, User $buyer, Carbon $lockedAt): void
    {
        $paidAt = $lockedAt->copy()->addMinutes(6);
        $reference = 'AC'.Str::upper(Str::random(18));
        $listing->forceFill(['status' => 'checkout', 'metadata' => [
            ...(array) $listing->metadata,
            'buyer_ids' => [$buyer->id],
            'locked_by' => $buyer->id,
            'locked_until' => $lockedAt->copy()->addMinutes(15)->utc()->toIso8601String(),
            'reference' => $reference,
        ]])->saveQuietly();
        $attempt = $this->payOwner($venue, $buyer, 'resale', (string) $listing->id, (int) $listing->asking_price, $lockedAt, $paidAt, $reference);

        $this->atClock($paidAt, function () use ($listing, $buyer, $attempt, $paidAt): void {
            $entitlement = ReservationEntitlement::withoutGlobalScope('tenant_isolation')->findOrFail($listing->reservation_entitlement_id);
            $transfer = app(ResaleTransfer::class)->move($listing, $buyer, $entitlement, $attempt->reference);
            app(VenueCreditService::class)->issue($listing->organization_id, $listing->seller_id, (int) $listing->asking_price, 'entitlement_transfer', $transfer->id,
                'receipt:'.OwnerPaymentAllocation::where('payment_attempt_id', $attempt->id)->value('payment_receipt_id'),
                ['marketplace_listing_id' => $listing->id, 'paid_at' => $paidAt->toIso8601String()]);
        });
    }

    private function customer(int $number): ?User
    {
        return User::where('email', $number === 0 ? 'user@example.com' : sprintf('khach%02d@demo.auracourt.vn', $number))->first();
    }
}
