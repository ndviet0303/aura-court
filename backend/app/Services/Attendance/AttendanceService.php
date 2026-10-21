<?php

namespace App\Services\Attendance;

use App\Events\CourtStatusUpdated;
use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\LegacyBookingMapping;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\ServiceTab;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function resolveReservation(string|int $codeOrId, int $organizationId): Reservation
    {
        $raw = trim((string) $codeOrId);

        // Check if codeOrId refers to an entitlement pass
        if (preg_match('/^(?:AURA_ENTITLEMENT_|ENT_)(\d+)$/i', $raw, $matches)) {
            $entitlement = ReservationEntitlement::query()
                ->where('organization_id', $organizationId)
                ->whereKey((int) $matches[1])
                ->first();
            if ($entitlement) {
                abort_if($entitlement->status === 'transferred', 422, 'This entitlement has been transferred and check-in pass has been revoked.');
                abort_if($entitlement->status !== 'active', 422, 'Only active entitlements can be checked in.');

                return $entitlement->reservation()->with(['court.venue', 'slots', 'user'])->firstOrFail();
            }
        }

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

        // Protected details are not disclosed if belonging to another tenant or not found
        abort(404, 'Booking not found or does not belong to this organization.');
    }

    public function checkIn(
        User $staff,
        int $organizationId,
        string|int $codeOrId,
        ?string $notes = null,
        ?User $customer = null,
        ?int $venueId = null
    ): array {
        $reservation = $this->resolveReservation($codeOrId, $organizationId);
        $this->assertCanOperate($staff, $reservation, 'Unauthorized to check in at this venue.');

        // 1. Correct venue validation (Task 8.2 / D49)
        if ($venueId !== null && (int) $reservation->court?->venue_id !== (int) $venueId) {
            abort(422, 'Booking does not belong to this venue.');
        }

        // 2. Holder / authorized representative check (Task 8.2 / D49)
        if ($customer !== null) {
            $hasActiveEntitlement = $reservation->entitlements()
                ->where('user_id', $customer->id)
                ->where('status', 'active')
                ->exists();

            $isOwner = (int) $reservation->user_id === (int) $customer->id;
            $isRepresentative = (int) data_get($reservation->metadata, 'representative_user_id') === (int) $customer->id;

            if (! $hasActiveEntitlement && ! $isOwner && ! $isRepresentative) {
                abort(422, 'Customer does not hold active check-in authority for this booking.');
            }
        }

        // 3. Status and payment eligibility (Task 8.2 / D49)
        if ($reservation->status === 'cancelled') {
            abort(422, 'Cancelled booking cannot be checked in.');
        }

        if ($reservation->status !== 'confirmed') {
            abort(422, 'Only confirmed bookings can be checked in.');
        }

        // 4. Current venue-local day validation
        $timezone = $reservation->court?->venue?->organization?->settings['timezone']
            ?? config('app.timezone')
            ?? 'Asia/Ho_Chi_Minh';
        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $slots = $reservation->slots;
        if ($slots->isEmpty()) {
            abort(422, 'Booking has no scheduled slots.');
        }

        $isToday = $slots->contains(function ($slot) use ($timezone, $today) {
            return $slot->starts_at?->copy()->setTimezone($timezone)->toDateString() === $today;
        });

        if (! $isToday) {
            abort(422, 'Booking is not scheduled for the current venue-local day.');
        }

        // 5. Arrival window enforcement from VenueBookingPolicy (Task 8.2 / D49)
        $firstSlot = $slots->sortBy('starts_at')->first();
        $lastSlot = $slots->sortByDesc('ends_at')->first();
        $policy = $reservation->court?->venue?->currentBookingPolicy;
        $earlyMinutes = (int) data_get($policy?->settings, 'arrival.early_minutes', 15);
        $noShowMinutes = (int) data_get($policy?->settings, 'arrival.no_show_after_minutes', 30);
        $slotStart = $firstSlot->starts_at->copy()->setTimezone($timezone);
        $slotEnd = $lastSlot->ends_at->copy()->setTimezone($timezone);

        if ($now->lessThan($slotStart->copy()->subMinutes($earlyMinutes))) {
            abort(422, 'Too early to check in for this booking.');
        }

        if ($now->greaterThan($slotStart->copy()->addMinutes($noShowMinutes)) && $now->greaterThan($slotStart)) {
            // If arrival window has fully expired beyond grace period
            if ($now->greaterThan($slotEnd) || $now->greaterThan($slotStart->copy()->addMinutes($noShowMinutes))) {
                abort(422, 'Arrival window has expired for this booking.');
            }
        }

        // 6. Court operational readiness (Task 8.2 / D49)
        $court = $reservation->court;
        if ($court) {
            if ($court->status !== Court::STATUS_ACTIVE) {
                abort(422, 'Court is currently under maintenance.');
            }

            $nowUtc = $now->copy()->utc();
            $hasBlock = CourtBlock::query()
                ->where('court_id', $court->id)
                ->where('starts_at', '<', $nowUtc)
                ->where('ends_at', '>', $nowUtc)
                ->exists();
            if ($hasBlock) {
                abort(422, 'Court is currently under maintenance.');
            }

            $cachedStatus = Cache::get("court:{$court->id}:operational_status");
            if ($cachedStatus === 'awaiting_inspection') {
                abort(422, 'Court is currently awaiting readiness inspection.');
            }

            $otherOccupied = BookingAttendance::query()
                ->where('organization_id', $organizationId)
                ->where('status', BookingAttendance::STATUS_CHECKED_IN)
                ->where('reservation_id', '!=', $reservation->id)
                ->whereHas('reservation', fn ($q) => $q->where('court_id', $court->id))
                ->exists();

            if ($otherOccupied) {
                abort(422, 'Court is currently occupied.');
            }
        }

        // 7. Duplicate scan check (idempotent, Task 8.3 / D50)
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
                'operational_status' => 'in_use',
            ],
        ]);

        // Project operational status to reservation metadata (Task 8.3 / D50)
        $resMeta = (array) ($reservation->metadata ?? []);
        $resMeta['operational_status'] = 'in_use';
        $reservation->update(['metadata' => $resMeta]);

        // Connect attendance to operational court status
        if ($court && $court->venue_id) {
            Cache::put("court:{$court->id}:operational_status", 'occupied', now()->addHours(4));
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
            'reservation' => $reservation->fresh(),
            'duplicate' => false,
        ];
    }

    public function checkOut(User $staff, int $organizationId, string|int $codeOrId, ?string $notes = null): array
    {
        $reservation = $this->resolveReservation($codeOrId, $organizationId);
        $this->assertCanOperate($staff, $reservation, 'Unauthorized to check out at this venue.');

        $attendance = BookingAttendance::query()
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->first();

        if (! $attendance || ! in_array($attendance->status, [BookingAttendance::STATUS_CHECKED_IN, BookingAttendance::STATUS_CHECKED_OUT], true)) {
            abort(422, 'Booking has not been checked in.');
        }

        // Idempotent duplicate check-out (Task 8.3 / D50)
        if ($attendance->status === BookingAttendance::STATUS_CHECKED_OUT) {
            return [
                'attendance' => $attendance,
                'reservation' => $reservation,
                'duplicate' => true,
            ];
        }

        // Unpaid tabs persist as separate receivables without blocking checkout (Task 8.4 / D51)
        $unpaidTabs = ServiceTab::query()
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->where('status', 'unpaid')
            ->get();

        $unpaidTotal = (int) $unpaidTabs->sum('total_amount');

        $attendanceMeta = (array) ($attendance->metadata ?? []);
        $attendanceMeta['unpaid_receivable'] = $unpaidTotal;
        $attendanceMeta['operational_status'] = 'completed';

        $attendance->update([
            'status' => BookingAttendance::STATUS_CHECKED_OUT,
            'checked_out_at' => now(),
            'checked_out_by' => $staff->id,
            'notes' => $notes ? trim(($attendance->notes ? $attendance->notes."\n" : '').$notes) : $attendance->notes,
            'metadata' => $attendanceMeta,
        ]);

        // Project operational status to reservation metadata (Task 8.3 / D50)
        $resMeta = (array) ($reservation->metadata ?? []);
        $resMeta['operational_status'] = 'completed';
        $reservation->update(['metadata' => $resMeta]);

        // Move court to awaiting_inspection, NOT automatically available (Task 8.5 / D52)
        $court = $reservation->court;
        if ($court && $court->venue_id) {
            Cache::put("court:{$court->id}:operational_status", 'awaiting_inspection', now()->addHours(24));
            CourtStatusUpdated::dispatch(
                venueId: (int) $court->venue_id,
                courtId: (int) $court->id,
                status: 'awaiting_inspection',
                metadata: [
                    'attendance_id' => $attendance->id,
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->code,
                    'action' => 'check_out',
                    'unpaid_receivable' => $unpaidTotal,
                ]
            );
        }

        return [
            'attendance' => $attendance->fresh(),
            'reservation' => $reservation->fresh(),
            'duplicate' => false,
            'unpaid_receivable' => $unpaidTotal,
        ];
    }

    public function confirmCourtReady(User $staff, int $organizationId, int $courtId, ?string $notes = null): array
    {
        $court = Court::query()->whereKey($courtId)->firstOrFail();
        abort_unless((int) $court->venue?->organization_id === (int) $organizationId, 404, 'Court not found in organization.');
        abort_unless($staff->canOperateVenue((int) $court->venue_id), 403, 'Unauthorized to perform court readiness inspection.');

        Cache::put("court:{$court->id}:operational_status", 'available', now()->addHours(4));

        CourtStatusUpdated::dispatch(
            venueId: (int) $court->venue_id,
            courtId: (int) $court->id,
            status: 'available',
            metadata: [
                'inspected_by' => $staff->id,
                'staff_name' => $staff->name,
                'notes' => $notes,
                'action' => 'confirm_ready',
            ]
        );

        return [
            'court_id' => $court->id,
            'status' => 'available',
            'inspected_by' => $staff->id,
            'inspected_at' => now()->toIso8601String(),
        ];
    }

    public function markNoShow(User $staff, int $organizationId, string|int $codeOrId, ?string $reason = null): array
    {
        $reservation = $this->resolveReservation($codeOrId, $organizationId);
        $this->assertCanOperate($staff, $reservation, 'Unauthorized to record no-show.');
        abort_unless($reservation->status === 'confirmed', 422, 'Only confirmed bookings can be marked no-show.');

        $existing = BookingAttendance::query()
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->first();

        abort_if($existing && $existing->status === BookingAttendance::STATUS_CHECKED_IN, 422, 'Cannot mark no-show for a checked-in booking.');

        // Verify arrival window has expired (Task 8.6 / D53)
        $firstSlot = $reservation->slots->sortBy('starts_at')->first();
        $timezone = $reservation->court?->venue?->organization?->settings['timezone'] ?? config('app.timezone') ?? 'Asia/Ho_Chi_Minh';
        $policy = $reservation->court?->venue?->currentBookingPolicy;
        $noShowMinutes = (int) data_get($policy?->settings, 'arrival.no_show_after_minutes', 30);
        $slotStart = $firstSlot->starts_at->copy()->setTimezone($timezone);
        $now = Carbon::now($timezone);
        $windowExpired = ! $now->lessThan($slotStart->copy()->addMinutes($noShowMinutes));

        abort_if(! $windowExpired, 422, 'Cannot mark no-show: arrival window has not yet expired.');

        $attendance = BookingAttendance::updateOrCreate([
            'organization_id' => $organizationId,
            'reservation_id' => $reservation->id,
        ], [
            'status' => BookingAttendance::STATUS_NO_SHOW,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
            'notes' => $reason ?? 'Customer no-show recorded under venue policy.',
            'metadata' => [
                'actor_id' => $staff->id,
                'staff_name' => $staff->name,
                'reason' => $reason,
                'operational_status' => 'no_show',
            ],
        ]);

        $resMeta = (array) ($reservation->metadata ?? []);
        $resMeta['operational_status'] = 'no_show';
        $reservation->update(['metadata' => $resMeta]);

        $court = $reservation->court;
        if ($court && $court->venue_id) {
            Cache::put("court:{$court->id}:operational_status", 'available', now()->addHours(4));
            CourtStatusUpdated::dispatch(
                venueId: (int) $court->venue_id,
                courtId: (int) $court->id,
                status: 'available',
                metadata: [
                    'reservation_id' => $reservation->id,
                    'action' => 'no_show',
                ]
            );
        }

        return [
            'attendance' => $attendance,
            'reservation' => $reservation->fresh(),
        ];
    }

    public function reassignCourt(User $staff, int $organizationId, string|int $codeOrId, int $newCourtId, string $reason): array
    {
        abort_if(trim($reason) === '', 422, 'Reason is required for incident reassignment.');

        $reservation = $this->resolveReservation($codeOrId, $organizationId);
        $this->assertCanOperate($staff, $reservation, 'Unauthorized to reassign court.');
        $oldCourtId = $reservation->court_id;
        abort_if($oldCourtId === $newCourtId, 422, 'New court must be different from current court.');

        $newCourt = Court::query()->whereKey($newCourtId)->firstOrFail();
        abort_unless((int) $newCourt->venue_id === (int) $reservation->court?->venue_id, 422, 'Target court must belong to the same venue.');
        abort_unless($newCourt->status === Court::STATUS_ACTIVE, 422, 'Target court is currently under maintenance.');

        // Check if new court has overlapping slots during remaining booking time
        foreach ($reservation->slots as $slot) {
            $slotStartUtc = ($slot->starts_at instanceof Carbon ? $slot->starts_at->copy() : Carbon::parse($slot->starts_at, 'UTC'))->utc();
            $slotEndUtc = ($slot->ends_at instanceof Carbon ? $slot->ends_at->copy() : Carbon::parse($slot->ends_at, 'UTC'))->utc();
            $conflict = ReservationSlot::query()
                ->where('court_id', $newCourtId)
                ->whereIn('status', ['held', 'confirmed', 'active'])
                ->where('reservation_id', '!=', $reservation->id)
                ->where('starts_at', '<', $slotEndUtc)
                ->where('ends_at', '>', $slotStartUtc)
                ->exists();
            abort_if($conflict, 409, 'Target court is already booked during this time interval.');
        }

        return DB::transaction(function () use ($reservation, $newCourt, $oldCourtId, $staff, $reason) {
            $reservation->update(['court_id' => $newCourt->id]);
            $reservation->slots()->update(['court_id' => $newCourt->id]);
            $reservation->entitlements()->update(['court_id' => $newCourt->id]);

            // Track incident and reassignment history in metadata
            $meta = (array) ($reservation->metadata ?? []);
            $incidents = (array) ($meta['incidents'] ?? []);
            $incidents[] = [
                'type' => 'court_reassignment',
                'from_court_id' => $oldCourtId,
                'to_court_id' => $newCourt->id,
                'reason' => $reason,
                'staff_id' => $staff->id,
                'at' => now()->toIso8601String(),
            ];
            $meta['incidents'] = $incidents;
            $reservation->update(['metadata' => $meta]);

            // Update operational statuses
            Cache::put("court:{$oldCourtId}:operational_status", 'awaiting_inspection', now()->addHours(2));
            Cache::put("court:{$newCourt->id}:operational_status", 'occupied', now()->addHours(4));

            CourtStatusUpdated::dispatch(
                venueId: (int) $newCourt->venue_id,
                courtId: (int) $oldCourtId,
                status: 'awaiting_inspection',
                metadata: ['action' => 'incident_reassigned_from', 'reason' => $reason]
            );

            CourtStatusUpdated::dispatch(
                venueId: (int) $newCourt->venue_id,
                courtId: (int) $newCourt->id,
                status: 'occupied',
                metadata: ['action' => 'incident_reassigned_to', 'reservation_id' => $reservation->id]
            );

            return [
                'reservation' => $reservation->fresh(['court', 'slots', 'entitlements']),
                'reassigned' => true,
                'from_court_id' => $oldCourtId,
                'to_court_id' => $newCourt->id,
            ];
        });
    }

    /** BR-21: operations run only at a venue the actor manages or is assigned to. */
    private function assertCanOperate(User $staff, Reservation $reservation, string $message): void
    {
        $venueId = $reservation->court?->venue_id;
        abort_unless($venueId !== null && $staff->canOperateVenue((int) $venueId), 403, $message);
    }

    public function getAttendance(User $staff, int $organizationId, string|int $codeOrId): array
    {
        $reservation = $this->resolveReservation($codeOrId, $organizationId);
        $this->assertCanOperate($staff, $reservation, 'Unauthorized to view bookings at this venue.');
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
