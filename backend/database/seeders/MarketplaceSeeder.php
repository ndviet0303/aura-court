<?php

namespace Database\Seeders;

use App\Contracts\Repositories\BookingTransferListingRepositoryInterface;
use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Models\BookingTransfer;
use App\Models\BookingTransferListingItem;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Services\Reservation\PriceQuoteService;
use Carbon\Carbon;
use Database\Seeders\Concerns\FindsFreeCourtTime;
use Database\Seeders\Concerns\PaysVenueOwner;
use Illuminate\Database\Seeder;

/**
 * Module 08.2: paid, confirmed bookings 4+ days out, each paid to the venue owner two to four days ago, in the shapes
 * ReservationService::hold and the owner receipt write. Some are on the transfer market: available, held by a pending
 * transfer, transferred by a completed one, and withdrawn; the rest stay unlisted for posting.
 */
class MarketplaceSeeder extends Seeder
{
    use FindsFreeCourtTime;
    use PaysVenueOwner;

    /** [reservation code, customer (0 = user@example.com, N = khachNN), court code, days ahead, start hour, hours]. */
    private const BOOKINGS = [
        ['RES-P2P-001', 3, 'Sân 1 - Thảm BWF Tiêu Chuẩn', 4, 19, 1],
        ['RES-P2P-002', 6, 'Sân Pickleball 3 - Sunset View Hồ Tây', 5, 7, 1],
        ['RES-P2P-003', 9, 'Sân Bóng Đá 5v5 A1 Quần Ngựa', 5, 19, 1],
        ['RES-P2P-004', 12, 'Sân Tennis 3 - Mặt Cứng US Open Landmark', 6, 17, 1],
        ['RES-P2P-005', 15, 'Sân Bóng Rổ 1 - Streetball Zone Tây Hồ', 7, 18, 1],
        ['RES-P2P-006', 18, 'Sân Bóng Bàn 1 - Double Fish ITTF', 8, 20, 1],
        ['RES-P2P-007', 21, 'Sân Bóng Chuyền 1 - Thảm Taraflex Landmark', 9, 18, 2],
        ['RES-P2P-008', 24, 'Sân 11 - Sài Gòn Central BWF', 10, 20, 1],
        ['RES-P2P-009', 2, 'Sân Bóng Đá 7v7 C1 Quần Ngựa', 11, 19, 1],
        ['RES-MY-001', 0, 'Sân Tennis 2 - Hard Court Pro Tây Hồ', 6, 18, 2],
    ];

    /** [reservation code, note, state: available | held | transferred | withdrawn, buyer customer number]. */
    private const LISTINGS = [
        ['RES-P2P-001', 'Bận việc đột xuất, nhượng lại đúng giá đã trả.', 'available', null],
        ['RES-P2P-002', 'Nhượng lại sân sáng, giảm nhẹ cho bạn nào cần.', 'held', 12],
        ['RES-P2P-003', 'Đội mình nghỉ tuần này, ai cần sân tối liên hệ nhé.', 'transferred', 18],
        ['RES-P2P-004', 'Đổi lịch công tác.', 'withdrawn', null],
        ['RES-P2P-005', null, 'available', null],
    ];

    private Carbon $now;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone'))->startOfMinute();

        foreach (self::BOOKINGS as $index => [$code, $customerNo, $courtCode, $daysAhead, $hour, $hours]) {
            $customer = User::where('email', $customerNo === 0 ? 'user@example.com' : sprintf('khach%02d@demo.auracourt.vn', $customerNo))->first();
            $court = Court::with(['venue'])->where('code', $courtCode)->first();
            // The code is the idempotency key: a seeded booking is never rebuilt.
            if (! $customer || ! $court || Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->exists()) {
                continue;
            }

            $this->paidReservation($code, $customer, $court, $daysAhead, $hour, $hours, $index);
        }

        foreach (self::LISTINGS as [$code, $note, $state, $buyerNo]) {
            $this->listing($code, $note, $state, $buyerNo);
        }
    }

    /** Rows as PostTransferListing, CreateBookingTransfer and ConfirmBookingTransfer leave them; skipped once listed. */
    private function listing(string $code, ?string $note, string $state, ?int $buyerNo): void
    {
        $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->first();
        if (! $reservation || BookingTransferListingItem::query()->where('reservation_id', $reservation->id)->exists()) {
            return;
        }
        $listings = app(BookingTransferListingRepositoryInterface::class);
        $transfers = app(BookingTransferRepositoryInterface::class);
        $item = ['reservation_id' => (int) $reservation->id, 'organization_id' => (int) $reservation->organization_id, 'price' => (int) round($reservation->total_amount * 0.9, -3)];
        $listing = $listings->create((int) $reservation->user_id, $note, [$item]);
        if ($state === 'withdrawn') {
            $listings->withdraw($listing->items()->pluck('id')->all());
        }
        $buyer = $buyerNo === null ? null : User::where('email', sprintf('khach%02d@demo.auracourt.vn', $buyerNo))->first();
        if ($buyer === null) {
            return;
        }
        $transfer = $transfers->create(['listing_id' => $listing->id, 'seller_id' => $reservation->user_id, 'buyer_id' => $buyer->id, 'total' => $item['price']], [$item]);
        if ($state === 'transferred') {
            $transfers->moveHolding($reservation, (int) $buyer->id, ['name' => (string) $buyer->name, 'phone' => (string) $buyer->phone, 'email' => (string) $buyer->email]);
            $transfers->close($transfer, BookingTransfer::STATUS_COMPLETED, ['confirmed_at' => $this->now]);
            $listings->markTransferred([(int) $reservation->id]);
        }
    }

    private function paidReservation(string $code, User $customer, Court $court, int $daysAhead, int $hour, int $hours, int $index): Reservation
    {
        $day = $this->now->copy()->startOfDay()->addDays($daysAhead);
        [$court, $startHour] = $this->firstFit($court, $hour, $hours, fn (Court $candidate, int $at): bool => $this->isFree(
            $candidate->id, $day->copy()->setTime($at, 0), $day->copy()->setTime($at + $hours, 0), $customer->id
        ));
        $starts = array_map(fn (int $offset): Carbon => $day->copy()->setTime($startHour + $offset, 0), range(0, $hours - 1));
        $snapshots = app(PriceQuoteService::class)->priceSnapshots($court, $starts, 60);
        $total = (int) array_sum(array_column($snapshots, 'unit_price'));
        $bookedAt = $this->now->copy()->subDays(2 + $index % 3)->setTime(10, 0)->addMinutes($index * 11);
        $paidAt = $bookedAt->copy()->addMinutes(3);

        $reservation = Reservation::query()->forceCreateQuietly([
            'organization_id' => $court->venue->organization_id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => $code,
            'check_in_code' => $code,
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => $total,
            'hold_expires_at' => $bookedAt->copy()->addMinutes(15),
            'confirmed_at' => $paidAt,
            'payment_model' => 'direct_owner_v1',
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
                'user_id' => $customer->id,
                'court_id' => $court->id,
                'starts_at' => $slotStart,
                'ends_at' => $slotStart->copy()->addHour(),
                'amount' => $snapshots[$position]['unit_price'],
                'status' => 'active',
                'created_at' => $bookedAt,
                'updated_at' => $paidAt,
            ]);
        }
        $this->payOwner($court->venue, $customer, 'booking', $code, $total, $bookedAt, $paidAt);

        return $reservation;
    }
}
