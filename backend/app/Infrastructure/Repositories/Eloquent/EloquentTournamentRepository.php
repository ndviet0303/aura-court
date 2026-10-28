<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Models\Court;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\TournamentEntry;
use App\Models\TournamentEvent;
use App\Models\TournamentGrant;
use App\Models\TournamentRental;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentTournamentRepository implements TournamentRepositoryInterface
{
    private const VENUE = 'venue:id,organization_id,name,address';

    public function paginatePublished(?string $sport, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->summaries(Tournament::query())
            ->where('type', Tournament::TYPE_PUBLIC)
            ->where('status', '!=', Tournament::STATUS_DRAFT)
            ->when($sport, fn (Builder $query, string $value) => $query->where('sport', $value))
            ->when($status, fn (Builder $query, string $value) => $query->where('status', $value))
            ->orderBy('starts_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function managedBy(User $user): Collection
    {
        $owned = $user->organizationMembers()->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)->where('status', 'active')->pluck('organization_id');

        return $this->summaries(Tournament::query())
            ->where(fn (Builder $query) => $query->whereIn('organization_id', $owned)
                ->orWhereHas('grants', fn (Builder $grant) => $grant->where('user_id', $user->id)->whereNull('revoked_at')))
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $attributes): Tournament
    {
        return $this->summary(Tournament::create($attributes));
    }

    public function update(Tournament $tournament, array $attributes): Tournament
    {
        $tournament->update($attributes);

        return $this->summary($tournament);
    }

    public function lock(Tournament $tournament): Tournament
    {
        return Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();
    }

    public function summary(Tournament $tournament): Tournament
    {
        return $tournament->load(self::VENUE)->loadCount(['entries as accepted_count' => fn (Builder $query) => $query->whereIn('status', [TournamentEntry::STATUS_PAID, TournamentEntry::STATUS_CONFIRMED])]);
    }

    public function recordEvent(Tournament $tournament, string $type, ?User $actor, ?string $reason, array $payload): TournamentEvent
    {
        return $tournament->events()->create(['type' => $type, 'actor_id' => $actor?->id, 'reason' => $reason, 'payload' => $payload, 'created_at' => now()]);
    }

    public function events(Tournament $tournament): Collection
    {
        return $tournament->events()->with('actor:id,name')->latest('created_at')->orderByDesc('id')->get();
    }

    public function grants(Tournament $tournament): Collection
    {
        return $tournament->grants()->whereNull('revoked_at')->with('user:id,name,email')->orderBy('id')->get();
    }

    public function grant(Tournament $tournament, User $user, string $role, User $by): TournamentGrant
    {
        $active = $tournament->grants()->where('user_id', $user->id)->where('role', $role)->whereNull('revoked_at')->first();

        return ($active ?? $tournament->grants()->create(['user_id' => $user->id, 'role' => $role, 'granted_by' => $by->id]))->load('user:id,name,email');
    }

    public function revoke(TournamentGrant $grant): void
    {
        $grant->update(['revoked_at' => now()]);
    }

    public function findUserByEmail(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    public function courts(Tournament $tournament): Collection
    {
        return Court::query()
            ->where('venue_id', $tournament->venue_id)
            ->where('sport_type', 'like', $tournament->sport === 'football' ? 'football%' : $tournament->sport)
            ->orderBy('code')
            ->get(['id', 'code', 'venue_id', 'sport_type']);
    }

    public function allocations(Tournament $tournament): Collection
    {
        return $tournament->allocations()->with('court:id,code')->orderBy('starts_at')->get();
    }

    public function lockCourt(Tournament $tournament, int $courtId): ?Court
    {
        return $this->courts($tournament)->contains('id', $courtId) ? Court::query()->whereKey($courtId)->lockForUpdate()->first() : null;
    }

    public function courtTimeTaken(int $courtId, CarbonInterface $start, CarbonInterface $end): bool
    {
        return TournamentCourtAllocation::blocks($courtId, $start, $end) || $this->bookingSlots($courtId, $start, $end)->exists();
    }

    public function bookingsIn(int $courtId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->bookingSlots($courtId, $start, $end)
            ->with(['reservation' => fn ($reservation) => $reservation->withoutGlobalScope('tenant_isolation')])
            ->orderBy('starts_at')
            ->get();
    }

    public function allocate(Tournament $tournament, array $attributes): TournamentCourtAllocation
    {
        return $tournament->allocations()->create($attributes)->load('court:id,code');
    }

    public function release(TournamentCourtAllocation $allocation): void
    {
        $allocation->delete();
    }

    public function reservationsByCode(array $codes): Collection
    {
        return Reservation::query()->withoutGlobalScope('tenant_isolation')->whereIn('code', $codes)->with('slots.court')->get();
    }

    public function anyRented(array $reservationIds): bool
    {
        return TournamentRental::query()->whereIn('reservation_id', $reservationIds)->exists();
    }

    public function attachRentals(Tournament $tournament, array $reservationIds): void
    {
        foreach ($reservationIds as $id) {
            $tournament->rentals()->create(['reservation_id' => $id]);
        }
    }

    public function rentals(Tournament $tournament): Collection
    {
        return Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->whereIn('id', $tournament->rentals()->select('reservation_id'))
            ->with(['slots' => fn ($slots) => $slots->orderBy('starts_at'), 'slots.court:id,code'])
            ->orderBy('id')
            ->get();
    }

    public function covers(Tournament $tournament, int $courtId, CarbonInterface $start, CarbonInterface $end): bool
    {
        $query = $tournament->isPublic()
            ? $tournament->allocations()->getQuery()
            // ponytail: one rental slot must hold the whole fixture; adjacent slots of a recurring booking are not joined.
            : ReservationSlot::query()->where('status', 'confirmed')
                ->whereIn('reservation_id', $tournament->rentals()->select('reservation_id'))
                ->whereHas('reservation', fn (Builder $reservation) => $reservation->withoutGlobalScope('tenant_isolation')->where('status', 'confirmed'));

        return $query->where('court_id', $courtId)
            ->where('starts_at', '<=', $start->copy()->utc())
            ->where('ends_at', '>=', $end->copy()->utc())
            ->exists();
    }

    /** @return Builder<ReservationSlot> */
    private function bookingSlots(int $courtId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return ReservationSlot::query()
            ->where('court_id', $courtId)
            ->where('starts_at', '<', $end->copy()->utc())
            ->where('ends_at', '>', $start->copy()->utc())
            ->whereHas('reservation', fn (Builder $reservation) => $reservation->withoutGlobalScope('tenant_isolation')->blocksInventory());
    }

    /**
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    private function summaries(Builder $query): Builder
    {
        return $query->with(self::VENUE)->withCount(['entries as accepted_count' => fn (Builder $entries) => $entries->whereIn('status', [TournamentEntry::STATUS_PAID, TournamentEntry::STATUS_CONFIRMED])]);
    }
}
