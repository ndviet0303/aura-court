<?php

namespace App\Services\Attendance;

use App\Events\CourtStatusUpdated;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\LegacyBookingMapping;
use App\Models\Reservation;
use App\Models\ServiceTab;
use App\Models\User;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;

class AttendanceService
{
    public function __construct(
        private readonly LegacyBookingBackfillService $backfill,
    ) {}

    public function resolveReservation(string|int $codeOrId, int $organizationId): Reservation
    {
        $raw = trim((string) $codeOrId);
        $clean = preg_replace('/^AURA_(?:V2_RESERVATION|BOOKING)_/i', '', $raw);
        $clean = trim($clean);

        // 1. Check direct V2 Reservation in tenant
        $reservation = Reservation::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($clean) {
                $q->where('code', $clean);
                if (is_numeric($clean)) {
                    $q->orWhere('id', (int) $clean);
                }
            })
            ->with(['court.venue', 'slots', 'user'])
            ->first();

        if ($reservation) {
            return $reservation;
        }

        // 2. Check if it's a mapped legacy booking for this tenant
        $mapping = LegacyBookingMapping::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($clean) {
                $q->where('legacy_code', $clean);
                if (is_numeric($clean)) {
                    $q->orWhere('legacy_booking_id', (int) $clean);
                }
            })
            ->with('reservation.court.venue', 'reservation.slots', 'reservation.user')
            ->first();

        if ($mapping && $mapping->reservation) {
            return $mapping->reservation;
        }

        // 3. Check unmapped legacy booking that belongs to this tenant
        $booking = Booking::query()
            ->where(function ($q) use ($clean) {
                $q->where('code', $clean);
                if (is_numeric($clean)) {
                    $q->orWhere('id', (int) $clean);
                }
            })
            ->with(['court.venue', 'slots'])
            ->first();

        if ($booking) {
            $bookingOrgId = $booking->court?->venue?->organization_id;
            if ($bookingOrgId === $organizationId) {
                $backfilledMapping = $this->backfill->backfillBooking($booking);
                $reservation = Reservation::with(['court.venue', 'slots', 'user'])->find($backfilledMapping->reservation_id);
                if ($reservation) {
                    return $reservation;
                }
            }
        }

        // Protected details are not disclosed if belonging to another tenant or not found
        abort(404, 'Booking not found or does not belong to this organization.');
    }

    public function checkIn(User $staff, int $organizationId, string|int $codeOrId, ?string $notes = null): array
    {
        $reservation = $this->resolveReservation($codeOrId, $organizationId);

        // Cancelled bookings cannot be checked in
        if ($reservation->status === 'cancelled') {
            abort(422, 'Cancelled booking cannot be checked in.');
        }

        // Only confirmed bookings can be checked in
        if ($reservation->status !== 'confirmed') {
            abort(422, 'Only confirmed bookings can be checked in.');
        }

        // Current venue-local day validation
        $timezone = $reservation->court?->venue?->organization?->settings['timezone']
            ?? config('app.timezone')
            ?? 'Asia/Ho_Chi_Minh';
        $today = Carbon::now($timezone)->toDateString();

        $slots = $reservation->slots;
        if ($slots->isEmpty()) {
            abort(422, 'Booking has no scheduled slots.');
        }

        $isToday = $slots->contains(function ($slot) use ($timezone, $today) {
            return Carbon::parse($slot->starts_at)->setTimezone($timezone)->toDateString() === $today;
        });

        if (! $isToday) {
            abort(422, 'Booking is not scheduled for the current venue-local day.');
        }

        // Duplicate scan check (idempotent)
        $existing = BookingAttendance::query()
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->first();

        if ($existing) {
            return [
                'attendance' => $existing,
                'reservation' => $reservation,
                'duplicate' => true,
            ];
        }

        $legacyMapping = LegacyBookingMapping::where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->first();

        $attendance = BookingAttendance::create([
            'organization_id' => $organizationId,
            'reservation_id' => $reservation->id,
            'legacy_booking_id' => $legacyMapping?->legacy_booking_id,
            'status' => BookingAttendance::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
            'notes' => $notes,
            'metadata' => [
                'staff_name' => $staff->name,
                'scanned_input' => (string) $codeOrId,
            ],
        ]);

        // Connect attendance to operational court status
        $court = $reservation->court;
        if ($court && $court->venue_id) {
            CourtStatusUpdated::dispatch(
                venueId: (int) $court->venue_id,
                courtId: (int) $court->id,
                status: 'occupied',
                metadata: [
                    'attendance_id' => $attendance->id,
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->code,
                    'action' => 'check_in',
                ]
            );
        }

        return [
            'attendance' => $attendance,
            'reservation' => $reservation,
            'duplicate' => false,
        ];
    }

    public function checkOut(User $staff, int $organizationId, string|int $codeOrId, ?string $notes = null): array
    {
        $reservation = $this->resolveReservation($codeOrId, $organizationId);

        $attendance = BookingAttendance::query()
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->first();

        if (! $attendance || ! in_array($attendance->status, [BookingAttendance::STATUS_CHECKED_IN, BookingAttendance::STATUS_CHECKED_OUT], true)) {
            abort(422, 'Booking has not been checked in.');
        }

        // Idempotent duplicate check-out
        if ($attendance->status === BookingAttendance::STATUS_CHECKED_OUT) {
            return [
                'attendance' => $attendance,
                'reservation' => $reservation,
                'duplicate' => true,
            ];
        }

        // Block checkout if unpaid service tabs exist (Task 7.6)
        $unpaidTabsQuery = ServiceTab::query()
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->where('status', 'unpaid');

        if ($unpaidTabsQuery->exists()) {
            $unpaidTotal = (int) $unpaidTabsQuery->sum('total_amount');
            abort(422, "Cannot check out: reservation has unpaid service tab of {$unpaidTotal} VND that must be settled first.");
        }

        $attendance->update([
            'status' => BookingAttendance::STATUS_CHECKED_OUT,
            'checked_out_at' => now(),
            'checked_out_by' => $staff->id,
            'notes' => $notes ? trim(($attendance->notes ? $attendance->notes . "\n" : '') . $notes) : $attendance->notes,
        ]);

        // Connect attendance to operational court status
        $court = $reservation->court;
        if ($court && $court->venue_id) {
            CourtStatusUpdated::dispatch(
                venueId: (int) $court->venue_id,
                courtId: (int) $court->id,
                status: 'available',
                metadata: [
                    'attendance_id' => $attendance->id,
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->code,
                    'action' => 'check_out',
                ]
            );
        }

        return [
            'attendance' => $attendance->fresh(),
            'reservation' => $reservation,
            'duplicate' => false,
        ];
    }

    public function getAttendance(int $organizationId, string|int $codeOrId): array
    {
        $reservation = $this->resolveReservation($codeOrId, $organizationId);
        $attendance = BookingAttendance::query()
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->with(['checkedInBy:id,name', 'checkedOutBy:id,name'])
            ->first();

        return [
            'attendance' => $attendance,
            'reservation' => $reservation,
        ];
    }
}
