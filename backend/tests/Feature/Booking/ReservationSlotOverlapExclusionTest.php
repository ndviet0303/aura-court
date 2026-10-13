<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Reservation\SlotSelectionPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Store-level exclusivity of active reservation slots (reservation_slots_no_overlap).
 */
class ReservationSlotOverlapExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_an_overlapping_slot_written_without_the_application_check(): void
    {
        $fixture = $this->tenant(120);
        $original = $this->bookAndConfirm($fixture, $this->at(3, 10), '12:00', 200000, 'direct');
        $slot = $original->slots()->firstOrFail();

        // 11:00-12:00 for another reservation inside the confirmed 10:00-12:00.
        $this->assertOverlapRejected($fixture, $slot->starts_at->copy()->addHour(), $slot->ends_at);

        $this->assertSame(1, ReservationSlot::where('court_id', $fixture['court']->id)->count());
    }

    public function test_store_level_overlap_through_checkout_is_a_conflict_without_side_effects(): void
    {
        $fixture = $this->tenant(120);
        // Quote 11:00-13:00 while the court is still free, then let 10:00-12:00 be taken.
        // Simulate writers that skip the application availability check.
        $this->mock(SlotSelectionPolicy::class)->shouldReceive('assertAllowed')->twice();

        $lateQuote = $this->quote($fixture, $fixture['other'], $this->at(3, 11), '13:00', 300000, 'late');
        $this->checkout($fixture, $fixture['payer'], $this->quote($fixture, $fixture['payer'], $this->at(3, 10), '12:00', 200000, 'early')['id'], 'early')
            ->assertCreated();
        $attemptsBefore = OwnerPaymentAttempt::count();

        $this->checkout($fixture, $fixture['other'], $lateQuote['id'], 'late')
            ->assertStatus(409)
            ->assertJsonPath('message', 'The selected court-time is no longer available.');

        $this->assertFalse(Reservation::where('price_quote_id', $lateQuote['id'])->exists());
        $this->assertFalse(ReservationEntitlement::where('user_id', $fixture['other']->id)->exists());
        $this->assertSame($attemptsBefore, OwnerPaymentAttempt::count());
    }

    public function test_adjacent_intervals_are_both_accepted(): void
    {
        $fixture = $this->tenant(60);

        $first = $this->quote($fixture, $fixture['payer'], $this->at(3, 10), '11:00', 100000, 'first');
        $this->checkout($fixture, $fixture['payer'], $first['id'], 'first')->assertCreated();
        $second = $this->quote($fixture, $fixture['other'], $this->at(3, 11), '12:00', 100000, 'second');
        $this->checkout($fixture, $fixture['other'], $second['id'], 'second')->assertCreated();

        $this->assertSame(2, ReservationSlot::where('court_id', $fixture['court']->id)->whereIn('status', ['held', 'confirmed'])->count());
    }

    public function test_replacement_held_for_an_extra_payment_and_its_original_both_block_competitors(): void
    {
        $fixture = $this->tenant(120);
        $original = $this->bookAndConfirm($fixture, $this->at(3, 10), '12:00', 200000, 'orig');
        $this->priceSlot($fixture, $this->at(4, 14), '16:00', 300000);

        $data = $this->rescheduleRequest($fixture, $original->code, $this->at(4, 14), 'move')->assertCreated()->json('data');
        $replacement = Reservation::where('code', $data['replacement']['code'])->firstOrFail();
        $this->assertSame('held', $replacement->status);
        $this->assertSame('confirmed', $original->fresh()->status);

        // Both the protected original and the held replacement are exclusive at the store level.
        foreach ([$original, $replacement] as $reservation) {
            $slot = $reservation->slots()->firstOrFail();
            $this->assertOverlapRejected($fixture, $slot->starts_at->copy()->addHour(), $slot->ends_at);
        }

        // The verified difference still commits the swap.
        $attempt = OwnerPaymentAttempt::where('purpose', 'booking_change')->where('purpose_id', $replacement->code)->firstOrFail();
        $this->sendOwnerPayment($fixture, $attempt->reference, 100000, 'diff-move');

        $this->assertSame('cancelled', $original->fresh()->status);
        $this->assertSame('confirmed', $replacement->fresh()->status);
    }

    public function test_released_slots_no_longer_block_the_interval(): void
    {
        $fixture = $this->tenant(120);
        $expired = $this->quote($fixture, $fixture['payer'], $this->at(3, 10), '12:00', 200000, 'expiring');
        $this->checkout($fixture, $fixture['payer'], $expired['id'], 'expiring')->assertCreated();
        $this->travel(31)->minutes();
        app(ExpirePendingBookings::class)->execute();
        $this->assertSame('expired', ReservationSlot::where('court_id', $fixture['court']->id)->value('status'));

        $next = $this->quote($fixture, $fixture['other'], $this->at(3, 11), '13:00', 300000, 'next');
        $this->checkout($fixture, $fixture['other'], $next['id'], 'next')->assertCreated();

        // A cancelled slot does not block a direct write either.
        $slot = Reservation::where('price_quote_id', $next['id'])->firstOrFail()->slots()->firstOrFail();
        $slot->update(['status' => 'cancelled']);
        $this->insertCompetingSlot($fixture, $slot->starts_at, $slot->ends_at);
        $this->assertSame(1, ReservationSlot::where('court_id', $fixture['court']->id)->where('status', 'held')->count());
    }

    private function assertOverlapRejected(array $fixture, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        try {
            // Savepoint, so the failed insert does not abort the test transaction.
            DB::transaction(fn () => $this->insertCompetingSlot($fixture, $startsAt, $endsAt));
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->getCode());
            $this->assertStringContainsString('reservation_slots_no_overlap', $exception->getMessage());

            return;
        }

        $this->fail('Expected reservation_slots_no_overlap to reject the slot.');
    }

    private function insertCompetingSlot(array $fixture, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        $reservationId = DB::table('reservations')->insertGetId([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['other']->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RSV-'.Str::upper(Str::random(10)),
            'status' => 'held',
            'total_amount' => 100000,
        ]);
        DB::table('reservation_slots')->insert([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservationId,
            'court_id' => $fixture['court']->id,
            'starts_at' => $startsAt->copy()->utc()->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->copy()->utc()->format('Y-m-d H:i:s'),
            'unit_price' => 100000,
            'status' => 'held',
        ]);
    }

    private function at(int $daysAhead, int $hour): CarbonInterface
    {
        return now()->addDays($daysAhead)->setTime($hour, 0, 0);
    }

    private function headers(array $fixture, string $key): array
    {
        return ['X-Organization-ID' => (string) $fixture['organization']->id, 'Idempotency-Key' => $key];
    }

    private function quote(array $fixture, User $user, CarbonInterface $start, string $endTime, int $price, string $tag): array
    {
        $this->priceSlot($fixture, $start, $endTime, $price);

        return $this->actingAs($user)->withHeaders($this->headers($fixture, 'q-'.$tag))
            ->postJson('/api/price-quotes', ['court_id' => $fixture['court']->id, 'slots' => [$start->toIso8601String()]])
            ->assertCreated()->json('quote');
    }

    private function checkout(array $fixture, User $user, int $quoteId, string $tag)
    {
        return $this->actingAs($user)->withHeaders($this->headers($fixture, 'c-'.$tag))
            ->postJson('/api/reservations/checkout', ['quote_id' => $quoteId]);
    }

    private function bookAndConfirm(array $fixture, CarbonInterface $start, string $endTime, int $price, string $tag): Reservation
    {
        $quote = $this->quote($fixture, $fixture['payer'], $start, $endTime, $price, $tag);
        $code = $this->checkout($fixture, $fixture['payer'], $quote['id'], $tag)->assertCreated()->json('reservation.code');
        $attempt = OwnerPaymentAttempt::where('purpose', 'booking')->where('purpose_id', $code)->firstOrFail();
        $this->sendOwnerPayment($fixture, $attempt->reference, $price, 'confirm-'.$tag);

        $reservation = Reservation::where('code', $code)->firstOrFail();
        $this->assertSame('confirmed', $reservation->status);

        return $reservation;
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

    private function tenant(int $slotDuration): array
    {
        $organization = Organization::create([
            'name' => 'Overlap Club',
            'slug' => 'overlap-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $payer = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        foreach ([$payer, $other] as $customer) {
            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $customer->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
        }
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Overlap Venue',
            'slug' => 'overlap-venue-'.Str::lower(Str::random(8)),
            'address' => '303 Test Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $zone = SportZone::create([
            'venue_id' => $venue->id,
            'name' => 'Overlap Zone',
            'sport_type' => 'badminton',
            'slot_duration' => $slotDuration,
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'sport_zone_id' => $zone->id, 'name' => 'Overlap Court', 'is_active' => true]);
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
        $connection->setAccountDetails('Overlap Owner', '0123456789');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'payer', 'other', 'venue', 'court', 'connection', 'secret');
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
}
