<?php

namespace Tests\Feature\Booking;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\PriceRule;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Attendance\AttendanceService;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Social\MarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketplaceTransferVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_rechecks_dispute_restriction_venue_policy_and_cutoff(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();

        $marketplace = app(MarketplaceService::class);

        // 1. Restriction: restricted seller cannot list
        $fixture['seller']->update(['status' => 'suspended']);
        $this->expectException(HttpException::class);
        $marketplace->list($entitlement, $fixture['seller'], 180000);
    }

    public function test_list_rejects_disputed_reservations_and_venue_policy_prohibitions(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();
        $marketplace = app(MarketplaceService::class);

        // 1. Dispute test: reservation marked disputed or active dispute case
        $reservation->update(['status' => 'disputed']);
        try {
            $marketplace->list($entitlement, $fixture['seller'], 180000);
            $this->fail('Expected listing disputed reservation to fail.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Disputed', $e->getMessage());
        }
        $reservation->update(['status' => 'confirmed']);
        $entitlement->refresh();

        // 2. Policy test: transfer_allowed = false
        $policy = $fixture['venue']->currentBookingPolicy;
        $policy->update([
            'settings' => array_merge($policy->settings, [
                'transfer' => ['transfer_allowed' => false, 'cutoff_hours' => 2],
            ]),
        ]);
        try {
            $marketplace->list($entitlement, $fixture['seller'], 180000);
            $this->fail('Expected listing with transfer_allowed=false to fail.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('not allowed', $e->getMessage());
        }
    }

    public function test_purchase_rechecks_disputes_restrictions_policy_and_cutoff_at_commit(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();
        $marketplace = app(MarketplaceService::class);

        $listing = $marketplace->list($entitlement, $fixture['seller'], 180000);

        // Check 1: Dispute at commit time
        $reservation->update(['status' => 'disputed']);
        try {
            $marketplace->purchase($listing, $fixture['buyer'], 'idemp-commit-disp-1', 'buyer-pay-1', 'seller-ref-1', true);
            $this->fail('Expected commit on disputed reservation to fail.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('dispute', $e->getMessage());
        }
        $reservation->update(['status' => 'confirmed']);

        // Check 2: Buyer becomes restricted
        $fixture['buyer']->update(['status' => 'suspended']);
        try {
            $marketplace->purchase($listing, $fixture['buyer'], 'idemp-commit-buyer-res-1', 'buyer-pay-1', 'seller-ref-1', true);
            $this->fail('Expected commit with restricted buyer to fail.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $fixture['buyer']->update(['status' => 'active']);

        // Check 3: Venue policy revoked transfer_allowed at commit time
        $policy = $fixture['venue']->currentBookingPolicy;
        $policy->update([
            'settings' => array_merge($policy->settings, [
                'transfer' => ['transfer_allowed' => false, 'cutoff_hours' => 2],
            ]),
        ]);
        try {
            $marketplace->purchase($listing, $fixture['buyer'], 'idemp-commit-policy-revoked-1', 'buyer-pay-1', 'seller-ref-1', true);
            $this->fail('Expected commit with transfer_allowed=false to fail.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('longer allowed', $e->getMessage());
        }
        $policy->update([
            'settings' => array_merge($policy->settings, [
                'transfer' => ['transfer_allowed' => true, 'cutoff_hours' => 2, 'seller_reimbursement_required' => true],
            ]),
        ]);

        // Check 4: Cutoff passes
        $listing->update(['cutoff_at' => now()->subMinute()]);
        try {
            $marketplace->purchase($listing, $fixture['buyer'], 'idemp-commit-cutoff-1', 'buyer-pay-1', 'seller-ref-1', true);
            $this->fail('Expected commit after cutoff to fail.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('cutoff', $e->getMessage());
        }
    }

    public function test_fake_buyer_payment_reference_is_rejected_and_does_not_transfer_entitlement(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();
        $marketplace = app(MarketplaceService::class);

        $listing = $marketplace->list($entitlement, $fixture['seller'], 180000);

        // Buyer hits the purchase HTTP endpoint sending a fabricated/fake buyer payment reference
        $response = $this->actingAs($fixture['buyer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'fake-buyer-ref-test-'.uniqid(),
            ])
            ->postJson('/api/marketplace/listings/'.$listing->id.'/purchase', [
                'buyer_payment_reference' => 'FAKE-UNVERIFIED-BUYER-REF',
                'seller_refund_reference' => 'SOME-SELLER-REF',
                'seller_refund_accepted' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Unverified buyer payment reference: verified direct-owner payment receipt is required.']);

        // Entitlement remains active with seller, listing is still listed
        $this->assertSame('active', $entitlement->fresh()->status);
        $this->assertSame('listed', $listing->fresh()->status);
        $this->assertSame($fixture['seller']->id, $reservation->fresh()->user_id);
    }

    public function test_fake_client_refund_flag_does_not_transfer_entitlement(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();
        $marketplace = app(MarketplaceService::class);

        $listing = $marketplace->list($entitlement, $fixture['seller'], 180000);

        // Buyer checks out to initiate verified attempt
        $attempt = $marketplace->checkout($listing, $fixture['buyer']);
        $this->assertSame('transfer_consideration', $attempt->purpose);

        // Send verified incoming payment via SePay webhook
        $this->sendOwnerPayment($fixture, $attempt->reference, 180000, 'buyer-pay-evt-1');
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attempt->fresh()->status);

        // Buyer hits the purchase HTTP endpoint with verified buyer payment reference,
        // but with self-declared seller_refund_accepted=true without any seller refund reference
        $response = $this->actingAs($fixture['buyer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'fake-flag-test-'.uniqid(),
            ])
            ->postJson('/api/marketplace/listings/'.$listing->id.'/purchase', [
                'buyer_payment_reference' => $attempt->reference,
                'seller_refund_accepted' => true, // Client self-declared flag with no reference!
            ]);

        $response->assertOk();
        $this->assertSame('compensated', $response->json('transfer.status'));

        // Seller retains entitlement!
        $this->assertSame('active', $entitlement->fresh()->status);
        $this->assertSame('listed', $listing->fresh()->status);
        $this->assertDatabaseHas('finance_exceptions', [
            'organization_id' => $fixture['organization']->id,
            'type' => 'marketplace_transfer_failed',
            'status' => 'open',
        ]);
    }

    public function test_verified_buyer_payment_with_fake_non_empty_seller_refund_reference_compensates_and_does_not_transfer(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();
        $marketplace = app(MarketplaceService::class);

        $listing = $marketplace->list($entitlement, $fixture['seller'], 180000);

        // Buyer checks out to initiate verified attempt
        $attempt = $marketplace->checkout($listing, $fixture['buyer']);
        $this->sendOwnerPayment($fixture, $attempt->reference, 180000, 'buyer-pay-evt-2');
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attempt->fresh()->status);

        // Buyer provides verified buyer payment reference, but sends a FAKE non-empty seller refund reference
        // that has NO matching OwnerRefundObligation or verified evidence in the DB
        $response = $this->actingAs($fixture['buyer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'fake-non-empty-seller-ref-'.uniqid(),
            ])
            ->postJson('/api/marketplace/listings/'.$listing->id.'/purchase', [
                'buyer_payment_reference' => $attempt->reference,
                'seller_refund_reference' => 'FABRICATED-SELLER-REIMBURSE-REF-999',
                'seller_refund_accepted' => true,
            ]);

        $response->assertOk();
        $this->assertSame('compensated', $response->json('transfer.status'));

        // Entitlement is NOT transferred: seller retains active status, listing remains listed
        $this->assertSame('active', $entitlement->fresh()->status);
        $this->assertSame('listed', $listing->fresh()->status);
        $this->assertSame($fixture['seller']->id, $reservation->fresh()->user_id);

        $this->assertDatabaseHas('finance_exceptions', [
            'organization_id' => $fixture['organization']->id,
            'type' => 'marketplace_transfer_failed',
            'status' => 'open',
        ]);
    }

    public function test_atomic_transfer_preserves_original_price_and_switches_check_in_authority(): void
    {
        $fixture = $this->tenant();
        // Original reservation is 200,000 VND, scheduled for today > 2 hours in the future
        $slotTime = now(config('app.timezone'))->addHours(3);
        $reservation = $this->createConfirmedReservation($fixture, 200000, $slotTime);
        $sellerEntitlement = $reservation->entitlements()->firstOrFail();
        $this->assertSame(200000, $sellerEntitlement->amount);

        $marketplace = app(MarketplaceService::class);
        // Asking price is discounted to 150,000 VND
        $listing = $marketplace->list($sellerEntitlement, $fixture['seller'], 150000);

        // 1. Buyer initiates checkout to get payment attempt
        $attempt = $marketplace->checkout($listing, $fixture['buyer']);
        $this->assertSame(150000, (int) $attempt->amount);

        // 2. Buyer pays via verified direct-owner SePay webhook
        $this->sendOwnerPayment($fixture, $attempt->reference, 150000, 'buyer-pay-atomic-evt');
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attempt->fresh()->status);

        // 3. Owner executes seller reimbursement and records evidence / obligation
        $executionRef = 'ACREIMB'.Str::upper(Str::random(12));
        $this->sendOwnerPayment($fixture, $executionRef, 150000, 'seller-reimburse-evt-1');

        // 4. Purchase with verified buyer payment reference AND verified seller reimbursement reference
        $response = $this->actingAs($fixture['buyer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'idemp-atomic-transfer-'.uniqid(),
            ])
            ->postJson('/api/marketplace/listings/'.$listing->id.'/purchase', [
                'buyer_payment_reference' => $attempt->reference,
                'seller_refund_reference' => $executionRef,
                'seller_refund_accepted' => true,
            ]);

        $response->assertOk();
        $this->assertSame('completed', $response->json('transfer.status'));
        $this->assertSame('sold', $listing->fresh()->status);

        // 5. Seller entitlement is revoked
        $this->assertSame('transferred', $sellerEntitlement->fresh()->status);

        // 6. Buyer entitlement is active with ORIGINAL PRICE PRESERVED (200,000 not 150,000)
        $buyerEntitlement = ReservationEntitlement::where('user_id', $fixture['buyer']->id)->firstOrFail();
        $this->assertSame('active', $buyerEntitlement->status);
        $this->assertSame(200000, $buyerEntitlement->amount);

        // 7. Reservation holder atomically switched to buyer
        $this->assertSame($fixture['buyer']->id, $reservation->fresh()->user_id);

        // 8. Seller attempting to check in with prior entitlement pass is REJECTED
        $attendanceService = app(AttendanceService::class);
        try {
            Carbon::setTestNow($slotTime);
            try {
                $attendanceService->checkIn(
                    $fixture['staff'],
                    $fixture['organization']->id,
                    'AURA_ENTITLEMENT_'.$sellerEntitlement->id,
                    'Seller check-in attempt after transfer',
                    $fixture['seller']
                );
                $this->fail('Expected seller check-in to be rejected.');
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
                $this->assertStringContainsString('transferred and check-in pass has been revoked', $e->getMessage());
            }

            // 9. Buyer checking in with new entitlement pass SUCCEEDS
            $buyerPassResult = $attendanceService->checkIn(
                $fixture['staff'],
                $fixture['organization']->id,
                'AURA_ENTITLEMENT_'.$buyerEntitlement->id,
                'Buyer check-in via pass',
                $fixture['buyer']
            );
            $this->assertSame('checked_in', $buyerPassResult['attendance']->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Marketplace Org',
            'slug' => 'market-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $owner = User::factory()->create();
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $staff = User::factory()->create();

        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $seller->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $buyer->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $staff->id, 'role' => 'staff', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Marketplace Venue',
            'slug' => 'market-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Court M1', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 30, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'percentage', 'fee_value' => 10, 'refund_due_hours' => 48],
                'change' => ['deadline_hours' => 12, 'fee_type' => 'none', 'fee_value' => 0],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true, 'transfer_allowed' => true],
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
        $connection->setAccountDetails('Marketplace Owner', '0912345678');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();

        config([
            'features.flags.direct_owner_payments' => true,
            'features.flags.marketplace_slot_resale' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'seller', 'buyer', 'staff', 'venue', 'court', 'connection', 'secret');
    }

    private function sendOwnerPayment(array $fixture, string $reference, int $amount, string $eventId): void
    {
        $connection = $fixture['connection'];
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
        $this->call('POST', '/api/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, $fixture['secret']),
        ], $raw)->assertOk();

        $receipt = OwnerPaymentReceipt::where('provider_event_id', $eventId)->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }
    }

    private function createConfirmedReservation(array $fixture, int $amount, Carbon $startsAt): Reservation
    {
        $reservation = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['seller']->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RSV-MKT-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => $amount,
            'payment_model' => 'direct_owner_v1',
            'confirmed_at' => now(),
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'unit_price' => $amount,
            'status' => 'confirmed',
            'snapshot' => [],
        ]);

        ReservationEntitlement::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'court_id' => $fixture['court']->id,
            'user_id' => $fixture['seller']->id,
            'entitlement_type' => 'court_reservation',
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'amount' => $amount,
        ]);

        return $reservation;
    }
}
