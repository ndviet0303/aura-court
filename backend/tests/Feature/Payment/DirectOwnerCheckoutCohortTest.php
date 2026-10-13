<?php

namespace Tests\Feature\Payment;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\LoyaltyRule;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\PointLedger;
use App\Models\PriceRule;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectOwnerCheckoutCohortTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_checkout_creates_a_policy_bound_hold_and_confirms_from_owner_receipt(): void
    {
        $fixture = $this->tenant();
        $start = now()->addDays(3)->setTime(10, 0, 0);
        CourtPriceSlot::create([
            'court_id' => $fixture['court']->id,
            'weekday' => $start->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 120000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($fixture['payer'])->withHeader('Idempotency-Key', 'direct-checkout-1')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [$start->toIso8601String()],
            ]);

        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'held')
            ->assertJsonPath('reservation.payment_model', 'direct_owner_v1')
            ->assertJsonPath('reservation.total_amount', 120000);
        $reservation = Reservation::where('code', $response->json('reservation.code'))->firstOrFail();

        $this->assertSame(1, data_get($reservation->metadata, 'booking_policy_snapshot.version'));
        $this->assertTrue($reservation->hold_expires_at->between(now()->addMinutes(29), now()->addMinutes(31)));
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $reservation->id, 'status' => 'held']);
        $this->assertDatabaseHas('reservation_entitlements', ['reservation_id' => $reservation->id, 'status' => 'held']);
        $this->assertDatabaseCount('wallet_accounts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('payments', 0);

        $attempt = OwnerPaymentAttempt::where('purpose', 'booking')->where('purpose_id', $reservation->code)->firstOrFail();
        $this->assertSame(120000, (int) $attempt->amount);
        $this->assertSame($fixture['connection']->id, (int) $attempt->receiving_connection_id);
        $this->getJson("/api/reservations/{$reservation->code}/payment")
            ->assertOk()
            ->assertJsonPath('payment_attempt.receiver.account_number', '0123456789')
            ->assertJsonMissingPath('wallet');

        $this->sendOwnerPayment($fixture['connection'], $fixture['secret'], $attempt->reference, 120000, 'direct-receipt');

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertDatabaseHas('owner_payment_receipts', ['provider_event_id' => 'direct-receipt', 'status' => 'allocated']);
        $this->actingAs($fixture['payer'])->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->getJson("/api/reservations/{$reservation->code}")
            ->assertOk()
            ->assertJsonPath('reservation.code', $reservation->code)
            ->assertJsonPath('reservation.payment_model', 'direct_owner_v1');
        $this->actingAs($fixture['payer'])->getJson("/api/reservations/{$reservation->code}/payment")
            ->assertOk()
            ->assertJsonPath('reservation.status', 'confirmed')
            ->assertJsonPath('payment_attempt.status', 'paid');
        $this->assertDatabaseCount('owner_payment_attempts', 1);
        $this->assertDatabaseCount('owner_payment_allocations', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_v2_direct_checkout_creates_receiver_bound_hold_with_policy_ttl_without_wallet_debit(): void
    {
        $fixture = $this->tenant();
        $start = now()->addDays(4)->setTime(14, 0, 0);
        PriceRule::create([
            'organization_id' => $fixture['organization']->id,
            'court_id' => $fixture['court']->id,
            'type' => 'base',
            'price' => 200000,
        ]);
        $headers = [
            'X-Organization-ID' => (string) $fixture['organization']->id,
            'Idempotency-Key' => 'direct-v2-quote-1',
        ];
        $quote = $this->actingAs($fixture['payer'])->withHeaders($headers)
            ->postJson('/api/price-quotes', [
                'court_id' => $fixture['court']->id,
                'slots' => [$start->toIso8601String()],
            ])->assertCreated()->json('quote');

        $response = $this->actingAs($fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $fixture['organization']->id,
            'Idempotency-Key' => 'direct-v2-checkout-1',
        ])->postJson('/api/reservations/checkout', ['quote_id' => $quote['id']]);

        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'held')
            ->assertJsonPath('reservation.payment_model', 'direct_owner_v1')
            ->assertJsonPath('reservation.total_amount', 200000);
        $reservation = Reservation::where('code', $response->json('reservation.code'))->firstOrFail();
        $this->assertTrue($reservation->hold_expires_at->between(now()->addMinutes(29), now()->addMinutes(31)));
        $this->assertSame(1, data_get($reservation->metadata, 'booking_policy_snapshot.version'));
        $attempt = OwnerPaymentAttempt::where('purpose_id', $reservation->code)->firstOrFail();
        $this->assertSame($fixture['connection']->id, (int) $attempt->receiving_connection_id);
        $this->assertSame('pending', $attempt->status);
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $reservation->id, 'status' => 'held']);
        $this->assertDatabaseHas('reservation_entitlements', ['reservation_id' => $reservation->id, 'status' => 'held']);
        $this->assertDatabaseCount('wallet_accounts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('journals', 0);

        $this->actingAs($fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $fixture['organization']->id,
            'Idempotency-Key' => 'direct-v2-unverified-confirm',
        ])->postJson("/api/reservations/{$reservation->code}/confirm")->assertConflict();
        $this->assertSame('held', $reservation->fresh()->status);
        $this->assertSame('pending', $attempt->fresh()->status);
    }

    public function test_v2_zero_payable_direct_reservation_confirms_without_wallet_or_bank_receipt(): void
    {
        $fixture = $this->tenant();
        $start = now()->addDays(5)->setTime(16, 0, 0);
        PriceRule::create([
            'organization_id' => $fixture['organization']->id,
            'court_id' => $fixture['court']->id,
            'type' => 'base',
            'price' => 200000,
        ]);
        LoyaltyRule::create([
            'organization_id' => $fixture['organization']->id,
            'name' => 'Full zero-payable redemption',
            'version' => 1,
            'earn_points_per_100_vnd' => 0,
            'redeem_points_per_100_vnd' => 0,
            'status' => 'published',
            'metadata' => ['points_redeemed' => 100, 'reward_value_vnd' => 200000],
            'published_at' => now(),
        ]);
        PointLedger::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['payer']->id,
            'loyalty_rule_id' => null,
            'delta' => 100,
            'balance_after' => 100,
            'source_type' => 'seed',
            'source_id' => 1,
        ]);
        $quote = $this->actingAs($fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $fixture['organization']->id,
            'Idempotency-Key' => 'direct-v2-zero-quote',
        ])->postJson('/api/price-quotes', [
            'court_id' => $fixture['court']->id,
            'slots' => [$start->toIso8601String()],
        ])->assertCreated()->json('quote');

        $response = $this->actingAs($fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $fixture['organization']->id,
            'Idempotency-Key' => 'direct-v2-zero-checkout',
        ])->postJson('/api/reservations/checkout', [
            'quote_id' => $quote['id'],
            'loyalty_points' => 100,
            'loyalty_policy_version' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'confirmed')
            ->assertJsonPath('reservation.payment_model', 'direct_owner_v1')
            ->assertJsonPath('reservation.total_amount', 0);
        $reservation = Reservation::where('code', $response->json('reservation.code'))->firstOrFail();
        $this->assertTrue((bool) data_get($reservation->metadata, 'zero_payable'));
        $this->assertDatabaseCount('owner_payment_attempts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseHas('point_ledgers', [
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['payer']->id,
            'source_type' => 'reservation',
            'source_id' => $reservation->id,
            'delta' => -100,
        ]);
    }

    public function test_expiry_sweep_releases_direct_holds_and_expires_their_attempts(): void
    {
        $fixture = $this->tenant();
        $slotStart = now()->addDays(3)->setTime(10, 0, 0);
        CourtPriceSlot::create([
            'court_id' => $fixture['court']->id,
            'weekday' => $slotStart->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 120000,
            'is_active' => true,
        ]);
        $slotReservation = $this->actingAs($fixture['payer'])->withHeader('Idempotency-Key', 'direct-expiry-slot-checkout')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [$slotStart->toIso8601String()],
            ])->assertCreated()->json('reservation');
        $slotAttempt = OwnerPaymentAttempt::where('purpose_id', $slotReservation['code'])->firstOrFail();

        $quoteStart = now()->addDays(4)->setTime(14, 0, 0);
        PriceRule::create([
            'organization_id' => $fixture['organization']->id,
            'court_id' => $fixture['court']->id,
            'type' => 'base',
            'price' => 200000,
        ]);
        $quote = $this->actingAs($fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $fixture['organization']->id,
            'Idempotency-Key' => 'direct-expiry-quote',
        ])->postJson('/api/price-quotes', [
            'court_id' => $fixture['court']->id,
            'slots' => [$quoteStart->toIso8601String()],
        ])->assertCreated()->json('quote');
        $quoteReservation = $this->actingAs($fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $fixture['organization']->id,
            'Idempotency-Key' => 'direct-expiry-quote-checkout',
        ])->postJson('/api/reservations/checkout', ['quote_id' => $quote['id']])
            ->assertCreated()->json('reservation');
        $quoteAttempt = OwnerPaymentAttempt::where('purpose_id', $quoteReservation['code'])->firstOrFail();

        $this->travel(31)->minutes();
        $this->assertSame(2, app(ExpirePendingBookings::class)->execute());

        $this->assertSame('expired', Reservation::where('code', $slotReservation['code'])->firstOrFail()->status);
        $this->assertSame('expired', Reservation::where('code', $quoteReservation['code'])->firstOrFail()->status);
        $this->assertSame('expired', $slotAttempt->fresh()->status);
        $this->assertSame('expired', $quoteAttempt->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Direct Owner Club',
            'slug' => 'direct-owner-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $payer = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $payer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Direct Owner Venue',
            'slug' => 'direct-owner-venue-'.Str::lower(Str::random(8)),
            'address' => '101 Test Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Direct Court', 'is_active' => true]);
        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'percentage', 'fee_value' => 10, 'refund_due_hours' => 48],
                'change' => ['deadline_hours' => 12, 'fee_type' => 'none', 'fee_value' => 0],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true],
                'ancillary_credit_policy' => 'refund',
            ],
        ]);
        $connection = new ReceivingConnection([
            'organization_id' => $organization->id,
            'venue_id' => $venue->id,
            'owner_user_id' => $owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'owner_attested_at' => now(),
            'verified_at' => now(),
            'effective_from' => now(),
        ]);
        $connection->setAccountDetails('Direct Owner', '0123456789');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'payer', 'venue', 'court', 'connection', 'secret');
    }

    private function sendOwnerPayment(ReceivingConnection $connection, string $secret, string $reference, int $amount, string $eventId): void
    {
        $payload = [
            'id' => $eventId,
            'transactionDate' => now()->addSeconds(2)->toIso8601String(),
            'accountNumber' => $connection->accountNumber(),
            'code' => $reference,
            'content' => $reference,
            'transferType' => 'in',
            'transferAmount' => $amount,
            'referenceCode' => 'BANK-'.$eventId,
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $response = $this->call('POST', '/api/v1/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, $secret),
        ], $raw);
        $response->assertOk();
        $receipt = OwnerPaymentReceipt::where('provider_event_id', $eventId)->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }
    }
}
