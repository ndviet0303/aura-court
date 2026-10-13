<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\LegacyBookingSlotMapping;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use Illuminate\Support\Facades\DB;

class LegacyBookingBackfillService
{
    public function audit(?int $organizationId = null): array
    {
        $query = Booking::query()->with(['court.venue', 'slots'])->orderBy('id');
        if ($organizationId !== null) {
            $query->whereHas('court.venue', fn ($venue) => $venue->where('organization_id', $organizationId));
        }

        $bookings = $query->get();
        $totalBookings = $bookings->count();
        $legacyIds = $bookings->pluck('id');

        $mappingsQuery = LegacyBookingMapping::query()->whereIn('legacy_booking_id', $legacyIds);
        if ($organizationId !== null) {
            $mappingsQuery->where('organization_id', $organizationId);
        }
        $mappings = $mappingsQuery->get()->keyBy('legacy_booking_id');

        $orphans = [];
        $collisions = [];
        $mappedCount = 0;
        $pending = [];

        foreach ($bookings as $booking) {
            $venueOrgId = $booking->court?->venue?->organization_id;
            if (! $venueOrgId) {
                $orphans[] = [
                    'legacy_booking_id' => $booking->id,
                    'code' => $booking->code,
                    'reason' => 'Missing venue or organization owner',
                ];

                continue;
            }

            if ($booking->slots->isEmpty()) {
                $orphans[] = [
                    'legacy_booking_id' => $booking->id,
                    'code' => $booking->code,
                    'reason' => 'Booking has zero slots',
                ];

                continue;
            }

            if ($mappings->has($booking->id)) {
                $mapping = $mappings->get($booking->id);
                $expectedChecksum = $this->bookingChecksum($booking);
                if ($mapping->source_checksum !== $expectedChecksum) {
                    $collisions[] = [
                        'legacy_booking_id' => $booking->id,
                        'code' => $booking->code,
                        'reason' => 'Source checksum mismatch against existing mapping',
                    ];
                } else {
                    $mappedCount++;
                }
            } else {
                $pending[] = ['legacy_booking_id' => $booking->id, 'code' => $booking->code, 'reason' => 'No reservation mapping yet'];
            }
        }

        $legacySlotCount = $bookings->flatMap->slots->count();
        $slotMappingsCount = LegacyBookingSlotMapping::query()
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->count();

        return [
            'dry_run' => true,
            'organization_id' => $organizationId,
            'legacy_bookings_total' => $totalBookings,
            'mapped_count' => $mappedCount,
            'pending_count' => count($pending),
            'pending' => $pending,
            'orphan_count' => count($orphans),
            'orphans' => $orphans,
            'collision_count' => count($collisions),
            'collisions' => $collisions,
            'legacy_total_amount' => (int) $bookings->sum('total_amount'),
            'mapped_total_amount' => (int) $mappings->sum('legacy_total_amount'),
            'legacy_slots' => $legacySlotCount,
            'mapped_slots' => $slotMappingsCount,
            'valid' => count($orphans) === 0 && count($collisions) === 0 && $pending === [],
        ];
    }

    public function backfill(?int $organizationId = null, bool $dryRun = false): array
    {
        $audit = $this->audit($organizationId);
        if ($dryRun) {
            $audit['created'] = 0;
            $audit['existing'] = $audit['mapped_count'];

            return $audit;
        }

        // Orphans and checksum collisions are reported, never mapped or thrown.
        $orphans = $audit['orphans'];
        $skipIds = collect($orphans)->merge($audit['collisions'])->pluck('legacy_booking_id')->all();
        $query = Booking::query()->with(['court.venue', 'slots'])->whereNotIn('id', $skipIds)->orderBy('id');
        if ($organizationId !== null) {
            $query->whereHas('court.venue', fn ($venue) => $venue->where('organization_id', $organizationId));
        }

        $bookings = $query->get();
        $created = 0;
        $existing = 0;

        foreach ($bookings as $booking) {
            if (LegacyBookingMapping::query()->where('legacy_booking_id', $booking->id)->exists()) {
                $existing++;

                continue;
            }

            try {
                $this->mapBooking($booking);
                $created++;
            } catch (\RuntimeException $e) { // QueryException included: one bad booking must not abort the run.
                $orphans[] = ['legacy_booking_id' => $booking->id, 'code' => $booking->code, 'reason' => 'Mapping failed: '.$e->getMessage()];
            }
        }

        $result = array_merge($this->validate($organizationId), ['dry_run' => false, 'created' => $created, 'existing' => $existing]);
        $result['valid'] = $result['valid'] && $audit['collisions'] === []; // orphans already break the counts
        $result['orphan_count'] = count($orphans);
        $result['orphans'] = $orphans;
        $result['collision_count'] = count($audit['collisions']);
        $result['collisions'] = $audit['collisions'];

        return $result;
    }

