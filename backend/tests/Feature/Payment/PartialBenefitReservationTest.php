<?php

namespace Tests\Feature\Payment;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Coupon;
use App\Models\Court;
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

/**
 * Task 7.2 — points and coupon uses are reserved when a direct-owner hold is
 * created, consumed exactly once on verified confirmation, and released exactly
 * once on hold expiry (including across scheduler retries).
 */
class PartialBenefitReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_hold_reserves_point_and_coupon_then_consumes_once_on_confirmation(): void
    {
        $fixture = $this->tenant();
        $start = now()->addDays(3)->setTime(10, 0, 0);

        $response = $this->actingAs($fixture['payer'])
            ->withHeader('Idempotency-Key', 'partial-confirm')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [$start->toIso8601String()],
                'coupon_code' => 'SAVE20K',
                'loyalty_points' => 10,
                'loyalty_policy_version' => 1,
            ]);

        // Subtotal 120000 − coupon 20000 − points (10 × 2000) 20000 = 80000 payable.
        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'held')
            ->assertJsonPath('reservation.payment_model', 'direct_owner_v1')
            ->assertJsonPath('reservation.total_amount', 80000);

        $reservation = Reservation::where('code', $response->json('reservation.code'))->firstOrFail();

        // Coupon use reserved (not yet consumed) and points reserved as a hold debit.
        $this->assertSame(1, (int) $fixture['coupon']->fresh()->usage_count);
        $this->assertSame($fixture['coupon']->id, $reservation->metadata['coupon_usage']['coupon_id']);
        $this->assertNull($reservation->metadata['coupon_usage']['released_at']);
        $this->assertSame(-10, (int) PointLedger::where('source_type', 'reservation')->where('source_id', $reservation->id)->sum('delta'));
        $this->assertSame(0, $this->pointBalance($fixture));

        $attempt = OwnerPaymentAttempt::where('purpose_id', $reservation->code)->firstOrFail();
        $this->assertSame(80000, (int) $attempt->amount);

        $this->sendOwnerPayment($fixture['connection'], $fixture['secret'], $attempt->reference, 80000, 'partial-receipt');

        // Confirmation consumes the reservations once: coupon stays used, points stay spent, no release.
        $reservation->refresh();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertNull($reservation->metadata['coupon_usage']['released_at']);
        $this->assertSame(1, (int) $fixture['coupon']->fresh()->usage_count);
        $this->assertSame(0, $this->pointBalance($fixture));
        $this->assertSame(0, PointLedger::where('source_type', 'reservation_release')->where('source_id', $reservation->id)->count());
        $this->assertDatabaseCount('owner_payment_allocations', 1);
    }

    public function test_partial_hold_releases_point_and_coupon_exactly_once_on_expiry(): void
    {
        $fixture = $this->tenant();
        $start = now()->addDays(3)->setTime(10, 0, 0);

        $reservation = Reservation::where('code', $this->actingAs($fixture['payer'])
            ->withHeader('Idempotency-Key', 'partial-expire')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [$start->toIso8601String()],
                'coupon_code' => 'SAVE20K',
                'loyalty_points' => 10,
                'loyalty_policy_version' => 1,
            ])->assertCreated()->json('reservation.code'))->firstOrFail();

        $this->assertSame(1, (int) $fixture['coupon']->fresh()->usage_count);
        $this->assertSame(0, $this->pointBalance($fixture));

        $this->travel(31)->minutes();
        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());

        // Expiry releases both benefits exactly once.
        $reservation->refresh();
        $releasedAt = $reservation->metadata['coupon_usage']['released_at'];
        $this->assertSame('expired', $reservation->status);
        $this->assertNotNull($releasedAt);
        $this->assertSame(0, (int) $fixture['coupon']->fresh()->usage_count);
        $this->assertSame(10, $this->pointBalance($fixture));
        $this->assertSame(1, PointLedger::where('source_type', 'reservation_release')->where('source_id', $reservation->id)->count());

        // Re-running the sweep is idempotent: no double release.
        $this->assertSame(0, app(ExpirePendingBookings::class)->execute());
        $this->assertSame($releasedAt, $reservation->fresh()->metadata['coupon_usage']['released_at']);
        $this->assertSame(0, (int) $fixture['coupon']->fresh()->usage_count);
        $this->assertSame(10, $this->pointBalance($fixture));
        $this->assertSame(1, PointLedger::where('source_type', 'reservation_release')->where('source_id', $reservation->id)->count());
    }

    public function test_reserved_points_block_a_second_hold_beyond_available_balance(): void
    {
        $fixture = $this->tenant();
        $first = now()->addDays(3)->setTime(10, 0, 0);
        $second = now()->addDays(3)->setTime(12, 0, 0);

        $this->actingAs($fixture['payer'])
            ->withHeader('Idempotency-Key', 'concurrent-hold-1')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [$first->toIso8601String()],
                'loyalty_points' => 10,
                'loyalty_policy_version' => 1,
            ])->assertCreated();

        $this->assertSame(0, $this->pointBalance($fixture));

        // The reserved points are unavailable to a competing hold on another slot.
        $this->actingAs($fixture['payer'])
            ->withHeader('Idempotency-Key', 'concurrent-hold-2')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [$second->toIso8601String()],
                'loyalty_points' => 10,
                'loyalty_policy_version' => 1,
            ])->assertStatus(422)->assertJsonPath('message', 'Số dư điểm không đủ để thực hiện quy đổi.');

        $this->assertSame(1, Reservation::where('user_id', $fixture['payer']->id)->count());
        $this->assertSame(1, PointLedger::where('source_type', 'reservation')->count());
    }

    public function test_stale_loyalty_policy_version_is_rejected_on_direct_hold(): void
    {
        $fixture = $this->tenant();
        $start = now()->addDays(3)->setTime(10, 0, 0);

        $this->actingAs($fixture['payer'])
            ->withHeader('Idempotency-Key', 'stale-policy-hold')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [$start->toIso8601String()],
                'loyalty_points' => 10,
                'loyalty_policy_version' => 99,
            ])->assertStatus(422)->assertJsonPath('message', 'Chính sách đổi điểm đã thay đổi. Vui lòng tải lại xem trước.');

        $this->assertSame(0, Reservation::where('user_id', $fixture['payer']->id)->count());
        $this->assertSame(10, $this->pointBalance($fixture));
        $this->assertSame(0, PointLedger::where('source_type', 'reservation')->count());
    }

    public function test_late_owner_receipt_after_expiry_does_not_reconfirm_or_reconsume(): void
    {
        $fixture = $this->tenant();
        $start = now()->addDays(3)->setTime(10, 0, 0);

        $reservation = Reservation::where('code', $this->actingAs($fixture['payer'])
            ->withHeader('Idempotency-Key', 'lost-response-hold')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [$start->toIso8601String()],
                'coupon_code' => 'SAVE20K',
                'loyalty_points' => 10,
                'loyalty_policy_version' => 1,
            ])->assertCreated()->json('reservation.code'))->firstOrFail();
        $attempt = OwnerPaymentAttempt::where('purpose_id', $reservation->code)->firstOrFail();

        // Owner's transfer response is lost; the hold expires and releases benefits.
        $this->travel(31)->minutes();
        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());
        $this->assertSame(10, $this->pointBalance($fixture));
        $this->assertSame(0, (int) $fixture['coupon']->fresh()->usage_count);

        // A late receipt arrives afterwards: it must not reconfirm or re-consume anything.
        $this->sendOwnerPayment($fixture['connection'], $fixture['secret'], $attempt->reference, 80000, 'late-receipt');

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(10, $this->pointBalance($fixture));
        $this->assertSame(0, (int) $fixture['coupon']->fresh()->usage_count);
        $this->assertDatabaseCount('owner_payment_allocations', 0);
        $this->assertDatabaseMissing('owner_payment_receipts', ['provider_event_id' => 'late-receipt', 'status' => 'allocated']);
    }

    /**
     * @return array{organization: Organization, owner: User, payer: User, venue: Venue, court: Court, connection: ReceivingConnection, secret: string, coupon: Coupon}
     */
    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Benefit Reservation Club',
            'slug' => 'benefit-reservation-'.Str::lower(Str::random(8)),
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
            'name' => 'Benefit Venue',
            'slug' => 'benefit-venue-'.Str::lower(Str::random(8)),
            'address' => '101 Test Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Benefit Court', 'is_active' => true]);
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 120000,
        ]);
        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'percentage', 'fee_value' => 10, 'refund_due_hours' => 48],
            ],
        ]);
        LoyaltyRule::create([
            'organization_id' => $organization->id,
            'name' => 'Base redemption',
            'version' => 1,
            'earn_points_per_100_vnd' => 0,
            'redeem_points_per_100_vnd' => 0,
            'status' => 'published',
            'metadata' => ['points_redeemed' => 100, 'reward_value_vnd' => 200000],
            'published_at' => now(),
        ]);
        PointLedger::create([
            'organization_id' => $organization->id,
            'user_id' => $payer->id,
            'loyalty_rule_id' => null,
            'delta' => 10,
            'balance_after' => 10,
            'source_type' => 'seed',
            'source_id' => 1,
        ]);
        $coupon = Coupon::create([
            'organization_id' => $organization->id,
            'code' => 'SAVE20K',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
            'usage_limit' => 1,
            'usage_count' => 0,
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
        $connection->setAccountDetails('Benefit Owner', '0123456789');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'payer', 'venue', 'court', 'connection', 'secret', 'coupon');
    }

    /** @param array{organization: Organization, payer: User} $fixture */
    private function pointBalance(array $fixture): int
    {
        return (int) PointLedger::query()
            ->where('organization_id', $fixture['organization']->id)
            ->where('user_id', $fixture['payer']->id)
            ->sum('delta');
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
        $response = $this->call('POST', '/api/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
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
