<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\FinanceException;
use App\Models\OutboxMessage;
use App\Models\OwnerPaymentReceipt;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-125, BR-151, UC-7.25, UC-7.26: an approved unit pays the venue's own account; only a confirmed transfer takes a place. */
class TournamentFeeTest extends TestCase
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
        $this->tournament = $this->tournament($this->court, ['status' => 'open', 'max_entries' => 2]);
        $this->player = User::factory()->create();
    }

    public function test_br_151_an_application_pays_only_after_approval(): void
    {
        $entry = $this->entry($this->tournament, TournamentEntry::STATUS_SUBMITTED, $this->player);

        $this->actingAs($this->player)->postJson("/api/tournament-entries/{$entry->id}/payment")
            ->assertConflict()->assertJsonPath('code', 'ENTRY_NOT_APPROVED');
        $this->actingAs(User::factory()->create())->postJson("/api/tournament-entries/{$entry->id}/payment")->assertForbidden();
    }

    public function test_fr_125_br_151_a_pending_payment_holds_no_place_and_the_transfer_confirms_it(): void
    {
        $entry = $this->entry($this->tournament, TournamentEntry::STATUS_APPROVED, $this->player);

        $payment = $this->actingAs($this->player)->postJson("/api/tournament-entries/{$entry->id}/payment")
            ->assertOk()->assertJsonPath('payment.amount', 200000)->assertJsonPath('payment.status', 'pending')->json('payment');
        $this->assertTrue(Carbon::parse($payment['expires_at'])->equalTo($this->tournament->payment_deadline));
        $this->postJson("/api/tournament-entries/{$entry->id}/payment")->assertJsonPath('payment.reference', $payment['reference']);
        $this->getJson("/api/tournaments/{$this->tournament->uuid}")->assertJsonPath('tournament.accepted_count', 0);

        $this->transfer($this->court, $payment['reference'], 200000)->assertOk();

        $this->assertSame('paid', $entry->fresh()->status);
        $this->getJson("/api/tournaments/{$this->tournament->uuid}")->assertJsonPath('tournament.accepted_count', 1)->assertJsonPath('entries.0.id', $entry->id);
        $this->getJson('/api/me/tournament-entries')->assertJsonPath('data.0.status', 'paid')->assertJsonPath('data.0.payment.status', 'paid');
        $this->assertTrue(OutboxMessage::where('event_type', 'tournament.entry_paid')->where('aggregate_id', $entry->id)->exists());
    }

    public function test_br_151_the_shared_payment_deadline_closes_payment(): void
    {
        $entry = $this->entry($this->tournament, TournamentEntry::STATUS_APPROVED, $this->player);
        $this->travelTo($this->tournament->payment_deadline->copy()->addMinute());

        $this->actingAs($this->player)->postJson("/api/tournament-entries/{$entry->id}/payment")
            ->assertConflict()->assertJsonPath('code', 'PAYMENT_DEADLINE_PASSED');
    }

    public function test_br_151_a_paid_unit_cannot_withdraw_and_a_withdrawn_one_cannot_pay(): void
    {
        $paid = $this->entry($this->tournament, TournamentEntry::STATUS_PAID, $this->player);
        $approved = $this->entry($this->tournament, TournamentEntry::STATUS_APPROVED);
        $reference = $this->actingAs($approved->user)->postJson("/api/tournament-entries/{$approved->id}/payment")->json('payment.reference');

        $this->actingAs($this->player)->postJson("/api/tournament-entries/{$paid->id}/withdraw")
            ->assertConflict()->assertJsonPath('code', 'ENTRY_NOT_WITHDRAWABLE');
        $this->actingAs($approved->user)->postJson("/api/tournament-entries/{$approved->id}/withdraw")->assertOk();
        $this->transfer($this->court, $reference, 200000)->assertOk();

        $this->assertSame('withdrawn', $approved->fresh()->status);
        $this->assertSame('transfer_after_expiry', OwnerPaymentReceipt::value('exception_code'));
    }

    public function test_br_151_a_transfer_that_finds_no_place_left_becomes_an_exception(): void
    {
        $this->entry($this->tournament, TournamentEntry::STATUS_PAID);
        $late = $this->entry($this->tournament, TournamentEntry::STATUS_APPROVED, $this->player);
        $other = $this->entry($this->tournament, TournamentEntry::STATUS_APPROVED);
        $mine = $this->actingAs($this->player)->postJson("/api/tournament-entries/{$late->id}/payment")->json('payment.reference');
        $theirs = $this->actingAs($other->user)->postJson("/api/tournament-entries/{$other->id}/payment")->json('payment.reference');

        $this->transfer($this->court, $theirs, 200000)->assertOk();
        $this->transfer($this->court, $mine, 200000)->assertOk();

        $this->assertSame('paid', $other->fresh()->status);
        $this->assertSame('approved', $late->fresh()->status);
        $this->assertSame('tournament_registration_unavailable', OwnerPaymentReceipt::latest('id')->value('exception_code'));
        $this->assertSame(1, FinanceException::count());
    }
}
