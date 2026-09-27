<?php

namespace App\Services\Reservation;

use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\MatchPost;
use App\Models\ReservationSlot;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative, under-lock guard for a slot selection on a single court.
 *
 * This is the single source of truth for "Active inventory exclusivity"
 * (reservations-and-pricing spec): interval duration, venue calendar,
 * book-ahead horizon, continuity policy and true-interval overlap are checked
 * atomically at allocation and replacement, so V1/V2/reschedule reject the same
 * selections consistently.
 *
 * Overlap/calendar/past always apply. Horizon/duration/continuity apply only
 * when the venue's live booking policy defines them; venues without a policy
 * are uncapped for those three while still excluded from overlaps.
 *
 * Slot-start alignment is intentionally NOT enforced here: each caller keeps its
 * own per-version alignment rule.
 */
class SlotSelectionPolicy
{
    public function __construct(private readonly VenueOperatingCalendar $calendar) {}

    /**
     * @param  array<int, array{start: \DateTimeInterface|string, end: \DateTimeInterface|string}>  $intervals
     * @param  int|null  $excludeReservationId  reservation whose own slots are ignored (reschedule replacement)
     * @param  array<int, int>  $excludeBookingSlotIds  legacy booking-slot ids to ignore (reschedule original projection)
     */
    public function assertAllowed(
        Court $court,
        array $intervals,
        ?int $excludeReservationId = null,
        array $excludeBookingSlotIds = [],
    ): void {
        if ($intervals === []) {
            return;
        }

        $court->loadMissing(['venue', 'sportZone']);
        $venue = $court->venue;
        $timezone = $venue?->timezone ?: config('app.timezone');

        /** @var Collection<int, array{start: Carbon, end: Carbon}> $slots */
        $slots = collect($intervals)
            ->map(fn (array $interval): array => [
                'start' => $this->toCarbon($interval['start']),
                'end' => $this->toCarbon($interval['end']),
            ])
            ->sortBy(fn (array $slot): int => $slot['start']->getTimestamp())
            ->values();

        // 1. Past-rejection (re-asserted under lock).
        foreach ($slots as $slot) {
            if ($slot['start']->copy()->setTimezone($timezone)->isPast()) {
                throw ValidationException::withMessages(['slots' => 'Không thể đặt slot trong quá khứ.']);
            }
        }

        // 2. Venue operating calendar.
        foreach ($slots as $slot) {
            if (! $this->calendar->isOpen($court, $slot['start'], $slot['end'])) {
                throw ValidationException::withMessages(['slots' => 'Khung giờ đã chọn nằm ngoài lịch hoạt động của sân.']);
            }
        }

        // 3. Court maintenance blocks (true-interval).
        foreach ($slots as $slot) {
            $blocked = CourtBlock::query()
                ->where('court_id', $court->id)
                ->where('starts_at', '<', $slot['end']->copy()->utc())
                ->where('ends_at', '>', $slot['start']->copy()->utc())
                ->exists();
            if ($blocked) {
                abort(409, 'Sân đang bị khóa trong khoảng thời gian đã chọn.');
            }
        }

        $booking = $this->livePolicyBooking($venue);

        // 4. Book-ahead horizon (datetime-based, venue timezone).
        if ($booking !== null && isset($booking['max_days_ahead'])) {
            $horizon = Carbon::now($timezone)->addDays((int) $booking['max_days_ahead']);
            foreach ($slots as $slot) {
                if ($slot['start']->copy()->setTimezone($timezone)->greaterThan($horizon)) {
                    throw ValidationException::withMessages(['slots' => 'Khung giờ đã chọn vượt quá thời hạn đặt trước cho phép của sân.']);
                }
            }
        }

        // 5. Total-session duration bounds (count × slot_duration).
        if ($booking !== null) {
            $totalMinutes = (int) $slots->sum(fn (array $slot): int => $slot['start']->diffInMinutes($slot['end']));
            if (isset($booking['min_duration_minutes']) && $totalMinutes < (int) $booking['min_duration_minutes']) {
                throw ValidationException::withMessages(['slots' => 'Tổng thời lượng đặt sân thấp hơn mức tối thiểu cho phép của sân.']);
            }
            if (isset($booking['max_duration_minutes']) && $totalMinutes > (int) $booking['max_duration_minutes']) {
                throw ValidationException::withMessages(['slots' => 'Tổng thời lượng đặt sân vượt quá mức tối đa cho phép của sân.']);
            }
        }

        // 6. Policy-driven continuity (contiguous, no gaps).
        if ($booking !== null && ! empty($booking['require_continuity']) && $slots->count() > 1) {
            for ($i = 1; $i < $slots->count(); $i++) {
                if (! $slots[$i]['start']->equalTo($slots[$i - 1]['end'])) {
                    throw ValidationException::withMessages(['slots' => 'Các slot phải liên tiếp trên cùng sân.']);
                }
            }
        }

        // 7. True-interval overlap vs active V1 and V2 inventory (start < otherEnd AND end > otherStart).
        foreach ($slots as $slot) {
            $startUtc = $slot['start']->copy()->utc();
            $endUtc = $slot['end']->copy()->utc();

            $v1Conflict = BookingSlot::query()
                ->where('court_id', $court->id)
                ->where('starts_at', '<', $endUtc)
                ->where('ends_at', '>', $startUtc)
                ->whereIn('status', ['held', 'confirmed'])
                ->when($excludeBookingSlotIds !== [], fn ($query) => $query->whereNotIn('id', $excludeBookingSlotIds))
                ->exists();
            if ($v1Conflict) {
                abort(409, 'The selected court-time is no longer available.');
            }

            $v2Conflict = ReservationSlot::query()
                ->where('court_id', $court->id)
                ->where('starts_at', '<', $endUtc)
                ->where('ends_at', '>', $startUtc)
                ->whereIn('status', ['held', 'confirmed'])
                ->when($excludeReservationId !== null, fn ($query) => $query->where('reservation_id', '!=', $excludeReservationId))
                ->whereHas('reservation', fn ($reservation) => $reservation->whereIn('status', ['held', 'confirmed']))
                ->exists();
            if ($v2Conflict) {
                abort(409, 'The selected court-time is no longer available.');
            }

            $matchConflict = MatchPost::query()
                ->where('court_id', $court->id)
                ->where('starts_at', '<', $endUtc)
                ->where('ends_at', '>', $startUtc)
                ->whereIn('status', ['open', 'active'])
                ->where(function ($q) use ($excludeReservationId) {
                    $q->where('is_owner_hosted', true)
                        ->orWhere(function ($sub) use ($excludeReservationId) {
                            $sub->whereNull('reservation_id');
                            if ($excludeReservationId !== null) {
                                $sub->orWhere('reservation_id', '!=', $excludeReservationId);
                            }
                        });
                })
                ->exists();
            if ($matchConflict) {
                abort(409, 'Court interval is reserved for a community/hosted activity.');
            }
        }
    }

    /**
     * The venue's live booking-policy `booking` settings, independent of the
     * direct-owner payment flag. Null when the venue has no captured policy.
     *
     * @return array<string, mixed>|null
     */
    private function livePolicyBooking(?Venue $venue): ?array
    {
        $policy = $venue?->currentBookingPolicy()->first();
        if ($policy === null) {
            return null;
        }

        $booking = data_get((array) $policy->settings, 'booking');

        return is_array($booking) ? $booking : null;
    }

    private function toCarbon(\DateTimeInterface|string $value): Carbon
    {
        return $value instanceof \DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($value))
            : Carbon::parse($value);
    }
}
