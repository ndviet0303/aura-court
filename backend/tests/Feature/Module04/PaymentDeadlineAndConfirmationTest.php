<?php

namespace Tests\Feature\Module04;

use App\Models\FinanceException;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\VoucherUse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksCourts;
use Tests\TestCase;

/** FR-73, FR-74, FR-75, BR-75, BR-79, BR-81, BR-82, BR-83, BR-86: the payment window and what confirms a booking. */
class PaymentDeadlineAndConfirmationTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
    }

    private function hold(string $start = '2026-11-03 18:00', string $end = '2026-11-03 19:00', int $total = 100000, array $extra = []): array
    {
        $court = $this->payableCourt();
        $code = $this->checkout($this->customer(), $court, $start, $end, $total, $extra)->assertCreated()->json('reservation.code');

        return [$court, Reservation::where('code', $code)->firstOrFail()];
    }

    public function test_br_79_ten_minute_cap_ignores_the_venue_hold_policy(): void
    {
        [, $reservation] = $this->hold();

        $this->assertSame('2026-11-03 09:10:00', $reservation->hold_expires_at->format('Y-m-d H:i:s'));
        $attempt = OwnerPaymentAttempt::where('purpose_id', $reservation->code)->firstOrFail();
        $this->assertTrue($attempt->expires_at->equalTo($reservation->hold_expires_at));
    }

    public function test_br_79_start_time_caps_the_deadline_and_a_later_transfer_is_an_exception(): void
    {
        Carbon::setTestNow('2026-11-03 14:58:00');
        [$court, $reservation] = $this->hold('2026-11-03 15:00', '2026-11-03 16:00');
        $this->assertSame('2026-11-03 15:00:00', $reservation->hold_expires_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-11-03 15:01:00');
        $this->transfer($court, $this->paymentCode($reservation->code), 100000)->assertOk();

        $this->assertNotSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('transfer_after_expiry', OwnerPaymentReceipt::value('exception_code'));
        $this->assertSame(1, FinanceException::count());
    }

    public function test_br_79_voucher_end_caps_the_deadline(): void
    {
        Carbon::setTestNow('2026-11-03 14:50:00');
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, Carbon::parse('2026-11-03 14:59:00'));

        $code = $this->checkout($this->customer(), $court, '2026-11-03 16:00', '2026-11-03 17:00', 80000, ['voucher_program_id' => $program->id])
            ->assertCreated()->assertJsonPath('reservation.total_amount', 80000)->json('reservation.code');

        $this->assertSame('2026-11-03 14:59:00', Reservation::where('code', $code)->value('hold_expires_at')->format('Y-m-d H:i:s'));
        $this->assertSame(VoucherUse::STATUS_HELD, VoucherUse::value('status'));
    }

    public function test_br_75_no_payment_window_left_at_the_start_time(): void
    {
        Carbon::setTestNow('2026-11-03 15:00:00');
        $court = $this->payableCourt();

        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)
            ->assertStatus(422)->assertJsonPath('code', 'PAYMENT_WINDOW_CLOSED');
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, OwnerPaymentAttempt::count());
    }

    public function test_fr_73_payment_information_and_reopening_without_extension(): void
    {
        [, $reservation] = $this->hold();
        $user = $reservation->user;

        $first = $this->actingAs($user)->getJson("/api/reservations/{$reservation->code}/payment")->assertOk()->json('payment_attempt');
        $this->assertSame(100000, $first['amount']);
        $this->assertSame(self::ACCOUNT_NUMBER, $first['receiver']['account_number']);
        $this->assertNotEmpty($first['qr_url']);
        $this->assertSame($first['reference'], $first['transfer_content']);
        $this->assertSame('2026-11-03T09:10:00+07:00', $first['expires_at']);

        Carbon::setTestNow('2026-11-03 09:05:00');
        $again = $this->command($user, "/api/reservations/{$reservation->code}/payment")->assertOk()->json('payment_attempt');
        $this->assertSame([$first['reference'], $first['amount'], $first['expires_at']], [$again['reference'], $again['amount'], $again['expires_at']]);
        $this->assertSame(1, OwnerPaymentAttempt::count());
        $this->assertSame('2026-11-03 09:10:00', $reservation->fresh()->hold_expires_at->format('Y-m-d H:i:s'));
    }

    public function test_fr_74_matching_transfer_confirms_once_even_when_reported_twice(): void
    {
        [$court, $reservation] = $this->hold();
        $code = $this->paymentCode($reservation->code);

        Carbon::setTestNow('2026-11-03 09:05:00');
        $this->transfer($court, $code, 100000, null, 'evt-1')->assertOk();
        $this->transfer($court, $code, 100000, null, 'evt-1')->assertOk();
        // The same event id carrying different transaction data is refused, not booked again.
        $this->transfer($court, $code, 50000, null, 'evt-1')->assertConflict();

        $reservation->refresh();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame('confirmed', ReservationSlot::where('reservation_id', $reservation->id)->value('status'));
        $this->assertSame('active', ReservationEntitlement::where('reservation_id', $reservation->id)->value('status'));
        $this->assertSame(1, OwnerPaymentReceipt::count());
        $this->assertSame(1, OwnerPaymentAllocation::count());
        $this->assertSame('paid', OwnerPaymentAttempt::value('status'));
    }

    public function test_br_81_customer_cannot_confirm_a_direct_payment_booking_without_a_transfer(): void
    {
        [, $reservation] = $this->hold();

        $this->command($reservation->user, "/api/reservations/{$reservation->code}/confirm")->assertConflict();
        $this->assertSame('held', $reservation->fresh()->status);
    }

    public function test_br_86_short_excess_duplicate_and_unknown_transfers_never_confirm(): void
    {
        [$court, $reservation] = $this->hold();
        $code = $this->paymentCode($reservation->code);
        Carbon::setTestNow('2026-11-03 09:02:00');

        $this->transfer($court, $code, 90000)->assertOk();
        $this->transfer($court, $code, 110000)->assertOk();
        $this->transfer($court, 'ACUNKNOWNCODE0000001', 100000)->assertOk();
        $this->assertSame('held', $reservation->fresh()->status);

        $this->transfer($court, $code, 100000)->assertOk();
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->transfer($court, $code, 100000)->assertOk();

        $codes = OwnerPaymentReceipt::orderBy('id')->pluck('exception_code')->all();
        $this->assertSame(['partial_amount', 'excess_amount', 'unmatched_reference', null, 'attempt_not_pending'], $codes);
        // A code that matches no request is never tied to a booking, even with the booking's exact amount.
        $this->assertNull(OwnerPaymentReceipt::orderBy('id')->skip(2)->first()->purpose_id);
    }

    public function test_fr_75_br_82_zero_payable_booking_is_confirmed_without_a_payment_request(): void
    {
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, now()->addMonth(), ['amount' => 100000]);

        $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 0, ['voucher_program_id' => $program->id])
            ->assertCreated()->assertJsonPath('reservation.status', 'confirmed')->assertJsonPath('reservation.total_amount', 0);

        $this->assertSame(0, OwnerPaymentAttempt::count());
        $this->assertSame(VoucherUse::STATUS_USED, VoucherUse::value('status'));
    }

    public function test_br_83_voucher_is_used_with_the_confirmation_exactly_once(): void
    {
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, now()->addMonth());
        $code = $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 80000, ['voucher_program_id' => $program->id])
            ->assertCreated()->json('reservation.code');
        $this->assertSame(VoucherUse::STATUS_HELD, VoucherUse::value('status'));

        Carbon::setTestNow('2026-11-03 09:03:00');
        $this->transfer($court, $this->paymentCode($code), 80000, null, 'evt-v')->assertOk();
        $this->transfer($court, $this->paymentCode($code), 80000, null, 'evt-v')->assertOk();

        $this->assertSame('confirmed', Reservation::where('code', $code)->value('status'));
        $this->assertSame(1, VoucherUse::count());
        $use = VoucherUse::firstOrFail();
        $this->assertSame(VoucherUse::STATUS_USED, $use->status);
        $this->assertSame('2026-11-03 09:03:00', $use->used_at->format('Y-m-d H:i:s'));
    }

    public function test_br_83_voucher_stopped_before_the_transfer_is_not_consumed_and_the_transfer_is_an_exception(): void
    {
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, now()->addMonth());
        $code = $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 80000, ['voucher_program_id' => $program->id])
            ->assertCreated()->json('reservation.code');

        $program->forceFill(['stopped_at' => now()->addMinute()])->save();
        Carbon::setTestNow('2026-11-03 09:03:00');
        $this->transfer($court, $this->paymentCode($code), 80000)->assertOk();

        $this->assertSame('expired', Reservation::where('code', $code)->value('status'));
        $this->assertSame('voucher_changed', OwnerPaymentReceipt::value('exception_code'));
        $this->assertSame(VoucherUse::STATUS_RELEASED, VoucherUse::value('status'));
    }
}
