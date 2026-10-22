<?php

namespace App\Services\Reservation;

use App\Exceptions\CommitmentConflictException;
use App\Models\MatchPost;
use App\Models\ReservationSlot;
use App\Models\Venue;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

    /**
     * A stop blocks [starts_at, no end): any held or confirmed booking, owner-hosted session or community activity
     * without a reservation still running after its start conflicts, as it did for court blocks.
     *
     * ponytail: refuses the stop with 409 as court blocks did; task 4.4 replaces this with a preview and an
     * acknowledged save that opens affected-booking cases.
     *
     * @param  list<int>  $courtIds
     */
    public function assertStopPreservesCommitments(array $courtIds, CarbonInterface $startsAt): void
    {
        if ($courtIds === []) {
            return;
        }
        $start = Carbon::instance($startsAt)->utc();
        $conflicts = $this->commitments($courtIds, $start)
            ->pluck('reference')
            ->unique()
            ->values()
            ->all();

        $this->abortOnConflicts($conflicts, 'Lịch dừng vận hành trùng với các lượt đặt còn hiệu lực.');

        // Same rule as SlotSelectionPolicy: a hosted or reservation-less activity holds the court like a booking.
        $hostedActivity = MatchPost::query()
            ->whereIn('court_id', $courtIds)
            ->where('ends_at', '>', $start)
            ->whereIn('status', ['open', 'active'])
            ->where(fn ($q) => $q->where('is_owner_hosted', true)->orWhereNull('reservation_id'))
            ->exists();
        abort_if($hostedActivity, 409, 'Lịch dừng vận hành trùng với phiên chơi do chủ sân tổ chức hoặc hoạt động cộng đồng còn hiệu lực.');
    }

    private function commitments(array $courtIds, Carbon $notBefore): Collection
    {
        return ReservationSlot::query()
            ->whereIn('court_id', $courtIds)
            ->where('ends_at', '>', $notBefore->copy()->utc())
            ->whereHas('reservation', fn ($query) => $query->whereIn('status', ['confirmed', 'held']))
            ->with(['court', 'reservation'])
            ->get()
            ->map(fn (ReservationSlot $slot): array => [
                'court' => $slot->court,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'reference' => $slot->reservation?->code ?: "RES-{$slot->reservation_id}",
            ]);
    }

    private function abortOnConflicts(array $references, string $message): void
    {
        if ($references !== []) {
            $fullMessage = $message.' Conflicting references: '.implode(', ', $references);
            throw new CommitmentConflictException($fullMessage, $references);
        }
    }
}
