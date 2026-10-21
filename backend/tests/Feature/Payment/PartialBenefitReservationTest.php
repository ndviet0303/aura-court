<?php

namespace Tests\Feature\Payment;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/**
 * A direct-owner hold takes no coupon or points (design D21, D28), and a receipt that arrives after the hold
 * expired does not bring it back.
 */
class PartialBenefitReservationTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    public function test_direct_hold_naming_a_coupon_or_points_is_refused_and_creates_nothing(): void
    {
        $fixture = $this->tenant();

        $this->actingAs($fixture['payer'])
            ->withHeader('Idempotency-Key', 'benefit-hold')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $fixture['court']->id,
                'slots' => [now()->addDays(3)->setTime(10, 0, 0)->toIso8601String()],
                'expected_total' => 80000,
                'coupon_code' => 'SAVE20K',
                'loyalty_points' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'coupon_code' => 'Mã coupon không còn áp dụng cho đặt sân.',
                'loyalty_points' => 'Đặt sân không còn dùng điểm tích lũy.',
            ]);

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('owner_payment_attempts', 0);
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
                'expected_total' => 120000,
            ])->assertCreated()->json('reservation.code'))->firstOrFail();
        $attempt = OwnerPaymentAttempt::where('purpose_id', $reservation->code)->firstOrFail();

        // Owner's transfer response is lost; the hold expires.
        $this->travel(31)->minutes();
        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());

        // A late receipt arrives afterwards: it must not reconfirm or re-consume anything.
        $this->sendOwnerPayment($fixture['connection'], $fixture['secret'], $attempt->reference, 120000, 'late-receipt');

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertDatabaseCount('owner_payment_allocations', 0);
        $this->assertDatabaseMissing('owner_payment_receipts', ['provider_event_id' => 'late-receipt', 'status' => 'allocated']);
    }

    /**
     * @return array{organization: Organization, owner: User, payer: User, venue: Venue, court: Court, connection: ReceivingConnection, secret: string}
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
        $this->priceCourt($court, 120000);
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
        $response = $this->call('POST', '/api/v1/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Apikey '.$secret,
        ], $raw);
        $response->assertOk();
        $receipt = OwnerPaymentReceipt::where('provider_event_id', $eventId)->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }
    }
}
