<?php

namespace App\Http\Resources\Tournament;

use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\TournamentEntry;
use App\Models\TournamentEvent;
use App\Models\TournamentGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The management screen (UC-7.19): the setup with what the caller may do now, every entry in full, the play, the
 * reserved court time or rentals, the grants and the history.
 *
 * @property array{tournament: Tournament, role: string, entries: Collection<int, TournamentEntry>, board: array<string, mixed>, allocations: Collection<int, TournamentCourtAllocation>, rentals: Collection<int, Reservation>, grants: Collection<int, TournamentGrant>, events: Collection<int, TournamentEvent>, courts: Collection<int, Court>} $resource
 */
class ManagedTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'tournament' => TournamentDetailResource::make($data['tournament'])->resolve($request) + ['permissions' => TournamentAccess::permissions($data['tournament'], $data['role'])],
            'entries' => TournamentEntryResource::collection($data['entries']),
            ...TournamentBoardResource::make($data['board'], false)->resolve($request),
            'allocations' => $data['allocations']->map(fn (TournamentCourtAllocation $allocation): array => [
                'id' => $allocation->id,
                'court' => $allocation->court->only(['id', 'code']),
                'starts_at' => $allocation->starts_at->toIso8601String(),
                'ends_at' => $allocation->ends_at->toIso8601String(),
            ])->values(),
            'rentals' => $data['rentals']->flatMap(fn (Reservation $reservation) => $reservation->slots->map(fn (ReservationSlot $slot): array => [
                'reservation_code' => $reservation->code,
                'court' => $slot->court?->only(['id', 'code']),
                'starts_at' => $slot->starts_at?->toIso8601String(),
                'ends_at' => $slot->ends_at?->toIso8601String(),
            ]))->values(),
            'grants' => $data['grants']->map(fn (TournamentGrant $grant): array => ['id' => $grant->id, 'role' => $grant->role, 'user' => $grant->user->only(['id', 'name', 'email'])])->values(),
            'events' => $data['events']->map(fn (TournamentEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'actor' => $event->actor?->only(['id', 'name']),
                'reason' => $event->reason,
                'payload' => (object) $event->payload,
                'created_at' => $event->created_at->toIso8601String(),
            ])->values(),
            'courts' => $data['courts']->map(fn ($court): array => $court->only(['id', 'code']))->values(),
        ];
    }
}
