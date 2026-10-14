<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\EntitlementTransfer;
use App\Models\MarketplaceListing;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Social\MarketplaceService;
use Carbon\Carbon;
use Database\Seeders\Concerns\FindsFreeCourtTime;
use Database\Seeders\Concerns\PaysVenueOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Peer-to-peer resale. Each seller paid the venue owner for the slot (owner receipt), then listed it with the bank
 * account the buyer transfers to; the seller confirms receipt and the slot moves, with no platform fee.
 * Rows are written directly in the shapes MarketplaceService::list and PeerToPeerResaleService write, so seeding
 * neither depends on feature flags nor sends the resale notifications.
 */
class MarketplaceSeeder extends Seeder
{
    use FindsFreeCourtTime;
    use PaysVenueOwner;

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

    /** Completed resales: [reservation code, seller, buyer, court slug, days ahead, start hour, asking % of the slot price, notes]. */
    private const SOLD = [
        ['RES-P2P-101', 5, 11, 'san-2-tieu-chuan-cau-giay', 4, 20, 90, 'Lịch làm thêm giờ nên nhượng lại sân tối.'],
        ['RES-P2P-102', 17, 0, 'san-pickleball-1-neon-cau-giay', 5, 19, 85, 'Bạn tập cùng bị ốm, pass lại sân cho người cần.'],
        ['RES-P2P-103', 20, 7, 'san-tennis-1-dat-nen-tay-ho', 6, 17, 100, 'Đi công tác đột xuất, nhượng lại đúng giá gốc.'],
    ];

    private Carbon $now;

