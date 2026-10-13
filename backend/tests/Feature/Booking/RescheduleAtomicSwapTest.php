<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\OwnerPaymentAttemptService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class RescheduleAtomicSwapTest extends TestCase
{
    use RefreshDatabase;

    public function test_more_expensive_change_holds_replacement_until_verified_difference_then_swaps(): void
    {
        $fixture = $this->tenant();
        $original = $this->bookAndConfirm($fixture, now()->addDays(3)->setTime(10, 0, 0), '11:00', 200000, 'orig-a');
        $this->assertSame('confirmed', $original->status);

        $newStart = now()->addDays(4)->setTime(14, 0, 0);
        $this->priceSlot($fixture, $newStart, '15:00', 300000);

        $preview = $this->reschedulePreview($fixture, $original->code, $newStart)->assertOk()->json('data');
        $this->assertSame(200000, $preview['original_paid_amount']);
        $this->assertSame(300000, $preview['new_total_amount']);
        $this->assertSame(100000, $preview['extra_payment_amount']);
        $this->assertSame(0, $preview['refund_amount']);

        $data = $this->rescheduleRequest($fixture, $original->code, $newStart, 'resched-a')->assertCreated()->json('data');
        $replacementCode = $data['replacement']['code'];
        $replacement = Reservation::where('code', $replacementCode)->firstOrFail();

        // Replacement is held awaiting the difference; original stays protected.
        $this->assertSame('held', $replacement->status);
        $this->assertSame(100000, (int) $data['payment_attempt']['amount']);
        $this->assertSame('confirmed', $original->fresh()->status);
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $original->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $replacement->id, 'status' => 'held']);

        $changeAttempt = OwnerPaymentAttempt::where('purpose', 'booking_change')->where('purpose_id', $replacementCode)->firstOrFail();
        $this->assertSame(100000, (int) $changeAttempt->amount);

        // Verified difference commits the atomic swap.
        $this->sendOwnerPayment($fixture, $changeAttempt->reference, 100000, 'diff-a');

        $this->assertSame('cancelled', $original->fresh()->status);
        $this->assertSame($replacementCode, data_get($original->fresh()->metadata, 'rescheduled_to'));
        $this->assertSame('confirmed', $replacement->fresh()->status);
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $original->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $replacement->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('reservation_entitlements', ['reservation_id' => $replacement->id, 'status' => 'active']);
        $this->assertDatabaseHas('owner_payment_receipts', ['provider_event_id' => 'diff-a', 'status' => 'allocated']);
    }

    public function test_extra_payment_timeout_releases_replacement_and_preserves_original(): void
    {
        $fixture = $this->tenant();
        $original = $this->bookAndConfirm($fixture, now()->addDays(3)->setTime(10, 0, 0), '11:00', 200000, 'orig-b');

        $newStart = now()->addDays(4)->setTime(14, 0, 0);
        $this->priceSlot($fixture, $newStart, '15:00', 300000);
        $data = $this->rescheduleRequest($fixture, $original->code, $newStart, 'resched-b')->assertCreated()->json('data');
        $replacement = Reservation::where('code', $data['replacement']['code'])->firstOrFail();
        $changeAttempt = OwnerPaymentAttempt::where('purpose', 'booking_change')->where('purpose_id', $replacement->code)->firstOrFail();

        $this->travel(31)->minutes();
        app(ExpirePendingBookings::class)->execute();

        // The unpaid replacement hold releases; the original is untouched.
        $this->assertSame('expired', $replacement->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, $changeAttempt->fresh()->status);
        $this->assertSame('confirmed', $original->fresh()->status);
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $original->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $replacement->id, 'status' => 'expired']);
    }

    public function test_late_difference_receipt_becomes_exception_and_preserves_original(): void
    {
        $fixture = $this->tenant();
        $original = $this->bookAndConfirm($fixture, now()->addDays(3)->setTime(10, 0, 0), '11:00', 200000, 'orig-c');

        $newStart = now()->addDays(4)->setTime(14, 0, 0);
        $this->priceSlot($fixture, $newStart, '15:00', 300000);
        $data = $this->rescheduleRequest($fixture, $original->code, $newStart, 'resched-c')->assertCreated()->json('data');
        $replacement = Reservation::where('code', $data['replacement']['code'])->firstOrFail();
        $changeAttempt = OwnerPaymentAttempt::where('purpose', 'booking_change')->where('purpose_id', $replacement->code)->firstOrFail();

        // A transfer stamped after the attempt window is a reconciliation exception, not a swap.
        $this->sendOwnerPayment($fixture, $changeAttempt->reference, 100000, 'late-c', $changeAttempt->expires_at->copy()->addMinutes(5));

        $this->assertDatabaseHas('owner_payment_receipts', [
            'provider_event_id' => 'late-c',
            'status' => 'late',
            'exception_code' => 'transfer_after_expiry',
        ]);
        $this->assertTrue(FinanceException::where('type', 'owner_payment_transfer_after_expiry')->exists());
        $this->assertSame('confirmed', $original->fresh()->status);
        $this->assertSame('held', $replacement->fresh()->status);
        $this->assertDatabaseCount('owner_payment_allocations', 1); // only the original booking payment
    }

    public function test_cheaper_change_swaps_immediately_and_records_only_the_refund_difference(): void
    {
        $fixture = $this->tenant();
        $original = $this->bookAndConfirm($fixture, now()->addDays(3)->setTime(10, 0, 0), '11:00', 300000, 'orig-d');

        $newStart = now()->addDays(4)->setTime(14, 0, 0);
        $this->priceSlot($fixture, $newStart, '15:00', 200000);

        $preview = $this->reschedulePreview($fixture, $original->code, $newStart)->assertOk()->json('data');
        $this->assertSame(0, $preview['extra_payment_amount']);
        $this->assertSame(100000, $preview['refund_amount']);

        $data = $this->rescheduleRequest($fixture, $original->code, $newStart, 'resched-d', [
            'bank_code' => 'MB',
            'account_name' => 'Payer Refund',
            'account_number' => '0987654321',
        ])->assertCreated()->json('data');
        $replacement = Reservation::where('code', $data['replacement']['code'])->firstOrFail();

        // Swap is immediate; no extra payment attempt is created.
        $this->assertSame('cancelled', $original->fresh()->status);
        $this->assertSame('confirmed', $replacement->fresh()->status);
        $this->assertDatabaseCount('owner_payment_attempts', 1); // only the original booking attempt
        $this->assertDatabaseHas('owner_refund_obligations', [
            'reservation_id' => $original->id,
            'reason' => 'reschedule_difference:'.$replacement->code,
            'amount' => 100000,
            'status' => OwnerRefundObligation::STATUS_REQUESTED,
        ]);
    }

    public function test_reschedule_rejects_replacement_overlapping_another_reservation(): void
    {
        $fixture = $this->tenant();
        $original = $this->bookAndConfirm($fixture, now()->addDays(3)->setTime(10, 0, 0), '11:00', 200000, 'orig-ov');

        // A different confirmed reservation occupies 14:30–15:30 on the same court.
        $occupantStart = now()->addDays(4)->setTime(14, 30, 0);
        $occupant = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RES-OCC-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 150000,
            'payment_model' => 'direct_owner_v1',
        ]);
        ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $occupant->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => $occupantStart->copy()->utc(),
            'ends_at' => $occupantStart->copy()->addHour()->utc(),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);

        // Reschedule the original onto 14:00–15:00: a nonidentical start that overlaps the occupant.
        $newStart = now()->addDays(4)->setTime(14, 0, 0);
        $this->priceSlot($fixture, $newStart, '15:00', 200000);
        $this->rescheduleRequest($fixture, $original->code, $newStart, 'resched-ov')->assertStatus(409);

        $this->assertSame('confirmed', $original->fresh()->status);
    }

    public function test_reschedule_rejects_a_past_replacement_slot(): void
    {
        $fixture = $this->tenant();
        $original = $this->bookAndConfirm($fixture, now()->addDays(3)->setTime(10, 0, 0), '11:00', 200000, 'orig-past');

        $pastStart = now()->subDay()->setTime(14, 0, 0);
        $this->rescheduleRequest($fixture, $original->code, $pastStart, 'resched-past')->assertStatus(422);

        $this->assertSame('confirmed', $original->fresh()->status);
    }

    public function test_reschedule_rejects_an_over_horizon_replacement_slot(): void
    {
        $fixture = $this->tenant();
        $original = $this->bookAndConfirm($fixture, now()->addDays(3)->setTime(10, 0, 0), '11:00', 200000, 'orig-hz');

        // The fixture policy allows max_days_ahead = 60; a +90d replacement violates the horizon.
        $farStart = now()->addDays(90)->setTime(14, 0, 0);
        $this->priceSlot($fixture, $farStart, '15:00', 200000);
        $this->rescheduleRequest($fixture, $original->code, $farStart, 'resched-hz')->assertStatus(422);

        $this->assertSame('confirmed', $original->fresh()->status);
    }

    private function bookAndConfirm(array $fixture, CarbonInterface $start, string $endTime, int $price, string $tag): Reservation
    {
        $this->priceSlot($fixture, $start, $endTime, $price);
        $headers = ['X-Organization-ID' => (string) $fixture['organization']->id];
        $quote = $this->actingAs($fixture['payer'])->withHeaders($headers + ['Idempotency-Key' => 'q-'.$tag])
            ->postJson('/api/price-quotes', ['court_id' => $fixture['court']->id, 'slots' => [$start->toIso8601String()]])
            ->assertCreated()->json('quote');
        $reservation = $this->actingAs($fixture['payer'])->withHeaders($headers + ['Idempotency-Key' => 'c-'.$tag])
            ->postJson('/api/reservations/checkout', ['quote_id' => $quote['id']])
            ->assertCreated()->json('reservation');

        $record = Reservation::where('code', $reservation['code'])->firstOrFail();
        $this->assertSame($price, (int) $record->total_amount);
        $attempt = OwnerPaymentAttempt::where('purpose', 'booking')->where('purpose_id', $record->code)->firstOrFail();
        $this->sendOwnerPayment($fixture, $attempt->reference, $price, 'confirm-'.$tag);

        return $record->fresh();
    }

    private function priceSlot(array $fixture, CarbonInterface $start, string $endTime, int $price): void
    {
        CourtPriceSlot::firstOrCreate([
            'court_id' => $fixture['court']->id,
            'weekday' => $start->dayOfWeekIso,
            'starts_at' => $start->format('H:i'),
        ], [
            'ends_at' => $endTime,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    private function reschedulePreview(array $fixture, string $code, CarbonInterface $newStart)
    {
        return $this->actingAs($fixture['payer'])
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
            ->postJson("/api/reservations/{$code}/reschedule-preview", [
                'court_id' => $fixture['court']->id,
                'slots' => [$newStart->toIso8601String()],
            ]);
    }

    private function rescheduleRequest(array $fixture, string $code, CarbonInterface $newStart, string $key, array $recipient = [])
    {
        $payload = [
            'court_id' => $fixture['court']->id,
            'slots' => [$newStart->toIso8601String()],
            'idempotency_key' => $key,
        ];
        if ($recipient !== []) {
            $payload['recipient'] = $recipient;
        }

        return $this->actingAs($fixture['payer'])
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
            ->postJson("/api/reservations/{$code}/reschedule-requests", $payload);
    }

    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Reschedule Club',
            'slug' => 'reschedule-'.Str::lower(Str::random(8)),
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
            'name' => 'Reschedule Venue',
            'slug' => 'reschedule-venue-'.Str::lower(Str::random(8)),
            'address' => '202 Test Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Reschedule Court', 'is_active' => true]);
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
        $connection->setAccountDetails('Reschedule Owner', '0123456789');
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

    private function sendOwnerPayment(array $fixture, string $reference, int $amount, string $eventId, ?CarbonInterface $eventAt = null): void
    {
        $connection = $fixture['connection'];
        $payload = [
            'id' => $eventId,
            'transactionDate' => ($eventAt ?? now()->addSeconds(2))->toIso8601String(),
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
}
