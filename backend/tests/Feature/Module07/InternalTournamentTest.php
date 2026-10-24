<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-121, BR-140, BR-148, UC-7.17–UC-7.19: an internal tournament in one renter's bookings, run by its representative. */
class InternalTournamentTest extends TestCase
{
    use RefreshDatabase;
    use RunsTournaments;

    private Court $court;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->court = $this->bookableCourt();
        $this->owner = $this->ownerOf($this->court->venue->organization);
    }

    /** @return array<string, mixed> */
    private function internalSetup(array $overrides = []): array
    {
        return array_diff_key($this->setupBody($overrides), array_flip(['max_entries', 'fee', 'registration_deadline', 'payment_deadline', 'starts_at', 'ends_at']));
    }

    public function test_fr_121_the_owner_opens_it_on_one_renters_bookings_and_grants_the_renter(): void
    {
        $first = $this->booking($this->court, now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(8, 0), 2);
        $second = $this->booking($this->court, now('Asia/Ho_Chi_Minh')->addDays(4)->setTime(8, 0), 2);
        $second->update(['user_id' => $first->user_id]);

        $id = $this->actingAs($this->owner)->postJson('/api/managed/internal-tournaments', $this->internalSetup() + ['reservation_codes' => [$first->code, $second->code]])
            ->assertCreated()->assertJsonPath('tournament.type', 'internal')->assertJsonPath('tournament.fee', 0)->json('tournament.id');

        $this->actingAs($first->user)->getJson("/api/managed/tournaments/{$id}")
            ->assertOk()->assertJsonPath('tournament.my_role', 'representative')->assertJsonCount(4, 'rentals')
            ->assertJsonPath('tournament.permissions.roster', true)->assertJsonPath('tournament.permissions.publish', false);
        $this->assertTrue(Tournament::where('uuid', $id)->first()->starts_at->equalTo($first->slots->min('starts_at')));
    }

    public function test_br_148_bookings_of_two_renters_unconfirmed_or_already_used_are_refused(): void
    {
        $mine = $this->booking($this->court, now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(8, 0));
        $theirs = $this->booking($this->court, now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(10, 0));
        $held = $this->booking($this->court, now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(12, 0), status: 'held');
        $used = $this->booking($this->court, now('Asia/Ho_Chi_Minh')->addDays(5)->setTime(8, 0));
        $this->internalTournament($this->court, rental: $used);
        $this->actingAs($this->owner);

        foreach ([[$mine->code, $theirs->code], [$held->code], [$used->code], ['KHONGCO']] as $codes) {
            $this->postJson('/api/managed/internal-tournaments', $this->internalSetup() + ['reservation_codes' => $codes])
                ->assertUnprocessable()->assertJsonPath('code', 'INTERNAL_RENTALS_INVALID');
        }
        $this->postJson('/api/managed/internal-tournaments', $this->setupBody() + ['reservation_codes' => [$mine->code]])
            ->assertUnprocessable()->assertJsonValidationErrors(['fee', 'max_entries', 'registration_deadline']);
    }

    public function test_br_140_only_the_venue_owner_opens_one(): void
    {
        $booking = $this->booking($this->court, now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(8, 0));

        $this->actingAs($booking->user)->postJson('/api/managed/internal-tournaments', $this->internalSetup() + ['reservation_codes' => [$booking->code]])
            ->assertForbidden()->assertJsonPath('code', 'TOURNAMENT_FORBIDDEN');
    }

    public function test_uc_7_19_the_representative_edits_the_draft_setup(): void
    {
        $tournament = $this->internalTournament($this->court);
        $representative = $tournament->grants()->first()->user;

        $this->actingAs($representative)->putJson("/api/managed/tournaments/{$tournament->uuid}", $this->internalSetup(['name' => 'Giải nội bộ công ty A', 'entry_type' => 'double']))
            ->assertOk()->assertJsonPath('tournament.name', 'Giải nội bộ công ty A')->assertJsonPath('tournament.entry_type', 'double');
    }

    public function test_br_140_the_representative_reaches_only_the_granted_tournament_and_no_venue_management(): void
    {
        $tournament = $this->internalTournament($this->court);
        $other = $this->internalTournament($this->court, rental: $this->booking($this->court, now('Asia/Ho_Chi_Minh')->addDays(6)->setTime(8, 0)));
        $representative = $tournament->grants()->first()->user;
        $this->actingAs($representative);

        $this->getJson('/api/managed/tournaments')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tournament->uuid);
        $this->getJson("/api/managed/tournaments/{$other->uuid}")->assertNotFound();
        $this->putJson("/api/managed/tournaments/{$other->uuid}", $this->internalSetup())->assertForbidden();
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/grants", ['role' => 'representative', 'email' => User::factory()->create()->email])->assertForbidden();
        $this->getJson("/api/operations/venues/{$this->court->venue_id}/customers", ['X-Organization-ID' => (string) $this->court->venue->organization_id])->assertForbidden();
    }

    public function test_br_148_an_internal_tournament_takes_no_public_entry_or_fee(): void
    {
        $tournament = $this->internalTournament($this->court);
        $representative = $tournament->grants()->first()->user;

        $this->actingAs($representative)->postJson("/api/managed/tournaments/{$tournament->uuid}/publish")->assertForbidden();
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/allocations", [
            'court_id' => $this->court->id, 'starts_at' => now()->addDays(12)->toIso8601String(), 'ends_at' => now()->addDays(12)->addHour()->toIso8601String(),
        ])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson("/api/tournaments/{$tournament->uuid}/entries", ['name' => 'Ngoài', 'members' => [$this->athlete()]])
            ->assertConflict()->assertJsonPath('code', 'ENTRY_CLOSED');
    }
}
