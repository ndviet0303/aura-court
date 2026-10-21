<?php

namespace Tests\Feature\Payment;

use App\Models\FinanceException;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** FR-81 and UC-4.12: the owner looks up direct-owner payment exceptions, read-only, without payouts (design D8). */
class OwnerPaymentExceptionLookupTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->fixture = $this->directOwnerCourt();
    }

    public function test_a_transfer_after_cancellation_shows_its_booking_and_viewing_changes_nothing(): void
    {
        $reservation = $this->held();
        $this->actingAs($this->fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $this->fixture['organization']->id,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson("/api/reservations/{$reservation->code}/cancel-payment")->assertOk();
        $attempt = $this->attempt($reservation);
        $receipt = $this->payOwner($this->fixture['connection'], $this->fixture['secret'], $attempt->reference, (int) $attempt->amount, 'after-cancel');
        $before = [$reservation->fresh()->updated_at, $receipt->fresh()->updated_at, FinanceException::withoutGlobalScopes()->firstOrFail()->updated_at];

        $this->lookup()->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'transfer_after_expiry')
            ->assertJsonPath('data.0.amount', (int) $attempt->amount)
            ->assertJsonPath('data.0.payment_code', $attempt->reference)
            ->assertJsonPath('data.0.event_at', $receipt->event_at->toISOString())
            ->assertJsonPath('data.0.connection', ['bank_code' => 'MBBank', 'account_last_four' => '4321'])
            ->assertJsonPath('data.0.status', 'open')
            ->assertJsonPath('data.0.purpose', 'booking')
            ->assertJsonPath('data.0.booking_code', $reservation->code);

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertEquals($before, [$reservation->fresh()->updated_at, $receipt->fresh()->updated_at, FinanceException::withoutGlobalScopes()->firstOrFail()->updated_at]);
    }

    public function test_an_unknown_code_shows_no_booking_even_when_its_amount_matches_a_pending_one(): void
    {
        $reservation = $this->held();

        $this->payOwner($this->fixture['connection'], $this->fixture['secret'], 'ACNOSUCHREQUEST00001', (int) $reservation->total_amount, 'unknown-code');

        $this->lookup()->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'unmatched_reference')
            ->assertJsonPath('data.0.amount', (int) $reservation->total_amount)
            ->assertJsonPath('data.0.payment_code', 'ACNOSUCHREQUEST00001')
            ->assertJsonPath('data.0.purpose', null)
            ->assertJsonPath('data.0.booking_code', null);
        $this->assertSame('held', $reservation->fresh()->status);
    }

    public function test_short_excess_and_late_transfers_name_their_booking(): void
    {
        $reservation = $this->held();
        $attempt = $this->attempt($reservation);
        $amount = (int) $attempt->amount;

        $this->payOwner($this->fixture['connection'], $this->fixture['secret'], $attempt->reference, $amount - 10000, 'short');
        $this->payOwner($this->fixture['connection'], $this->fixture['secret'], $attempt->reference, $amount + 10000, 'excess');
        $this->travelTo($attempt->expires_at->copy()->addMinute());
        $this->payOwner($this->fixture['connection'], $this->fixture['secret'], $attempt->reference, $amount, 'late');

        $rows = collect($this->lookup()->assertOk()->json('data'))->keyBy('code');
        $this->assertEqualsCanonicalizing(['partial_amount', 'excess_amount', 'transfer_after_expiry'], $rows->keys()->all());
        $this->assertSame([$reservation->code], $rows->pluck('booking_code')->unique()->values()->all());
        $this->assertSame([$amount - 10000, $amount + 10000, $amount], [$rows['partial_amount']['amount'], $rows['excess_amount']['amount'], $rows['transfer_after_expiry']['amount']]);
    }

    public function test_the_lookup_stays_open_while_payouts_are_off_and_the_legacy_console_does_not(): void
    {
        config(['features.flags.marketplace_payouts' => false]);
        $this->payOwner($this->fixture['connection'], $this->fixture['secret'], 'ACNOSUCHREQUEST00002', 50000, 'payouts-off');

        $this->lookup()->assertOk()->assertJsonCount(1, 'data');
        $this->asOwner()->getJson('/api/finance/exceptions')->assertNotFound()->assertJsonPath('code', 'CAPABILITY_DISABLED');
        $this->asOwner()->getJson('/api/capabilities')->assertOk()
            ->assertJsonPath('capabilities.marketplace_payouts', false)
            ->assertJsonPath('capabilities.direct_owner_payments', true);
    }

    public function test_the_lookup_follows_direct_owner_payments(): void
    {
        config(['features.flags.direct_owner_payments' => false]);

        $this->lookup()->assertNotFound()->assertJsonPath('code', 'CAPABILITY_DISABLED');
    }

    public function test_staff_customers_and_other_owners_are_refused(): void
    {
        $this->payOwner($this->fixture['connection'], $this->fixture['secret'], 'ACNOSUCHREQUEST00003', 50000, 'refused');
        $staff = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $this->fixture['organization']->id,
            'user_id' => $staff->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);
        $otherOwner = $this->otherOrganization()['owner'];

        foreach ([$staff, $this->fixture['payer'], $otherOwner] as $reader) {
            $response = $this->lookup($reader)->assertForbidden();
            $this->assertArrayNotHasKey('data', $response->json());
        }
    }

    public function test_each_owner_sees_only_their_own_organization(): void
    {
        $other = $this->otherOrganization();
        $this->payOwner($this->fixture['connection'], $this->fixture['secret'], 'ACNOSUCHREQUEST00004', 41000, 'org-a');
        $this->payOwner($other['connection'], $other['secret'], 'ACNOSUCHREQUEST00005', 42000, 'org-b');

        $this->lookup()->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 41000);
        $this->actingAs($other['owner'])->withHeaders(['X-Organization-ID' => (string) $other['organization']->id])
            ->getJson('/api/owner-payment-exceptions')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 42000);
    }

    public function test_exceptions_recorded_before_the_lookup_are_backfilled_from_their_receipt(): void
    {
        $reservation = $this->held();
        $attempt = $this->attempt($reservation);
        $receipt = $this->payOwner($this->fixture['connection'], $this->fixture['secret'], $attempt->reference, (int) $attempt->amount - 10000, 'old-row');
        $exception = FinanceException::withoutGlobalScopes()->firstOrFail();
        $exception->forceFill(['metadata' => ['provider' => 'sepay', 'credential_version' => 1, 'exception_code' => 'partial_amount']])->save();

        $migration = require database_path('migrations/2026_10_21_000002_backfill_owner_payment_exception_lookup.php');
        $migration->up();
        $backfilled = $exception->fresh()->metadata;
        $migration->up();

        $this->assertSame($backfilled, $exception->fresh()->metadata);
        $this->assertEquals([
            'purpose' => 'booking',
            'purpose_id' => $reservation->code,
            'payment_code' => $receipt->payment_code,
            'event_at' => $receipt->event_at->toIso8601String(),
            'bank_code' => 'MBBank',
            'account_last_four' => '4321',
        ], collect($exception->fresh()->metadata)->only(['purpose', 'purpose_id', 'payment_code', 'event_at', 'bank_code', 'account_last_four'])->all());
        $this->assertSame(OwnerPaymentReceipt::STATUS_EXCEPTION, $receipt->fresh()->status);
    }

    private function start(): CarbonInterface
    {
        return now()->addDays(2)->setTime(18, 0);
    }

    private function held(): Reservation
    {
        $code = $this->checkoutFor($this->fixture['payer'], $this->fixture['organization'], $this->fixture['court'], [$this->start()])
            ->assertCreated()->assertJsonPath('reservation.status', 'held')->json('reservation.code');

        return Reservation::where('code', $code)->firstOrFail();
    }

    private function attempt(Reservation $reservation): OwnerPaymentAttempt
    {
        return OwnerPaymentAttempt::where('purpose_id', $reservation->code)->firstOrFail();
    }

    private function asOwner(): self
    {
        return $this->actingAs($this->fixture['owner'])->withHeaders(['X-Organization-ID' => (string) $this->fixture['organization']->id]);
    }

    private function lookup(?User $reader = null): TestResponse
    {
        return $this->actingAs($reader ?? $this->fixture['owner'])
            ->withHeaders(['X-Organization-ID' => (string) $this->fixture['organization']->id])
            ->getJson('/api/owner-payment-exceptions');
    }

    /** A second direct-owner organization in the pilot alongside the first. */
    private function otherOrganization(): array
    {
        $first = $this->fixture['organization'];
        $other = $this->directOwnerCourt();
        config(['features.pilot_organizations' => [(string) $first->id, (string) $other['organization']->id]]);

        return $other;
    }
}
