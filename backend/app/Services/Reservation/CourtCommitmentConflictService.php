<?php

namespace App\Services\Reservation;

use App\Exceptions\CommitmentConflictException;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\MatchPost;
use App\Models\ReservationSlot;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CourtCommitmentConflictService
{
    public function __construct(private readonly VenueOperatingCalendar $calendar) {}

    public function assertSchedulePreservesCommitments(Venue $venue): void
    {
        $courtIds = $venue->courts()->pluck('id')->all();
        if ($courtIds === []) {
            return;
        }

        $conflicts = $this->commitments($courtIds, Carbon::now('UTC'))
            ->filter(fn (array $commitment): bool => ! $this->calendar->isOpen(
                $commitment['court'],
                $commitment['starts_at'],
                $commitment['ends_at'],
            ))
            ->pluck('reference')
            ->unique()
            ->values()
            ->all();

        $this->abortOnConflicts($conflicts, 'Lịch hoạt động mới xung đột với các lượt đặt hiện có.');
    }

    public function assertNoMatchPostConflict(int $courtId, Carbon|string $startsAt, Carbon|string $endsAt, ?int $excludeReservationId = null): void
    {
        $startUtc = Carbon::parse($startsAt)->utc();
        $endUtc = Carbon::parse($endsAt)->utc();

        $hasConflict = MatchPost::query()
            ->where('court_id', $courtId)
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

        if ($hasConflict) {
            abort(409, 'Court interval is reserved for a community/hosted activity.');
        }
    }

    public function assertBlockPreservesCommitments(Court $court, Carbon $startsAt, Carbon $endsAt, ?int $excludeReservationId = null): void
    {
        $this->assertNoMatchPostConflict($court->id, $startsAt, $endsAt, $excludeReservationId);

        $conflicts = $this->commitments([$court->id], Carbon::now('UTC'), $startsAt, $endsAt, $excludeReservationId)
            ->pluck('reference')
            ->unique()
            ->values()
            ->all();

        $this->abortOnConflicts($conflicts, 'Khung giờ đóng sân bị trùng với các lịch đặt đã xác nhận.');
    }

    private function commitments(array $courtIds, Carbon $notBefore, ?Carbon $overlapStart = null, ?Carbon $overlapEnd = null, ?int $excludeReservationId = null): Collection
    {
        $notBeforeUtc = $notBefore->copy()->utc();
        $overlapStartUtc = $overlapStart?->copy()->utc();
        $overlapEndUtc = $overlapEnd?->copy()->utc();

        $bookings = BookingSlot::query()
            ->whereIn('court_id', $courtIds)
            ->where('ends_at', '>', $overlapStartUtc ?? $notBeforeUtc)
            ->when($overlapEndUtc, fn ($query) => $query->where('starts_at', '<', $overlapEndUtc))
            ->whereHas('booking', fn ($query) => $query->whereIn('status', ['confirmed', 'pending_payment']))
            ->with(['booking', 'court.venue.organization'])
            ->get()
            ->map(fn (BookingSlot $slot): array => [
                'court' => $slot->court,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'reference' => $slot->booking?->code ?: "BK-{$slot->booking_id}",
            ]);

        $reservations = ReservationSlot::query()
            ->whereIn('court_id', $courtIds)
            ->where('ends_at', '>', $overlapStartUtc ?? $notBeforeUtc)
            ->when($overlapEndUtc, fn ($query) => $query->where('starts_at', '<', $overlapEndUtc))
            ->when($excludeReservationId !== null, fn ($query) => $query->where('reservation_id', '!=', $excludeReservationId))
            ->whereHas('reservation', fn ($query) => $query->whereIn('status', ['confirmed', 'held']))
            ->get()
            ->map(fn (ReservationSlot $slot): array => [
                'court' => $slot->court,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'reference' => $slot->reservation?->code ?: "RES-{$slot->reservation_id}",
            ]);

        return $bookings->concat($reservations);
    }

    private function abortOnConflicts(array $references, string $message): void
    {
        if ($references !== []) {
            $fullMessage = $message.' Conflicting references: '.implode(', ', $references);
            throw new CommitmentConflictException($fullMessage, $references);
        }
    }
}
