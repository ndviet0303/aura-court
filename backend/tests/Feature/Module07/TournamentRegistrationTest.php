<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\OutboxMessage;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-123, FR-124, BR-150, UC-7.21–UC-7.24: an account applies for a unit, follows and withdraws it; the owner reviews it. */
class TournamentRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use RunsTournaments;

    private Court $court;

    private Tournament $tournament;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->court = $this->payableCourt();
        $this->tournament = $this->tournament($this->court, ['status' => 'open', 'entry_type' => 'double', 'athlete_fields' => ['CCCD']]);
        $this->player = User::factory()->create();
    }

    public function test_fr_123_br_150_a_unit_applies_with_athletes_who_need_no_account(): void
    {
        $users = User::count();

        $this->actingAs($this->player)->postJson("/api/tournaments/{$this->tournament->uuid}/entries", ['name' => 'Cặp An Bình', 'members' => [
            $this->athlete('Nguyễn Văn An', extra: ['CCCD' => '001']), $this->athlete('Trần Văn Bình', extra: ['CCCD' => '002']),
        ]])->assertCreated()
            ->assertJsonPath('entry.status', 'submitted')
            ->assertJsonPath('entry.representative.id', $this->player->id)
            ->assertJsonPath('entry.members.1.full_name', 'Trần Văn Bình')
            ->assertJsonPath('entry.members.1.extra.CCCD', '002');

        $this->assertSame($users, User::count());
        $this->getJson('/api/me/tournament-entries')->assertJsonPath('data.0.name', 'Cặp An Bình')
            ->assertJsonPath('data.0.tournament.id', $this->tournament->uuid)->assertJsonPath('data.0.payment', null);
        $this->getJson("/api/tournaments/{$this->tournament->uuid}")->assertJsonPath('tournament.accepted_count', 0);
    }

    public function test_br_150_the_unit_must_fit_the_event_and_carry_every_athlete_field(): void
    {
        $this->actingAs($this->player);
        $url = "/api/tournaments/{$this->tournament->uuid}/entries";

        $this->postJson($url, ['name' => 'Ba người', 'members' => [
            $this->athlete('A', extra: ['CCCD' => '1']), $this->athlete('B', extra: ['CCCD' => '2']), $this->athlete('C', extra: ['CCCD' => '3']),
        ]])->assertUnprocessable()->assertJsonValidationErrors('members');
        $this->postJson($url, ['name' => 'Thiếu CCCD', 'members' => [$this->athlete('A', extra: ['CCCD' => '1']), $this->athlete('B')]])
            ->assertUnprocessable()->assertJsonValidationErrors('members.1.extra');
        $this->postJson($url, ['name' => 'Sinh sau', 'members' => [
            ['full_name' => 'A', 'birth_date' => '2030-01-01', 'role' => 'main', 'extra' => ['CCCD' => '1']], $this->athlete('B', extra: ['CCCD' => '2']),
        ]])->assertUnprocessable()->assertJsonValidationErrors('members.0.birth_date');
        $this->assertSame(0, TournamentEntry::count());
    }

    public function test_fr_123_one_live_application_per_account_until_it_is_withdrawn(): void
    {
        $first = $this->entry($this->tournament, TournamentEntry::STATUS_SUBMITTED, $this->player, members: 2);
        $body = ['name' => 'Lần hai', 'members' => [$this->athlete('A', extra: ['CCCD' => '1']), $this->athlete('B', extra: ['CCCD' => '2'])]];
        $this->actingAs($this->player);

        $this->postJson("/api/tournaments/{$this->tournament->uuid}/entries", $body)->assertConflict()->assertJsonPath('code', 'ENTRY_EXISTS');
        $this->actingAs(User::factory()->create())->postJson("/api/tournament-entries/{$first->id}/withdraw")->assertForbidden();
        $this->actingAs($this->player)->postJson("/api/tournament-entries/{$first->id}/withdraw")->assertOk()->assertJsonPath('entry.status', 'withdrawn');
        $this->postJson("/api/tournament-entries/{$first->id}/withdraw")->assertConflict()->assertJsonPath('code', 'ENTRY_NOT_WITHDRAWABLE');

        $this->postJson("/api/tournaments/{$this->tournament->uuid}/entries", $body)->assertCreated();
    }

    public function test_fr_123_registration_closes_at_the_deadline_and_on_drafts(): void
    {
        $body = ['name' => 'Muộn', 'members' => [$this->athlete('A', extra: ['CCCD' => '1']), $this->athlete('B', extra: ['CCCD' => '2'])]];
        $this->travelTo($this->tournament->registration_deadline->copy()->addMinute());

        $this->actingAs($this->player)->postJson("/api/tournaments/{$this->tournament->uuid}/entries", $body)
            ->assertConflict()->assertJsonPath('code', 'ENTRY_CLOSED');
        $this->getJson("/api/tournaments/{$this->tournament->uuid}")->assertJsonPath('tournament.can_register', false);
        $draft = $this->tournament($this->court, ['entry_type' => 'double', 'athlete_fields' => ['CCCD']]);
        $this->postJson("/api/tournaments/{$draft->uuid}/entries", $body)->assertConflict()->assertJsonPath('code', 'ENTRY_CLOSED');
    }

    public function test_fr_124_the_owner_approves_or_rejects_with_a_reason_and_the_representative_is_told(): void
    {
        $approved = $this->entry($this->tournament, TournamentEntry::STATUS_SUBMITTED, $this->player, members: 2);
        $rejected = $this->entry($this->tournament, TournamentEntry::STATUS_SUBMITTED, members: 2);
        $this->actingAs($this->ownerOf($this->court->venue->organization));

        $this->postJson("/api/tournament-entries/{$approved->id}/approve")->assertOk()->assertJsonPath('entry.status', 'approved');
        $this->postJson("/api/tournament-entries/{$rejected->id}/reject")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/tournament-entries/{$rejected->id}/reject", ['reason' => 'Sai hạng thi đấu'])
            ->assertOk()->assertJsonPath('entry.status', 'rejected')->assertJsonPath('entry.reject_reason', 'Sai hạng thi đấu');
        $this->postJson("/api/tournament-entries/{$approved->id}/reject", ['reason' => 'Đổi ý'])->assertConflict()->assertJsonPath('code', 'ENTRY_NOT_PENDING');

        $this->assertTrue(OutboxMessage::where('event_type', 'tournament.entry_approved')->where('aggregate_id', $approved->id)->exists());
        $this->assertTrue(OutboxMessage::where('event_type', 'tournament.entry_rejected')->where('aggregate_id', $rejected->id)->exists());
    }

    public function test_fr_124_officials_and_strangers_do_not_review(): void
    {
        $entry = $this->entry($this->tournament, TournamentEntry::STATUS_SUBMITTED, members: 2);
        $official = $this->staffOf($this->court->venue);
        $this->grant($this->tournament, $official);

        $this->actingAs($official)->postJson("/api/tournament-entries/{$entry->id}/approve")->assertForbidden();
        $this->actingAs($this->player)->postJson("/api/tournament-entries/{$entry->id}/approve")->assertForbidden();
        $this->assertSame('submitted', $entry->fresh()->status);
    }
}
