<?php

namespace Tests\Feature\Module04;

use App\Models\OwnerPaymentAttempt;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksCourts;
use Tests\TestCase;

/** FR-71, FR-72, BR-74, BR-77, BR-80: submitting a booking request and holding the court-time. */
class BookingRequestTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 09:00:00');
    }

    public function test_fr_72_payable_request_is_held_with_a_payment_request(): void
    {
        $court = $this->payableCourt();

        $reservation = $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)
            ->assertCreated()->json('reservation');

        $this->assertSame('held', $reservation['status']);
        $this->assertSame('held', ReservationSlot::where('reservation_id', $reservation['id'])->value('status'));
        $this->assertSame('held', ReservationEntitlement::where('reservation_id', $reservation['id'])->value('status'));
        $this->assertSame(1, OwnerPaymentAttempt::where('purpose_id', $reservation['code'])->where('status', 'pending')->count());
    }

    public function test_br_74_missing_phone_is_refused_before_anything_is_held(): void
    {
        $court = $this->payableCourt();

        $this->checkout($this->customer(['phone' => null]), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)
            ->assertStatus(422)->assertJsonPath('code', 'PHONE_REQUIRED');
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, PriceQuote::count());
        $this->assertSame(0, OwnerPaymentAttempt::count());
    }

    public function test_br_74_booker_contact_is_recorded_and_survives_profile_edits(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer(['name' => 'Nguyễn Văn A', 'phone' => '0901234567']);
        $code = $this->checkout($user, $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertCreated()->json('reservation.code');

        $user->forceFill(['name' => 'Tên Mới', 'phone' => '0999999999'])->save();

        $booker = ['name' => 'Nguyễn Văn A', 'phone' => '0901234567', 'email' => $user->email];
        $this->actingAs($user)->getJson("/api/reservations/{$code}")->assertOk()->assertJsonPath('reservation.metadata.booker', $booker);
        $owner = $this->ownerOf($court->venue->organization);
        $row = collect($this->actingAs($owner)->getJson('/api/admin/reservations', ['X-Organization-ID' => (string) $court->venue->organization_id])->assertOk()->json('data'))
            ->firstWhere('code', $code);
        $this->assertSame('Nguyễn Văn A', $row['booker']['name'] ?? $row['metadata']['booker']['name'] ?? null);
        $this->assertSame('0901234567', $row['booker']['phone'] ?? $row['metadata']['booker']['phone'] ?? null);
    }

    public function test_br_80_request_without_the_agreed_amount_is_refused(): void
    {
        $court = $this->payableCourt();

        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', null)->assertStatus(422)->assertJsonValidationErrors('expected_total');
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_80_price_rose_before_submission_is_refused_with_the_new_amounts(): void
    {
        $court = $this->payableCourt(220000);

        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 200000)
            ->assertStatus(409)->assertJsonPath('code', 'PRICE_CHANGED')->assertJsonPath('total', 220000)->assertJsonPath('rental', 220000);
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, ReservationSlot::count());
        $this->assertSame(0, OwnerPaymentAttempt::count());
    }

    public function test_br_80_price_fell_before_submission_books_the_new_amount(): void
    {
        $court = $this->payableCourt(180000);

        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 200000)
            ->assertCreated()->assertJsonPath('reservation.total_amount', 180000);
    }

    public function test_br_77_overlapping_request_conflicts_and_adjacent_request_does_not(): void
    {
        $court = $this->payableCourt();
        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 17:00', 200000)->assertCreated();

        $this->checkout($this->customer(), $court, '2026-11-03 16:30', '2026-11-03 17:30', 100000)->assertConflict();
        $this->checkout($this->customer(), $court, '2026-11-03 14:00', '2026-11-03 15:30', 150000)->assertConflict();
        $this->checkout($this->customer(), $court, '2026-11-03 17:00', '2026-11-03 18:00', 100000)->assertCreated();
        $this->checkout($this->customer(), $court, '2026-11-03 14:00', '2026-11-03 15:00', 100000)->assertCreated();
    }

    public function test_br_77_overdue_hold_does_not_block_and_is_expired_first(): void
    {
        $court = $this->payableCourt();
        $first = $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertCreated()->json('reservation.code');

        Carbon::setTestNow('2026-11-02 09:11:00');
        $free = collect($this->getJson("/api/availability?court_id={$court->id}&date=2026-11-03")->json())->firstWhere('startsAt', '2026-11-03T15:00:00+07:00');
        $this->assertTrue($free['available']);
        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertCreated();

        $old = Reservation::where('code', $first)->firstOrFail();
        $this->assertSame('expired', $old->status);
        $this->assertSame('payment_deadline', $old->metadata['release_reason']);
        $this->assertSame('expired', OwnerPaymentAttempt::where('purpose_id', $first)->value('status'));
    }

    public function test_br_77_live_hold_blocks(): void
    {
        $court = $this->payableCourt();
        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertCreated();

        Carbon::setTestNow('2026-11-02 09:09:00');
        $this->checkout($this->customer(), $court, '2026-11-03 15:30', '2026-11-03 16:30', 100000)->assertConflict();
    }

    public function test_br_77_store_rejects_an_overlapping_slot_written_around_the_check(): void
    {
        $court = $this->payableCourt();
        $held = $this->checkout($this->customer(), $court, '2026-11-03 10:00', '2026-11-03 12:00', 200000)->assertCreated()->json('reservation');
        $other = Reservation::create([
            'organization_id' => $court->venue->organization_id, 'user_id' => $this->customer()->id, 'court_id' => $court->id,
            'code' => 'RSVBYPASS0001', 'status' => 'held', 'currency' => 'VND', 'total_amount' => 100000,
            'hold_expires_at' => now()->addMinutes(10), 'payment_model' => 'direct_owner_v1',
        ]);

        $this->expectException(QueryException::class);
        ReservationSlot::create([
            'organization_id' => $other->organization_id, 'reservation_id' => $other->id, 'court_id' => $court->id,
            'starts_at' => '2026-11-03 11:00', 'ends_at' => '2026-11-03 12:00', 'unit_price' => 100000, 'status' => 'held',
        ]);
        $this->assertNotNull($held);
    }
}
