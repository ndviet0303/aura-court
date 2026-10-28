<?php

namespace App\Contracts\Repositories;

use App\Models\Court;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\TournamentEvent;
use App\Models\TournamentGrant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Module 07 part C: tournaments with their grants, reserved court time, internal rentals and history. */
interface TournamentRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Tournament> published public tournaments, soonest first, with venue and accepted_count */
    public function paginatePublished(?string $sport, ?string $status, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, Tournament> owned through the organization, or granted to the user, with venue and accepted_count */
    public function managedBy(User $user): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Tournament;

    /** @param  array<string, mixed>  $attributes */
    public function update(Tournament $tournament, array $attributes): Tournament;

    /** The tournament re-read FOR UPDATE; call inside a transaction. */
    public function lock(Tournament $tournament): Tournament;

    /** With venue and accepted_count, as the summary shows it. */
    public function summary(Tournament $tournament): Tournament;

    /** @param  array<string, mixed>  $payload */
    public function recordEvent(Tournament $tournament, string $type, ?User $actor, ?string $reason, array $payload): TournamentEvent;

    /** @return Collection<int, TournamentEvent> newest first, with actor */
    public function events(Tournament $tournament): Collection;

    /** @return Collection<int, TournamentGrant> active grants with user */
    public function grants(Tournament $tournament): Collection;

    public function grant(Tournament $tournament, User $user, string $role, User $by): TournamentGrant;

    public function revoke(TournamentGrant $grant): void;

    public function findUserByEmail(string $email): ?User;

    /** @return Collection<int, Court> the venue's courts of the tournament's sport */
    public function courts(Tournament $tournament): Collection;

    /** @return Collection<int, TournamentCourtAllocation> by start, with court */
    public function allocations(Tournament $tournament): Collection;

    /** The court FOR UPDATE, as the booking flow locks it, when it is one of the tournament's courts. */
    public function lockCourt(Tournament $tournament, int $courtId): ?Court;

    /** Whether a confirmed booking, a live hold or another tournament's allocation takes some of [start, end) (BR-152). */
    public function courtTimeTaken(int $courtId, CarbonInterface $start, CarbonInterface $end): bool;

    /** @return Collection<int, ReservationSlot> the confirmed or held booking slots in [start, end), by start, with reservation */
    public function bookingsIn(int $courtId, CarbonInterface $start, CarbonInterface $end): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function allocate(Tournament $tournament, array $attributes): TournamentCourtAllocation;

    public function release(TournamentCourtAllocation $allocation): void;

    /**
     * @param  list<string>  $codes
     * @return Collection<int, Reservation> with slots and their courts, whatever the tenant
     */
    public function reservationsByCode(array $codes): Collection;

    /** @param  list<int>  $reservationIds */
    public function anyRented(array $reservationIds): bool;

    /** @param  list<int>  $reservationIds */
    public function attachRentals(Tournament $tournament, array $reservationIds): void;

    /** @return Collection<int, Reservation> the internal tournament's bookings, with slots and courts */
    public function rentals(Tournament $tournament): Collection;

    /** Whether [start, end) on the court lies within one allocation (public) or one confirmed rental slot (internal). */
    public function covers(Tournament $tournament, int $courtId, CarbonInterface $start, CarbonInterface $end): bool;
}
