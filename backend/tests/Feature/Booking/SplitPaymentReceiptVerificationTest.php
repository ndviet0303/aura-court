<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\FinanceException;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\SplitGroup;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\SplitPaymentService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class SplitPaymentReceiptVerificationTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private array $eventDates = [];

    public function test_create_split_group_creates_shares_and_payment_attempts_with_references_and_hold_expiry(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createHeldReservation($fixture, 200000);

        $response = $this->actingAs($fixture['host'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'split-create-'.uniqid(),
            ])
            ->postJson('/api/reservations/'.$reservation->id.'/split', [
                'shares' => [
                    ['amount' => 80000],
                    ['amount' => 120000],
                ],
            ]);

        $response->assertCreated();
        $splitId = (int) $response->json('split.id');
        $group = SplitGroup::with('shares')->findOrFail($splitId);

        $this->assertSame(200000, $group->expected_amount);
        $this->assertSame(0, $group->paid_amount);
        $this->assertSame('pending', $group->status);
        $this->assertFalse($group->host_covers_shortfall);
        $this->assertCount(2, $group->shares);

        $share1 = $group->shares->firstWhere('share_number', 1);
        $share2 = $group->shares->firstWhere('share_number', 2);

        $this->assertSame(80000, $share1->amount);
        $this->assertSame(120000, $share2->amount);
        $this->assertNotNull($share1->payment_reference);
        $this->assertNotNull($share2->payment_reference);

        $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$share1->id)->firstOrFail();
        $attempt2 = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$share2->id)->firstOrFail();

        $this->assertSame($share1->payment_reference, $attempt1->reference);
        $this->assertSame($share2->payment_reference, $attempt2->reference);
        $this->assertSame(80000, (int) $attempt1->amount);
        $this->assertSame(120000, (int) $attempt2->amount);
        $this->assertSame('split_share', $attempt1->purpose);

        $getResponse = $this->actingAs($fixture['host'])
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
            ->getJson('/api/split-groups/'.$group->id);

        $getResponse->assertOk();
        $sharesData = collect($getResponse->json('split.shares'));
        $this->assertCount(2, $sharesData);
        $share1Data = $sharesData->firstWhere('id', $share1->id);
        $share2Data = $sharesData->firstWhere('id', $share2->id);
        $this->assertNotNull($share1Data['payment_attempt']);
        $this->assertSame($attempt1->reference, $share1Data['payment_attempt']['reference']);
        $this->assertSame($attempt2->reference, $share2Data['payment_attempt']['reference']);
    }

    public function test_paying_share_with_unverified_client_reference_is_rejected(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createHeldReservation($fixture, 200000);
        $group = app(SplitPaymentService::class)->create($reservation, $fixture['host'], [
            ['amount' => 100000],
            ['amount' => 100000],
        ]);
        $share = $group->shares->first();

        $response = $this->actingAs($fixture['payer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'split-pay-'.uniqid(),
            ])
            ->postJson('/api/split-shares/'.$share->id.'/pay', [
                'amount' => 100000,
                'payment_reference' => 'FAKE-UNVERIFIED-REF-123',
            ]);

        $response->assertStatus(422);
        $this->assertSame('pending', $share->fresh()->status);
    }

    public function test_verified_receipts_for_all_shares_confirms_booking_once_without_wallet_debit(): void
    {
        $fixture = $this->tenant();
        $initialBalance = 5000000;
        $walletService = app(WalletPaymentService::class);
        $hostWallet = $walletService->getOrCreateWallet($fixture['host']->id, $fixture['organization']->id);
        $walletService->credit($hostWallet, $initialBalance, 'initial_topup', 1, 'Initial balance');
        $this->assertSame($initialBalance, (int) $hostWallet->fresh()->balance);

        $reservation = $this->createHeldReservation($fixture, 200000);
        $group = app(SplitPaymentService::class)->create($reservation, $fixture['host'], [
            ['amount' => 100000],
            ['amount' => 100000],
        ]);

        $share1 = $group->shares->firstWhere('share_number', 1);
        $share2 = $group->shares->firstWhere('share_number', 2);
        $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$share1->id)->firstOrFail();
        $attempt2 = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$share2->id)->firstOrFail();

        // 1. Pay share 1 via verified SePay event
        $this->sendOwnerPayment($fixture, $attempt1->reference, 100000, 'split-share-evt-1');

        $this->assertSame('paid', $share1->fresh()->status);
        $this->assertSame(100000, $group->fresh()->paid_amount);
        $this->assertSame('pending', $group->fresh()->status);
        $this->assertSame('held', $reservation->fresh()->status);

        // 2. Pay share 2 via verified SePay event
        $this->sendOwnerPayment($fixture, $attempt2->reference, 100000, 'split-share-evt-2');

        $this->assertSame('paid', $share2->fresh()->status);
        $this->assertSame(200000, $group->fresh()->paid_amount);
        $this->assertSame('confirmed', $group->fresh()->status);
        $this->assertNotNull($group->fresh()->confirmed_at);

        // Reservation is confirmed
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('confirmed', $reservation->slots()->first()->status);
        $this->assertSame('active', $reservation->entitlements()->first()->status);

        // CRITICAL INVARIANT: Host wallet is NOT debited a second time!
        $this->assertSame($initialBalance, (int) $hostWallet->fresh()->balance);
    }

    public function test_completed_split_confirms_the_mapped_reservation_and_leaves_the_legacy_booking_unchanged(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createHeldReservation($fixture, 200000);
        $slot = $reservation->slots()->firstOrFail();
        $booking = Booking::create([
            'code' => 'BK-SPLIT-'.Str::upper(Str::random(8)),
            'user_id' => $fixture['host']->id,
            'court_id' => $fixture['court']->id,
            'total_amount' => 200000,
            'status' => 'pending_payment',
            'payment_model' => 'direct_owner_v1',
        ]);
        $legacySlot = BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'unit_price' => 200000,
            'status' => 'held',
        ]);
        $mapping = LegacyBookingMapping::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'legacy_booking_id' => $booking->id,
            'legacy_code' => $booking->code,
            'legacy_status' => 'pending_payment',
            'legacy_total_amount' => 200000,
            'legacy_slot_count' => 1,
            'source_checksum' => hash('sha256', 'split-legacy'),
            'migrated_at' => now(),
        ]);
        $group = app(SplitPaymentService::class)->create($reservation, $fixture['host'], [
            ['amount' => 100000],
            ['amount' => 100000],
        ]);

        foreach ($group->shares as $share) {
            $attempt = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$share->id)->firstOrFail();
            $this->sendOwnerPayment($fixture, $attempt->reference, 100000, 'split-legacy-evt-'.$share->share_number);
        }

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('confirmed', $slot->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
        $this->assertSame('held', $legacySlot->fresh()->status);
        $this->assertSame('pending_payment', $mapping->fresh()->legacy_status);
    }

    public function test_host_shortfall_coverage_creates_payment_obligation_and_requires_verified_receipt(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createHeldReservation($fixture, 200000);
        $group = app(SplitPaymentService::class)->create($reservation, $fixture['host'], [
            ['amount' => 80000],
            ['amount' => 120000],
        ]);

        $share1 = $group->shares->firstWhere('share_number', 1);
        $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$share1->id)->firstOrFail();

        // Pay share 1
        $this->sendOwnerPayment($fixture, $attempt1->reference, 80000, 'shortfall-share-1');
        $this->assertSame('paid', $share1->fresh()->status);
        $this->assertSame(80000, $group->fresh()->paid_amount);

        // Host elects to cover shortfall (120,000)
        $coverResponse = $this->actingAs($fixture['host'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'split-cover-'.uniqid(),
            ])
            ->postJson('/api/split-groups/'.$group->id.'/cover-shortfall');
        $this->assertSame('pending', $group->fresh()->status);
        $this->assertFalse($group->fresh()->host_covers_shortfall);

        $coverageAttempt = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':coverage')->firstOrFail();
        $this->assertSame(120000, (int) $coverageAttempt->amount);
        $this->assertSame('pending', $coverageAttempt->status);
        $this->assertSame($fixture['host']->id, $coverageAttempt->user_id);

        // Self-reporting reference without verified receipt is rejected
        $fakePayResponse = $this->actingAs($fixture['host'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'split-fake-pay-'.uniqid(),
            ])
            ->postJson('/api/split-groups/'.$group->id.'/cover-shortfall', [
                'payment_reference' => 'FAKE-COVERAGE-REF',
            ]);
        $fakePayResponse->assertStatus(422);

        // Send verified payment for coverage
        $this->sendOwnerPayment($fixture, $coverageAttempt->reference, 120000, 'shortfall-coverage-evt');

        $this->assertSame('confirmed', $group->fresh()->status);
        $this->assertTrue($group->fresh()->host_covers_shortfall);
        $this->assertSame(200000, $group->fresh()->paid_amount);
        $this->assertSame('confirmed', $reservation->fresh()->status);

        $share2 = $group->shares->firstWhere('share_number', 2);
        $this->assertSame('paid', $share2->fresh()->status);
        $this->assertSame($fixture['host']->id, $share2->fresh()->payer_id);
    }

    public function test_expired_split_group_records_one_finance_exception_per_paid_share_without_refunds(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createHeldReservation($fixture, 200000);
        $group = app(SplitPaymentService::class)->create($reservation, $fixture['host'], [
            ['amount' => 70000],
            ['amount' => 70000],
            ['amount' => 60000],
        ]);

        // Shares 1 and 2 are paid; share 3 is not.
        $paid = $group->shares->whereIn('share_number', [1, 2])->values();
        foreach ($paid as $share) {
            $attempt = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$share->id)->firstOrFail();
            $this->sendOwnerPayment($fixture, $attempt->reference, 70000, 'expired-share-'.$share->share_number);
            $this->assertSame('paid', $share->fresh()->status);
        }

        // Fast-forward past expiry
        $group->update(['expires_at' => now()->subMinute()]);
        $reservation->update(['hold_expires_at' => now()->subMinute()]);

        $service = app(SplitPaymentService::class);
        $this->assertSame(1, $service->expireDue());
        $this->assertSame(0, $service->expireDue());

        $freshGroup = $group->fresh(['shares', 'reservation']);
        $this->assertSame('expired', $freshGroup->status);
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame('expired', $freshGroup->shares->firstWhere('share_number', 3)->status);

        // Received money stays traceable per payer for the owner to settle outside the platform; nothing is refunded.
        $exceptions = FinanceException::where('type', 'split_expired_paid_share')->orderBy('id')->get();
        $this->assertSame($paid->map(fn ($share) => 'split-share:'.$share->id)->all(), $exceptions->pluck('reference')->all());
        $this->assertSame([70000, 70000], $exceptions->pluck('amount')->all());
        $this->assertSame([(int) $fixture['organization']->id], $exceptions->pluck('organization_id')->unique()->values()->all());
        $this->assertSame(0, OwnerRefundObligation::count());
        $this->assertSame('paid', $freshGroup->shares->firstWhere('share_number', 1)->status);

        // The attempt for unpaid share is expired
        $attempt3 = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$freshGroup->shares->firstWhere('share_number', 3)->id)->firstOrFail();
        $this->assertSame('expired', $attempt3->fresh()->status);
    }

    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Split Org',
            'slug' => 'split-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $owner = User::factory()->create();
        $host = User::factory()->create();
        $payer = User::factory()->create();

        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $host->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $payer->id, 'role' => 'customer', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Split Venue',
            'slug' => 'split-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Court 1', 'is_active' => true]);
        $this->priceCourt($court, 200000);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 30, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false],
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
        $connection->setAccountDetails('Split Owner', '0987654321');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();

        config([
            'features.flags.direct_owner_payments' => true,
            'features.flags.marketplace_split_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'host', 'payer', 'venue', 'court', 'connection', 'secret');
    }

    private function createHeldReservation(array $fixture, int $amount): Reservation
    {
        $reservation = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['host']->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RSV-SPLIT-'.Str::upper(Str::random(8)),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => $amount,
            'payment_model' => 'direct_owner_v1',
            'hold_expires_at' => now()->addMinutes(15),
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
            'unit_price' => $amount,
            'status' => 'held',
            'snapshot' => [],
        ]);

        ReservationEntitlement::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'court_id' => $fixture['court']->id,
            'user_id' => $fixture['host']->id,
            'entitlement_type' => 'court_reservation',
            'status' => 'held',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
            'amount' => $amount,
        ]);

        return $reservation;
    }

    private function sendOwnerPayment(array $fixture, string $reference, int $amount, string $eventId): void
    {
        $connection = $fixture['connection'];
        $payload = [
            'id' => $eventId,
            'transactionDate' => $this->eventDates[$eventId] ??= now()->addSeconds(2)->toIso8601String(),
            'accountNumber' => $connection->accountNumber(),
            'code' => $reference,
            'content' => $reference,
            'transferType' => 'in',
            'transferAmount' => $amount,
            'referenceCode' => 'BANK-'.$eventId,
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Apikey '.$fixture['secret'],
        ], $raw)->assertOk();

        $receipt = OwnerPaymentReceipt::where('provider_event_id', $eventId)->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }
    }
}