    public function resolveCanonicalReservationId(int $idOrLegacyId, int $organizationId): int
    {
        // 1. Check if it's already a valid canonical reservation for this tenant
        $canonicalExists = Reservation::query()
            ->where('organization_id', $organizationId)
            ->where('id', $idOrLegacyId)
            ->exists();

        if ($canonicalExists) {
            return $idOrLegacyId;
        }

        // 2. Explicitly resolve legacy booking mapping
        $mapping = LegacyBookingMapping::query()
            ->where('organization_id', $organizationId)
            ->where('legacy_booking_id', $idOrLegacyId)
            ->first();

        if ($mapping) {
            return (int) $mapping->reservation_id;
        }

        // 3. Reject unmapped legacy booking - NO numerical ID coincidence fallback!
        abort(422, "Legacy booking {$idOrLegacyId} has no canonical reservation mapping.");
    }

    public function backfillBooking(Booking $booking): LegacyBookingMapping
    {
        $booking->loadMissing(['court.venue', 'slots']);
        $mapping = LegacyBookingMapping::query()->withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->first();
        if ($mapping) {
            $this->assertMapping($booking, $mapping);

            return $mapping;
        }

        return $this->mapBooking($booking);
    }

    public function validate(?int $organizationId = null): array
    {
        $bookings = Booking::query()
            ->when($organizationId !== null, fn ($query) => $query->whereHas('court.venue', fn ($venue) => $venue->where('organization_id', $organizationId)))
            ->with('slots')
            ->get();
        $legacyIds = $bookings->pluck('id');
        $mappings = LegacyBookingMapping::query()->whereIn('legacy_booking_id', $legacyIds)->get();
        $slotIds = $bookings->flatMap->slots->pluck('id');
        $slotMappings = LegacyBookingSlotMapping::query()->whereIn('legacy_booking_slot_id', $slotIds)->get();

        return [
            'legacy_bookings' => $bookings->count(),
            'mapped_bookings' => $mappings->count(),
            'legacy_total_amount' => (int) $bookings->sum('total_amount'),
            'mapped_total_amount' => (int) $mappings->sum('legacy_total_amount'),
            'legacy_slots' => $slotIds->count(),
            'mapped_slots' => $slotMappings->count(),
            'valid' => $bookings->count() === $mappings->count()
                && (int) $bookings->sum('total_amount') === (int) $mappings->sum('legacy_total_amount')
                && $slotIds->count() === $slotMappings->count(),
        ];
    }

