<?php

namespace Tests\Feature\Module04;

use App\Models\Court;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksCourts;
use Tests\TestCase;

/** FR-79, FR-80, FR-81, BR-88: the customer's bookings, one booking's detail, and the owner's payment exceptions. */
class BookingLookupTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
    }

    /** @return array<string, string> */
    private function tenant(Court $court): array
    {
        return ['X-Organization-ID' => (string) $court->venue->organization_id];
    }

    public function test_fr_79_customer_lists_only_their_bookings_page_by_page(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();
        $code = $this->checkout($user, $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');
        foreach (range(1, 21) as $i) {
            Reservation::create([
                'organization_id' => $court->venue->organization_id, 'user_id' => $user->id, 'court_id' => $court->id,
                'code' => sprintf('RSVOLD%05d', $i), 'status' => 'expired', 'currency' => 'VND', 'total_amount' => 100000,
                'payment_model' => 'direct_owner_v1', 'created_at' => now()->subDays($i),
            ]);
        }
        $this->checkout($this->customer(), $court, '2026-11-03 20:00', '2026-11-03 21:00', 100000)->assertCreated();

        $first = $this->actingAs($user)->getJson('/api/me/reservations')->assertOk()->assertJsonPath('total', 22);
        $this->assertSame($code, $first->json('data.0.code'));
        $this->assertSame('held', $first->json('data.0.status'));
        $this->assertNotEmpty($first->json('data.0.slots'));
        $this->assertCount(2, $this->actingAs($user)->getJson('/api/me/reservations?page=2')->assertOk()->json('data'));
    }

    public function test_fr_79_detail_shows_time_court_amount_status_payment_and_deadline(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();
        $code = $this->checkout($user, $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');

        $detail = $this->actingAs($user)->getJson("/api/reservations/{$code}")->assertOk()->json('reservation');

        $this->assertSame('held', $detail['status']);
        $this->assertSame(100000, (int) $detail['total_amount']);
        $this->assertSame($court->id, $detail['court']['id']);
        $this->assertSame($court->venue_id, $detail['court']['venue']['id']);
        $this->assertNotNull($detail['hold_expires_at']);
        $this->assertNotEmpty($detail['slots']);
        $this->assertArrayHasKey('payment', $detail);
        $this->assertArrayHasKey('attendance', $detail);
        $this->assertArrayHasKey('latest_affected_case', $detail);
        $this->assertSame($user->phone, $detail['metadata']['booker']['phone']);
    }

    public function test_fr_80_booking_pass_code_leads_venue_staff_to_that_booking(): void
    {
        $court = $this->payableCourt();
        $code = $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');
        $this->transfer($court, $this->paymentCode($code), 100000)->assertOk();

        // The pass QR encodes AURA_BOOKING_<code> (ReservationQrModal), unlike the payment QR, which carries the payment code.
        $this->actingAs($this->staffOf($court->venue))->getJson("/api/attendance/AURA_BOOKING_{$code}", $this->tenant($court))
            ->assertOk()->assertJsonPath('reservation.code', $code)->assertJsonPath('reservation.status', 'confirmed');
        $this->assertNotSame($code, $this->paymentCode($code));
    }

    public function test_br_88_a_booking_is_shown_only_to_its_booker_and_the_venue_staff(): void
    {
        $court = $this->payableCourt();
        $code = $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');

        $this->assertContains($this->actingAs($this->customer())->getJson("/api/reservations/{$code}")->status(), [403, 404]);
        $this->assertContains($this->actingAs($this->customer())->getJson("/api/reservations/{$code}/payment")->status(), [403, 404]);

        $this->actingAs($this->staffOf($court->venue))->getJson("/api/attendance/{$code}", $this->tenant($court))
            ->assertOk()->assertJsonPath('reservation.code', $code);
        $otherVenue = $this->openVenue(['organization_id' => $court->venue->organization_id]);
        $this->actingAs($this->staffOf($otherVenue))->getJson("/api/attendance/{$code}", $this->tenant($court))->assertForbidden();
    }

    public function test_fr_81_owner_sees_only_their_payment_exceptions_with_the_booking_when_known(): void
    {
        config(['features.flags.marketplace_payouts' => false]);
        $court = $this->payableCourt();
        $code = $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');
        $other = $this->payableCourt();
        $otherCode = $this->checkout($this->customer(), $other, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');

        $this->transfer($court, $this->paymentCode($code), 90000)->assertOk();
        $this->transfer($court, $this->paymentCode($code), 120000)->assertOk();
        $this->transfer($court, 'ACNOSUCHCODE00000001', 100000)->assertOk();
        $this->transfer($other, $this->paymentCode($otherCode), 1000)->assertOk();
        Carbon::setTestNow('2026-11-03 09:20:00');
        $this->transfer($court, $this->paymentCode($code), 100000)->assertOk();

        $rows = collect($this->actingAs($this->ownerOf($court->venue->organization))->getJson('/api/owner-payment-exceptions', $this->tenant($court))->assertOk()->json('data'));

        $this->assertEqualsCanonicalizing(['partial_amount', 'excess_amount', 'unmatched_reference', 'transfer_after_expiry'], $rows->pluck('code')->all());
        $this->assertSame([$code], $rows->where('code', '!=', 'unmatched_reference')->pluck('booking_code')->unique()->values()->all());
        $this->assertNull($rows->firstWhere('code', 'unmatched_reference')['booking_code']);
        $this->assertSame(1, $this->actingAs($this->ownerOf($other->venue->organization))->getJson('/api/owner-payment-exceptions', $this->tenant($other))->assertOk()->json('meta.total'));
    }

    public function test_br_88_staff_customers_and_other_owners_cannot_read_payment_exceptions(): void
    {
        $court = $this->payableCourt();
        $other = $this->payableCourt();

        $this->actingAs($this->staffOf($court->venue))->getJson('/api/owner-payment-exceptions', $this->tenant($court))->assertForbidden();
        $this->actingAs($this->customer())->getJson('/api/owner-payment-exceptions', $this->tenant($court))->assertForbidden();
        $this->actingAs($this->ownerOf($other->venue->organization))->getJson('/api/owner-payment-exceptions', $this->tenant($court))->assertForbidden();
    }
}
