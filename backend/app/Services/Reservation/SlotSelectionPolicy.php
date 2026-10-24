<?php

namespace App\Services\Reservation;

use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\TournamentCourtAllocation;
use App\Models\Venue;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative, under-lock guard for a slot selection on a single court.
 *
 * This is the single source of truth for "Active inventory exclusivity"
 * (reservations-and-pricing spec): interval duration, venue calendar,
 * book-ahead horizon, continuity and true-interval overlap are checked
 * atomically at allocation, so every booking path rejects the same selections
 * consistently.
 *
 * Overlap/calendar/past/continuity always apply. Horizon and duration apply only
 * when the venue's live booking policy defines them; venues without a policy
 * are uncapped for those two while still excluded from overlaps. A hold past
 * its payment deadline does not count as an overlap.
 *
 * Slot-start alignment is intentionally NOT enforced here: each caller keeps its
 * own per-version alignment rule.
 *
 * An operator command on an affected booking (design D8) passes the booking as $changing: its own slots do
 * not overlap it, its captured policy replaces the live one, and duration bounds are skipped.
 * $allowStarted accepts a slot already in play (move-court), rejecting only one that has ended.
 */
class SlotSelectionPolicy
{
    public function __construct(private readonly VenueOperatingCalendar $calendar) {}

    /**
     * @param  array<int, array{start: \DateTimeInterface|string, end: \DateTimeInterface|string}>  $intervals
     */
    public function assertAllowed(Court $court, array $intervals, ?Reservation $changing = null, bool $allowStarted = false): void
    {
        if ($intervals === []) {
            return;
        }

        $court->loadMissing(['venue']);
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
            if ($slot[$allowStarted ? 'end' : 'start']->copy()->setTimezone($timezone)->isPast()) {
                throw ValidationException::withMessages(['slots' => 'Không thể đặt giờ đã qua.']);
            }
        }

        // 2. Venue operating calendar.
        foreach ($slots as $slot) {
            if (! $this->calendar->isOpen($court, $slot['start'], $slot['end'])) {
                throw ValidationException::withMessages(['slots' => 'Khung giờ đã chọn nằm ngoài lịch hoạt động của sân.']);
            }
        }

        // 3. Stops of the court or its venue (D5).
        foreach ($slots as $slot) {
            $this->assertNoStop($court, $slot['start'], $slot['end']);
        }

        $booking = $changing === null ? $this->livePolicyBooking($venue) : $this->capturedPolicyBooking($changing);

        // 4. Book-ahead horizon (datetime-based, venue timezone).
        if ($booking !== null && isset($booking['max_days_ahead'])) {
            $horizon = Carbon::now($timezone)->addDays((int) $booking['max_days_ahead']);
            foreach ($slots as $slot) {
                if ($slot['start']->copy()->setTimezone($timezone)->greaterThan($horizon)) {
                    throw ValidationException::withMessages(['slots' => 'Khung giờ đã chọn vượt quá thời hạn đặt trước cho phép của sân.']);
                }
            }
        }

        // 5. Total-session duration bounds (count × slot_duration); an operator change keeps the booking's duration.
        if ($booking !== null && $changing === null) {
            $totalMinutes = (int) $slots->sum(fn (array $slot): int => $slot['start']->diffInMinutes($slot['end']));
            if (isset($booking['min_duration_minutes']) && $totalMinutes < (int) $booking['min_duration_minutes']) {
                throw ValidationException::withMessages(['slots' => 'Tổng thời lượng đặt sân thấp hơn mức tối thiểu cho phép của sân.']);
            }
            if (isset($booking['max_duration_minutes']) && $totalMinutes > (int) $booking['max_duration_minutes']) {
                throw ValidationException::withMessages(['slots' => 'Tổng thời lượng đặt sân vượt quá mức tối đa cho phép của sân.']);
            }
        }

        // 6. Continuity (contiguous, no gaps); BR-73 leaves venues no setting to turn it off.
        if ($slots->count() > 1) {
            for ($i = 1; $i < $slots->count(); $i++) {
                if (! $slots[$i]['start']->equalTo($slots[$i - 1]['end'])) {
                    throw ValidationException::withMessages(['slots' => 'Các giờ đặt phải liền nhau trên cùng sân.']);
                }
            }
        }

        // 7. True-interval overlap vs active reservation inventory (start < otherEnd AND end > otherStart).
        foreach ($slots as $slot) {
            $startUtc = $slot['start']->copy()->utc();
            $endUtc = $slot['end']->copy()->utc();

            $reservationConflict = ReservationSlot::query()
                ->where('court_id', $court->id)
                ->where('starts_at', '<', $endUtc)
                ->where('ends_at', '>', $startUtc)
                ->whereIn('status', ['held', 'confirmed'])
                ->when($changing, fn ($q) => $q->where('reservation_id', '!=', $changing->id))
                ->whereHas('reservation', fn ($reservation) => $reservation->blocksInventory())
                ->exists();
            if ($reservationConflict) {
                abort(409, 'The selected court-time is no longer available.');
            }
        }
    }

    /** D5: a stop of the court or its venue blocks [starts_at, ended_at ?? infinity); callers read it after their court and venue locks. */
    public function assertNoStop(Court $court, CarbonInterface $start, CarbonInterface $end): void
    {
        $stopped = OperationalStop::query()->affectingCourt($court, (int) $court->venue_id)->overlapping($start, $end)->exists();
        abort_if($stopped, 409, 'Sân đang tạm ngừng hoặc ngừng kinh doanh trong khoảng thời gian đã chọn.');
        // BR-152: court time reserved for a tournament is not for ordinary bookings.
        abort_if(TournamentCourtAllocation::blocks((int) $court->id, $start, $end), 409, 'Sân đã được dành cho giải đấu trong khoảng thời gian đã chọn.');
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

    /** @return array<string, mixed>|null the booking settings captured on the reservation when it was made */
    private function capturedPolicyBooking(Reservation $reservation): ?array
    {
        $booking = data_get($reservation->metadata, 'booking_policy_snapshot.settings.booking');

        return is_array($booking) ? $booking : null;
    }

    private function toCarbon(\DateTimeInterface|string $value): Carbon
    {
        return $value instanceof \DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($value))
            : Carbon::parse($value);
    }
}