    private function mapBooking(Booking $booking): LegacyBookingMapping
    {
        $organizationId = $booking->court?->venue?->organization_id;
        if (! $organizationId) {
            throw new \RuntimeException("Legacy booking {$booking->id} has no organization owner.");
        }

        $checksum = $this->bookingChecksum($booking);
        $active = in_array($booking->status, ['pending_payment', 'confirmed'], true);
        $reservationStatus = $booking->status === 'pending_payment' ? 'held' : ($booking->status === 'confirmed' ? 'confirmed' : 'legacy');

        return DB::transaction(function () use ($booking, $organizationId, $checksum, $active, $reservationStatus): LegacyBookingMapping {
            $reservation = Reservation::forceCreate([
                'created_at' => $booking->created_at,
                'organization_id' => $organizationId,
                'user_id' => $booking->user_id,
                'court_id' => $booking->court_id,
                'code' => 'LEGACY-'.$booking->code,
                'status' => $reservationStatus,
                'currency' => 'VND',
                'total_amount' => $booking->total_amount,
                'hold_expires_at' => $booking->payment_expires_at,
                'confirmed_at' => $booking->status === 'confirmed' ? $booking->created_at : null,
                'payment_model' => $booking->payment_model ?: 'legacy_wallet_v1',
                'metadata' => array_filter([
                    'legacy_booking_id' => $booking->id,
                    'coupon_id' => $booking->coupon_id,
                    'coupon_usage_released_at' => $booking->coupon_usage_released_at?->toIso8601String(),
                    'coupon_usage_snapshot' => $booking->policy_snapshot['coupon_usage_snapshot'] ?? null,
                    'loyalty_snapshot' => $booking->policy_snapshot['loyalty_snapshot'] ?? null,
                    'loyalty_discount_amount' => $booking->loyalty_discount_amount,
                    'loyalty_points_redeemed' => $booking->loyalty_points_redeemed,
                    'booking_policy_snapshot' => $booking->policy_snapshot['venue_booking_policy'] ?? null,
                ], fn ($value) => $value !== null),
            ]);

            $mapping = LegacyBookingMapping::create([
                'organization_id' => $organizationId,
                'reservation_id' => $reservation->id,
                'legacy_booking_id' => $booking->id,
                'legacy_code' => $booking->code,
                'legacy_status' => $booking->status,
                'legacy_total_amount' => $booking->total_amount,
                'legacy_slot_count' => $booking->slots->count(),
                'source_checksum' => $checksum,
                'migrated_at' => now(),
            ]);

            foreach ($booking->slots as $legacySlot) {
                $slot = ReservationSlot::create([
                    'organization_id' => $organizationId,
                    'reservation_id' => $reservation->id,
                    'court_id' => $legacySlot->court_id,
                    'starts_at' => $legacySlot->starts_at,
                    'ends_at' => $legacySlot->ends_at,
                    'unit_price' => $legacySlot->unit_price,
                    'status' => $active ? ($booking->status === 'pending_payment' ? 'held' : 'confirmed') : 'legacy',
                    'snapshot' => ['legacy_booking_id' => $booking->id, 'legacy_slot_id' => $legacySlot->id],
                ]);

                LegacyBookingSlotMapping::create([
                    'organization_id' => $organizationId,
                    'legacy_booking_mapping_id' => $mapping->id,
                    'reservation_slot_id' => $slot->id,
                    'legacy_booking_slot_id' => $legacySlot->id,
                    'source_checksum' => $this->slotChecksum($legacySlot),
                ]);

                ReservationEntitlement::create([
                    'organization_id' => $organizationId,
                    'reservation_id' => $reservation->id,
                    'reservation_slot_id' => $slot->id,
                    'user_id' => $booking->user_id,
                    'court_id' => $legacySlot->court_id,
                    'starts_at' => $legacySlot->starts_at,
                    'ends_at' => $legacySlot->ends_at,
                    'amount' => $legacySlot->unit_price,
                    'status' => $active ? ($booking->status === 'pending_payment' ? 'held' : 'active') : 'legacy',
                ]);
            }

            return $mapping;
        });
    }

    private function assertMapping(Booking $booking, LegacyBookingMapping $mapping): void
    {
        if ($mapping->source_checksum !== $this->bookingChecksum($booking)) {
            throw new \RuntimeException("Legacy booking {$booking->id} changed after it was backfilled.");
        }
    }

    private function bookingChecksum(Booking $booking): string
    {
        return hash('sha256', json_encode([
            'id' => $booking->id,
            'code' => $booking->code,
            'user_id' => $booking->user_id,
            'court_id' => $booking->court_id,
            'total_amount' => $booking->total_amount,
            'status' => $booking->status,
            'slots' => $booking->slots->map(fn ($slot) => $this->slotChecksum($slot))->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function slotChecksum(object $slot): string
    {
        return hash('sha256', json_encode([
            'id' => $slot->id,
            'court_id' => $slot->court_id,
            'starts_at' => (string) $slot->starts_at,
            'ends_at' => (string) $slot->ends_at,
            'unit_price' => $slot->unit_price,
            'status' => $slot->status,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
