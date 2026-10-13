<?php

namespace Tests\Feature\Payment;

use App\Models\Coupon;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\PointLedger;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerRefundInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_reviews_org_scoped_refund_obligation_inbox_and_non_owner_is_forbidden(): void
    {
        $fixture = $this->paidReservation();
        $organization = $fixture['organization'];
        $owner = $fixture['owner'];
        $payer = $fixture['payer'];
        $reservation = $fixture['reservation'];
        $slotIds = $fixture['slots']->pluck('id')->map(fn ($id) => (int) $id)->all();

        // The payer creates a refund obligation through the canonical v2 request path.
        Sanctum::actingAs($payer);
        $created = $this->withHeader('X-Organization-ID', (string) $organization->id)
            ->postJson("/api/reservations/{$reservation->code}/refund-requests", [
                'slot_ids' => [$slotIds[0]],
                'reason' => 'Cancel one court hour',
                'idempotency_key' => 'inbox-refund-1',
                'recipient' => [
                    'bank_code' => 'MBBank',
                    'account_name' => 'Payer Name',
                    'account_number' => '1234567890',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', OwnerRefundObligation::STATUS_REQUESTED);
        $refundId = (int) $created->json('data.id');
        $referenceCode = $created->json('data.reference_code');
        $this->assertNotEmpty($referenceCode);

        // An organization owner sees the obligation in the org-scoped inbox.
        Sanctum::actingAs($owner);
        $inbox = $this->withHeader('X-Organization-ID', (string) $organization->id)
            ->getJson('/api/refund-requests?'.http_build_query(['organization_id' => $organization->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $refundId)
            ->assertJsonPath('data.0.reference_code', $referenceCode)
            ->assertJsonPath('data.0.status', OwnerRefundObligation::STATUS_REQUESTED);
        $this->assertIsArray($inbox->json('data.0.evidence'));

        // The status filter narrows the inbox by obligation status.
        $this->withHeader('X-Organization-ID', (string) $organization->id)
            ->getJson('/api/refund-requests?'.http_build_query([
                'organization_id' => $organization->id,
                'status' => OwnerRefundObligation::STATUS_APPROVED,
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // A customer/non-owner member cannot review refund obligations.
        Sanctum::actingAs($payer);
        $this->withHeader('X-Organization-ID', (string) $organization->id)
            ->getJson('/api/refund-requests?'.http_build_query(['organization_id' => $organization->id]))
            ->assertForbidden();
    }

    private function paidReservation(): array
    {
        $organization = Organization::create([
            'name' => 'Refund Club',
            'slug' => 'refund-club-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $payer = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $payer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Refund Venue',
            'slug' => 'refund-venue-'.Str::lower(Str::random(8)),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Refund Court', 'is_active' => true]);
        $coupon = Coupon::create([
            'organization_id' => $organization->id,
            'code' => 'REFUND10',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'usage_limit' => 10,
            'usage_count' => 1,
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'organization_id' => $organization->id,
            'user_id' => $payer->id,
            'court_id' => $court->id,
            'code' => 'RSV-REFUND-'.Str::upper(Str::random(8)),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 160000,
            'hold_expires_at' => now()->addMinutes(30),
            'payment_model' => 'direct_owner_v1',
            'metadata' => [
                'coupon_id' => $coupon->id,
                'coupon_usage_snapshot' => ['usage_count_reserved' => true, 'release_on_full_cancellation' => true, 'discount_amount' => 40000],
                'loyalty_points_redeemed' => 10,
                'loyalty_snapshot' => ['rule_id' => null, 'rule_version' => 1, 'points_spent' => 10],
                'booking_policy_snapshot' => [
                    'version' => 1,
                    'settings' => ['cancellation' => ['deadline_hours' => 24, 'fee_type' => 'percentage', 'fee_value' => 10, 'refund_due_hours' => 48]],
                ],
            ],
        ]);
        $slots = collect();
        foreach ([16, 17] as $hour) {
            $starts = now()->addDays(3)->setTime($hour, 0);
            $slot = ReservationSlot::create([
                'organization_id' => $organization->id,
                'reservation_id' => $reservation->id,
                'court_id' => $court->id,
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addHour(),
                'unit_price' => 100000,
                'status' => 'held',
            ]);
            ReservationEntitlement::create([
                'organization_id' => $organization->id,
                'reservation_id' => $reservation->id,
                'reservation_slot_id' => $slot->id,
                'user_id' => $payer->id,
                'court_id' => $court->id,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'amount' => 100000,
                'status' => 'held',
            ]);
            $slots->push($slot);
        }
        PointLedger::create([
            'organization_id' => $organization->id,
            'user_id' => $payer->id,
            'delta' => -10,
            'balance_after' => -10,
            'source_type' => 'reservation',
            'source_id' => $reservation->id,
            'metadata' => ['points_spent' => 10],
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
        $connection->setAccountDetails('Refund Venue Owner', '0123456789');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();

        $attempt = app(OwnerPaymentAttemptService::class)->createAttempt(
            $connection,
            $payer,
            'booking',
            $reservation->code,
            'ACREFUND'.Str::upper(Str::random(10)),
            160000,
            'VND',
            now()->addMinutes(20),
            'refund-test-attempt-'.Str::uuid(),
        );
        $payload = [
            'id' => 'refund-receipt-'.Str::uuid(),
            'transactionDate' => now()->addSeconds(2)->toIso8601String(),
            'accountNumber' => '0123456789',
            'code' => $attempt->reference,
            'content' => $attempt->reference,
            'transferType' => 'in',
            'transferAmount' => 160000,
            'referenceCode' => 'BANK-REFUND-TEST',
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $this->call('POST', '/api/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, $secret),
        ], $raw)->assertOk();
        $receipt = OwnerPaymentReceipt::where('provider_event_id', $payload['id'])->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }

        $reservation->refresh();
        $reservation->slots()->where('status', 'held')->update(['status' => 'confirmed']);
        $reservation->entitlements()->where('status', 'held')->update(['status' => 'active']);

        return [
            'organization' => $organization,
            'owner' => $owner,
            'payer' => $payer,
            'venue' => $venue,
            'reservation' => $reservation->fresh(),
            'slots' => $slots,
            'coupon' => $coupon,
            'attempt' => $attempt->fresh(),
            'allocation_id' => $attempt->allocations()->value('id'),
        ];
    }
}