    public function run(): void
    {
        $this->now = Carbon::now(config('app.timezone'))->startOfMinute();
        $marketplace = app(MarketplaceService::class);

        foreach (self::RESALES as $index => [$code, $sellerNo, $slug, $daysAhead, $hour, $hours, $listed, $percent, $badge, $notes]) {
            $seller = $this->customer($sellerNo);
            $court = Court::with(['venue', 'sportZone'])->where('slug', $slug)->first();
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

        foreach (self::SOLD as $index => [$code, $sellerNo, $buyerNo, $slug, $daysAhead, $hour, $percent, $notes]) {
            $seller = $this->customer($sellerNo);
            $buyer = $this->customer($buyerNo);
            $court = Court::with(['venue', 'sportZone'])->where('slug', $slug)->first();
            if (! $seller || ! $buyer || ! $court || Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->exists()) {
                continue;
            }

            $reservation = $this->paidReservation($code, $seller, $buyer, $court, $daysAhead, $hour, 1, count(self::RESALES) + $index);
            $listedAt = $this->now->copy()->subDays(2)->setTime(20, 0)->addMinutes($index * 13);
            $listing = $this->list($marketplace, $reservation->entitlements->first(), $seller, $percent, $listedAt, ['badge' => 'deal', 'notes' => $notes]);
            $this->sell($listing, $buyer, $this->now->copy()->subDay()->setTime(12, 0)->addMinutes($index * 17));
        }
    }

    /** A booking the seller held and paid to the venue owner two to four days ago, shaped like ReservationService::hold. */
    private function paidReservation(string $code, User $seller, ?User $buyer, Court $court, int $daysAhead, int $hour, int $hours, int $index): Reservation
    {
        $day = $this->now->copy()->startOfDay()->addDays($daysAhead);
        [$court, $startHour] = $this->firstFit($court, $hour, $hours, fn (Court $candidate, int $at): bool => $this->isFree(
            $candidate->id, $day->copy()->setTime($at, 0), $day->copy()->setTime($at + $hours, 0), $seller->id
        ) && (! $buyer || $this->isFree($candidate->id, $day->copy()->setTime($at, 0), $day->copy()->setTime($at + $hours, 0), $buyer->id)));
        $starts = array_map(fn (int $offset): Carbon => $day->copy()->setTime($startHour + $offset, 0), range(0, $hours - 1));
        $snapshots = app(PriceQuoteService::class)->priceSnapshots($court, $starts, 60);
        $total = (int) array_sum(array_column($snapshots, 'unit_price'));
        $bookedAt = $this->now->copy()->subDays(2 + $index % 3)->setTime(10, 0)->addMinutes($index * 11);
        $paidAt = $bookedAt->copy()->addMinutes(3);

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
        $this->payOwner($court->venue, $seller, 'booking', $code, $total, $bookedAt, $paidAt);

        return $reservation->load('entitlements');
    }

    /** Listed through MarketplaceService at $listedAt, to the configured demo account the buyer transfers to. */
    private function list(MarketplaceService $marketplace, ReservationEntitlement $entitlement, User $seller, int $percent, Carbon $listedAt, array $metadata): MarketplaceListing
    {
        $connection = Venue::where('slug', 'aura-court')->firstOrFail()->currentReceivingConnection()->firstOrFail();
        $receiver = ['bank_code' => $connection->bank_code, 'account_number' => $connection->accountNumber(), 'account_name' => $connection->accountName()];
        $asking = intdiv($entitlement->amount * $percent, 100 * 5000) * 5000;

        return $this->atClock($listedAt, function () use ($marketplace, $entitlement, $seller, $asking, $receiver, $metadata): MarketplaceListing {
            $listing = $marketplace->list($entitlement, $seller, $asking, $receiver);
            $listing->forceFill(['metadata' => $metadata])->saveQuietly();

            return $listing;
        });
    }

    /** The buyer locked the listing, reported the transfer, and the seller confirmed receipt: PeerToPeerResaleService::transfer. */
    private function sell(MarketplaceListing $listing, User $buyer, Carbon $lockedAt): void
    {
        $reportedAt = $lockedAt->copy()->addMinutes(6);
        $soldAt = $reportedAt->copy()->addMinutes(20);
        $reference = 'RS'.Str::upper(Str::random(10));
        $metadata = [
            ...(array) $listing->metadata,
            'buyer_ids' => [$buyer->id],
            'locked_by' => $buyer->id,
            'locked_until' => $listing->cutoff_at->copy()->utc()->toIso8601String(),
            'reference' => $reference,
            'payment_reported_at' => $reportedAt->copy()->utc()->toIso8601String(),
        ];

        $this->atClock($soldAt, function () use ($listing, $buyer, $metadata, $reference): void {
            $entitlement = ReservationEntitlement::withoutGlobalScope('tenant_isolation')->findOrFail($listing->reservation_entitlement_id);
            $entitlement->update(['status' => 'transferred']);
            $buyerEntitlement = ReservationEntitlement::create([
                'organization_id' => $entitlement->organization_id,
                'reservation_id' => $entitlement->reservation_id,
                'reservation_slot_id' => $entitlement->reservation_slot_id,
                'user_id' => $buyer->id,
                'court_id' => $entitlement->court_id,
                'starts_at' => $entitlement->starts_at,
                'ends_at' => $entitlement->ends_at,
                'amount' => $entitlement->amount,
                'status' => 'active',
            ]);
            // One-slot bookings: the buyer now holds every active entitlement, so the booking follows.
            Reservation::withoutGlobalScope('tenant_isolation')->whereKey($entitlement->reservation_id)->update(['user_id' => $buyer->id, 'updated_at' => now()]);

            EntitlementTransfer::create([
                'organization_id' => $listing->organization_id,
                'marketplace_listing_id' => $listing->id,
                'seller_id' => $listing->seller_id,
                'buyer_id' => $buyer->id,
                'seller_entitlement_id' => $entitlement->id,
                'buyer_entitlement_id' => $buyerEntitlement->id,
                'buyer_amount' => (int) $listing->asking_price,
                'marketplace_fee' => 0,
                'seller_refund' => 0,
                'buyer_payment_reference' => $reference,
                'idempotency_key' => 'p2p:listing:'.$listing->id,
                'status' => 'completed',
                'metadata' => [
                    'settlement_mode' => 'peer_to_peer',
                    'original_price' => (int) $entitlement->amount,
                    'consideration_amount' => (int) $listing->asking_price,
                    'payment_reported_at' => $metadata['payment_reported_at'],
                ],
            ]);
            $listing->forceFill(['status' => 'sold', 'sold_at' => now(), 'metadata' => $metadata])->save();
        });
    }

    private function customer(int $number): ?User
    {
        return User::where('email', $number === 0 ? 'user@example.com' : sprintf('khach%02d@demo.auracourt.vn', $number))->first();
    }
}
