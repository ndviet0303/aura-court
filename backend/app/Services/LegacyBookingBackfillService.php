<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\LegacyBookingSlotMapping;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use Illuminate\Support\Facades\DB;

class LegacyBookingBackfillService
{
    public function backfill(?int $organizationId = null): array
    {
        $query = Booking::query()->with(['court.venue', 'slots'])->orderBy('id');
        if ($organizationId !== null) {
            $query->whereHas('court.venue', fn ($venue) => $venue->where('organization_id', $organizationId));
        }

        $bookings = $query->get();
        $created = 0;
        $existing = 0;
        foreach ($bookings as $booking) {
            $mapping = LegacyBookingMapping::query()->where('legacy_booking_id', $booking->id)->first();
            if ($mapping) {
                $this->assertMapping($booking, $mapping);
                $existing++;

                continue;
            }

            $this->mapBooking($booking);
            $created++;
        }

        $result = $this->validate($organizationId);
        $result['created'] = $created;
        $result['existing'] = $existing;

        return $result;
    }

    public function backfillBooking(Booking $booking): LegacyBookingMapping
    {
        $booking->loadMissing(['court.venue', 'slots']);
        $mapping = LegacyBookingMapping::query()->where('legacy_booking_id', $booking->id)->first();
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

    public function project(Reservation $reservation): array
    {
        $mapping = $reservation->legacyMapping()->firstOrFail();

        return [
            'id' => $mapping->legacy_booking_id,
            'code' => $mapping->legacy_code,
            'status' => $mapping->legacy_status,
            'total_amount' => $mapping->legacy_total_amount,
            'reservation_code' => $reservation->code,
            'slots' => $reservation->slots->map(fn (ReservationSlot $slot) => [
                'id' => $slot->id,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'unit_price' => $slot->unit_price,
            ])->values(),
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
            $reservation = Reservation::create([
                'organization_id' => $organizationId,
                'user_id' => $booking->user_id,
                'court_id' => $booking->court_id,
                'code' => 'LEGACY-'.$booking->code,
                'status' => $reservationStatus,
                'currency' => 'VND',
                'total_amount' => $booking->total_amount,
                'hold_expires_at' => $booking->payment_expires_at,
                'confirmed_at' => $booking->status === 'confirmed' ? $booking->created_at : null,
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
