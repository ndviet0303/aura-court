<?php

namespace Tests\Feature\Module04;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Domain\Pricing\Actions\PublishPriceTableVersion;
use App\Domain\Pricing\DTOs\PublishPriceTableVersionData;
use App\Models\Court;
use App\Models\OutboxMessage;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\PriceTable;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\VoucherUse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksCourts;
use Tests\TestCase;

/** FR-76, FR-77, FR-78, BR-84, BR-85, BR-87: a booking pending payment ends by cancel, deadline or price change. */
class PaymentCancelAndExpiryTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
    }

    /** @return array{0: Court, 1: Reservation} */
    private function heldWithVoucher(): array
    {
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, now()->addMonth());
        $code = $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 80000, ['voucher_program_id' => $program->id])
            ->assertCreated()->json('reservation.code');

        return [$court, Reservation::where('code', $code)->firstOrFail()];
    }

    private function republishPrices(Court $court, int $pricePerHour): void
    {
        app(PublishPriceTableVersion::class)->execute(new PublishPriceTableVersionData(
            (int) PriceTable::where('venue_id', $court->venue_id)->value('id'),
            null,
            [['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'dates' => null, 'start_minute' => 0, 'end_minute' => 1440, 'price_per_hour' => $pricePerHour]],
        ));
    }

    public function test_fr_76_br_84_customer_cancels_a_pending_booking_and_frees_court_and_voucher_once(): void
    {
        [$court, $reservation] = $this->heldWithVoucher();
        $uri = "/api/reservations/{$reservation->code}/cancel-payment";

        $this->command($reservation->user, $uri)->assertOk()->assertJsonPath('reservation.status', 'cancelled');
        $this->command($reservation->user, $uri)->assertOk()->assertJsonPath('reservation.status', 'cancelled');

        $reservation->refresh();
        $this->assertSame('customer_payment_cancel', $reservation->metadata['release_reason']);
        $this->assertSame(0, ReservationSlot::where('reservation_id', $reservation->id)->where('status', 'held')->count());
        $this->assertSame(VoucherUse::STATUS_RELEASED, VoucherUse::value('status'));
        $this->assertSame(1, VoucherUse::count());
        $this->assertSame('expired', OwnerPaymentAttempt::value('status'));
        $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated();
    }

    public function test_br_84_only_the_booker_cancels_and_only_while_pending(): void
    {
        [$court, $reservation] = $this->heldWithVoucher();
        $uri = "/api/reservations/{$reservation->code}/cancel-payment";

        $refused = $this->command($this->customer(), $uri);
        $this->assertContains($refused->status(), [403, 404]);
        $this->assertSame('held', $reservation->fresh()->status);

        $this->transfer($court, $this->paymentCode($reservation->code), 80000)->assertOk();
        $this->command($reservation->user, $uri)->assertConflict()->assertJsonPath('code', 'RESERVATION_NOT_PENDING');
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_br_85_transfer_after_cancel_is_an_exception_and_does_not_revive_the_booking(): void
    {
        [$court, $reservation] = $this->heldWithVoucher();
        $this->command($reservation->user, "/api/reservations/{$reservation->code}/cancel-payment")->assertOk();

        $this->transfer($court, $this->paymentCode($reservation->code), 80000)->assertOk();

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertSame('transfer_after_expiry', OwnerPaymentReceipt::value('exception_code'));
        $this->assertSame(VoucherUse::STATUS_RELEASED, VoucherUse::value('status'));
    }

    public function test_fr_77_deadline_passes_and_the_booking_expires_with_its_voucher_released(): void
    {
        [, $reservation] = $this->heldWithVoucher();

        Carbon::setTestNow('2026-11-03 09:09:59');
        $this->assertSame(0, app(ExpirePendingBookings::class)->execute());
        Carbon::setTestNow('2026-11-03 09:10:01');
        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());

        $reservation->refresh();
        $this->assertSame('expired', $reservation->status);
        $this->assertSame('payment_deadline', $reservation->metadata['release_reason']);
        $this->assertSame(VoucherUse::STATUS_RELEASED, VoucherUse::value('status'));
        $this->assertSame(0, app(ExpirePendingBookings::class)->execute());
    }

    public function test_fr_78_br_87_uc_4_10_a3_new_price_expires_a_pending_booking_and_tells_the_customer(): void
    {
        $court = $this->payableCourt();
        $customer = $this->customer();
        $code = $this->checkout($customer, $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');

        Carbon::setTestNow('2026-11-03 09:02:00');
        $this->republishPrices($court, 120000);

        $reservation = Reservation::where('code', $code)->firstOrFail();
        $this->assertSame('expired', $reservation->status);
        $this->assertSame('price_changed', $reservation->metadata['release_reason']);
        $this->assertSame(1, OutboxMessage::where('event_type', 'booking.price_changed')->count());
        // The booking detail tells the customer why it ended.
        $this->actingAs($customer)->getJson("/api/reservations/{$code}")
            ->assertOk()
            ->assertJsonPath('reservation.metadata.release_reason', 'price_changed');

        // The old payment request no longer books anything.
        Carbon::setTestNow('2026-11-03 09:03:00');
        $this->transfer($court, $this->paymentCode($code), 100000)->assertOk();
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertNotNull(OwnerPaymentReceipt::value('exception_code'));
    }

    public function test_br_87_same_amount_keeps_the_booking_and_confirmed_bookings_are_not_repriced(): void
    {
        $court = $this->payableCourt();
        $held = $this->checkout($this->customer(), $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');
        $paid = $this->checkout($this->customer(), $court, '2026-11-03 20:00', '2026-11-03 21:00', 100000)->assertCreated()->json('reservation.code');
        $this->transfer($court, $this->paymentCode($paid), 100000)->assertOk();

        $this->republishPrices($court, 100000);
        $this->assertSame('held', Reservation::where('code', $held)->value('status'));
        $this->assertSame('2026-11-03 09:10:00', Reservation::where('code', $held)->value('hold_expires_at')->format('Y-m-d H:i:s'));

        $this->republishPrices($court, 150000);
        $this->assertSame('confirmed', Reservation::where('code', $paid)->value('status'));
        $this->assertSame(100000, (int) Reservation::where('code', $paid)->value('total_amount'));
        $this->assertSame('expired', Reservation::where('code', $held)->value('status'));
    }
}
