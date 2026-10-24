<?php

namespace Tests\Feature\Module07\Concerns;

use App\Models\Court;
use App\Models\Reservation;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\TournamentEntry;
use App\Models\TournamentGrant;
use App\Models\TournamentRental;
use App\Models\User;
use Tests\Concerns\BooksCourts;
use Tests\Feature\Module02\Concerns\SeedsBookings;

/**
 * Module 07 part C: tournaments written straight to the tables, and setup payloads for the API. Times are relative to
 * Carbon::setTestNow; the court's venue collects through a verified connection (BooksCourts).
 *
 *     $court = $this->payableCourt();
 *     $tournament = $this->tournament($court, ['status' => 'open']);             // public badminton singles, knockout
 *     $entry = $this->entry($tournament, 'approved', $representative);
 *     $internal = $this->internalTournament($court);                             // on one renter's booking
 */
trait RunsTournaments
{
    use BooksCourts, SeedsBookings;

    /**
     * A valid public setup for the API (FR-120).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function setupBody(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Giải cầu lông mở rộng',
            'sport' => 'badminton',
            'entry_type' => 'single',
            'format' => Tournament::FORMAT_KNOCKOUT,
            'games_to_win' => 2,
            'points' => ['win' => 3, 'draw' => 1, 'loss' => 0],
            'tiebreakers' => ['head_to_head', 'score_diff'],
            'rules' => 'Thi đấu theo luật BWF.',
            'eligibility' => 'Vận động viên phong trào.',
            'athlete_fields' => [],
            'forfeit_rule' => 'Vắng 15 phút bị xử thua.',
            'prizes' => 'Cúp và tiền thưởng.',
            'max_entries' => 8,
            'fee' => 200000,
            'registration_deadline' => now()->addDays(5)->toIso8601String(),
            'payment_deadline' => now()->addDays(6)->toIso8601String(),
            'starts_at' => now()->addDays(10)->toIso8601String(),
            'ends_at' => now()->addDays(11)->toIso8601String(),
        ], $overrides);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function tournament(Court $court, array $attributes = []): Tournament
    {
        $setup = array_intersect_key($this->setupBody(), array_flip([
            'name', 'sport', 'entry_type', 'format', 'games_to_win', 'points', 'tiebreakers', 'rules', 'eligibility',
            'athlete_fields', 'forfeit_rule', 'prizes', 'max_entries', 'fee',
        ]));

        return Tournament::create($attributes + $setup + [
            'organization_id' => $court->venue->organization_id,
            'venue_id' => $court->venue_id,
            'created_by' => $this->ownerOf($court->venue->organization)->id,
            'type' => Tournament::TYPE_PUBLIC,
            'status' => Tournament::STATUS_DRAFT,
            'published_at' => ($attributes['status'] ?? 'draft') === 'draft' ? null : now(),
            'registration_deadline' => now()->addDays(5),
            'payment_deadline' => now()->addDays(6),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(11),
        ]);
    }

    /**
     * An internal tournament on one renter's confirmed booking of the court, granted to the renter (D21).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function internalTournament(Court $court, array $attributes = [], ?Reservation $rental = null): Tournament
    {
        $rental ??= $this->booking($court, now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(8, 0), 4);
        $tournament = $this->tournament($court, $attributes + [
            'type' => Tournament::TYPE_INTERNAL,
            'eligibility' => null,
            'prizes' => null,
            'max_entries' => null,
            'fee' => 0,
            'registration_deadline' => null,
            'payment_deadline' => null,
            'starts_at' => $rental->slots->min('starts_at'),
            'ends_at' => $rental->slots->max('ends_at'),
        ]);
        TournamentRental::create(['tournament_id' => $tournament->id, 'reservation_id' => $rental->id]);
        $this->grant($tournament, $rental->user, TournamentGrant::ROLE_REPRESENTATIVE);

        return $tournament;
    }

    protected function grant(Tournament $tournament, User $user, string $role = TournamentGrant::ROLE_OFFICIAL): TournamentGrant
    {
        return TournamentGrant::create(['tournament_id' => $tournament->id, 'user_id' => $user->id, 'role' => $role, 'granted_by' => $tournament->created_by]);
    }

    /** Court time reserved for the tournament, $from–$to on the day $daysAhead from now (venue time). */
    protected function allocation(Tournament $tournament, Court $court, int $daysAhead = 10, int $from = 8, int $to = 18): TournamentCourtAllocation
    {
        $day = now('Asia/Ho_Chi_Minh')->addDays($daysAhead)->startOfDay();

        return TournamentCourtAllocation::create([
            'tournament_id' => $tournament->id, 'court_id' => $court->id, 'created_by' => $tournament->created_by,
            'starts_at' => $day->copy()->setTime($from, 0), 'ends_at' => $day->copy()->setTime($to, 0),
        ]);
    }

    /** An entry with its athletes; a public one belongs to $representative (a new account when omitted). */
    protected function entry(Tournament $tournament, string $status, ?User $representative = null, string $name = '', int $members = 1): TournamentEntry
    {
        $entry = TournamentEntry::create([
            'tournament_id' => $tournament->id,
            'user_id' => $tournament->isPublic() ? ($representative ?? User::factory()->create())->id : null,
            'name' => $name !== '' ? $name : 'Đơn vị '.(TournamentEntry::query()->count() + 1),
            'status' => $status,
            'approved_at' => in_array($status, [TournamentEntry::STATUS_APPROVED, TournamentEntry::STATUS_PAID], true) ? now() : null,
            'paid_at' => $status === TournamentEntry::STATUS_PAID ? now() : null,
        ]);
        foreach (range(1, $members) as $position) {
            $entry->members()->create(['full_name' => "{$entry->name} VĐV {$position}", 'birth_date' => '1995-05-01', 'extra' => [], 'role' => 'main', 'position' => $position]);
        }

        return $entry;
    }

    /**
     * An athlete for the entry and roster requests.
     *
     * @param  array<string, string>  $extra
     * @return array<string, mixed>
     */
    protected function athlete(string $name = 'Nguyễn Văn An', string $role = 'main', array $extra = []): array
    {
        return ['full_name' => $name, 'birth_date' => '1998-04-12', 'role' => $role, 'extra' => $extra];
    }

    /** $count accepted entries named Đội 01…, the roster locked, then drawn through the API by the owner. */
    protected function drawn(Tournament $tournament, int $count): Tournament
    {
        foreach (range(1, $count) as $i) {
            $this->entry($tournament, $tournament->acceptedStatus(), name: sprintf('Đội %02d', $i));
        }
        $tournament->update(['status' => Tournament::STATUS_ROSTER_LOCKED]);
        $this->actingAs($this->ownerOf($tournament->venue->organization))
            ->postJson("/api/managed/tournaments/{$tournament->uuid}/draw")->assertOk();

        return $tournament->refresh();
    }
}
