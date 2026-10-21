<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\BookingAttendance;
use App\Models\FinanceException;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\Concerns\HoldsLegacyCoupon;
use Tests\TestCase;

/** BR-84 and FR-79: the customer cancels a booking still pending payment and reads its detail (design D1, D10). */
class CustomerPaymentCancelTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use HoldsLegacyCoupon;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->fixture = $this->directOwnerCourt();
    }

    public function test_cancel_a_pending_booking_releases_its_court_time_and_voucher_once_without_a_refund(): void
    {
        $endsAt = now()->addDays(5)->startOfSecond();
        $reservation = $this->held($this->start());
        $voucher = $this->holdLegacyCoupon($reservation, $endsAt);
        $waiting = WaitlistEntry::create([
            'user_id' => $this->bookingCustomer($this->fixture['organization'])->id,
            'court_id' => $this->fixture['court']->id,
            'starts_at' => $this->start(),
            'ends_at' => $this->start()->addHour(),
            'status' => 'waiting',
        ]);

        $this->cancel($reservation)->assertOk()
            ->assertJsonPath('reservation.status', 'cancelled')
            ->assertJsonPath('reservation.metadata.release_reason', 'customer_payment_cancel');

        $cancelled = $reservation->fresh();
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(['cancelled'], $cancelled->slots()->pluck('status')->unique()->values()->all());
        $this->assertSame(['cancelled'], $cancelled->entitlements()->pluck('status')->unique()->values()->all());
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, $this->attempt($cancelled)->status);
        $this->assertSame(0, $voucher->fresh()->usage_count);
        // Releasing the voucher gives the use back without extending its validity.
        $this->assertTrue($voucher->fresh()->ends_at->equalTo($endsAt));
        $this->assertSame('notified', $waiting->fresh()->status);
        // Cancelling before payment involves no money, so nothing is owed back.
        $this->assertSame(0, OwnerRefundObligation::withoutGlobalScopes()->count());
        $this->assertSame(0, DB::table('refunds')->count());
    }

    public function test_repeated_cancellation_and_an_expired_booking_release_nothing_again(): void
    {
        $reservation = $this->held($this->start());
        $voucher = $this->holdLegacyCoupon($reservation, now()->addDays(5));
        $this->cancel($reservation)->assertOk();
        $cancelledAt = $reservation->fresh()->cancelled_at;

        $this->cancel($reservation)->assertOk()->assertJsonPath('reservation.status', 'cancelled');

        $this->assertTrue($reservation->fresh()->cancelled_at->equalTo($cancelledAt));
        $this->assertSame(0, $voucher->fresh()->usage_count);

        $expiring = $this->held($this->start()->addHours(2));
        $this->travel(11)->minutes();
        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());

        $this->cancel($expiring)->assertOk()->assertJsonPath('reservation.status', 'expired');
        $this->assertSame('payment_deadline', $expiring->fresh()->metadata['release_reason']);
    }

    public function test_a_booking_confirmed_by_its_transfer_first_cannot_be_cancelled(): void
    {
        $reservation = $this->held($this->start());
        $this->transfer($reservation, 'paid-first');
        $this->assertSame('confirmed', $reservation->fresh()->status);

        $this->cancel($reservation)->assertStatus(409)->assertJsonPath('code', 'RESERVATION_NOT_PENDING');

        $confirmed = $reservation->fresh();
        $this->assertSame('confirmed', $confirmed->status);
        $this->assertArrayNotHasKey('release_reason', $confirmed->metadata ?? []);
        $this->assertNotContains('cancelled', $confirmed->slots()->pluck('status')->all());
    }

    public function test_another_customer_cannot_find_the_booking_and_the_venue_cannot_cancel_it(): void
    {
        $reservation = $this->held($this->start());

        $this->cancel($reservation, $this->bookingCustomer($this->fixture['organization']))->assertNotFound();
        // The owner sees every booking of the organization but only its customer may cancel it.
        $this->cancel($reservation, $this->fixture['owner'])->assertForbidden();

        $this->assertSame('held', $reservation->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_PENDING, $this->attempt($reservation)->status);
    }

    public function test_a_transfer_after_cancellation_is_a_payment_exception_and_the_booking_stays_cancelled(): void
    {
        $reservation = $this->held($this->start());
        $this->cancel($reservation)->assertOk();

        // Before the original deadline: the request expired because the booking was cancelled.
        $receipt = $this->transfer($reservation, 'paid-after-cancel');

        $this->assertSame([OwnerPaymentReceipt::STATUS_LATE, 'transfer_after_expiry'], [$receipt->status, $receipt->exception_code]);
        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertSame(1, FinanceException::withoutGlobalScopes()->where('type', 'owner_payment_transfer_after_expiry')->count());
        $this->assertSame(0, OwnerRefundObligation::withoutGlobalScopes()->count());
    }

    public function test_pending_booking_detail_shows_its_deadline_payment_request_and_booker(): void
    {
        $reservation = $this->held($this->start());

        $this->detail($reservation)->assertOk()
            ->assertJsonPath('reservation.status', 'held')
            ->assertJsonPath('reservation.hold_expires_at', $reservation->hold_expires_at->toJSON())
            ->assertJsonPath('reservation.payment.status', 'pending')
            ->assertJsonPath('reservation.payment.receipt', null)
            ->assertJsonPath('reservation.booker.email', $this->fixture['payer']->email);
    }

    public function test_completed_and_expired_booking_details_show_attendance_and_payment_results(): void
    {
        $completed = $this->held($this->start());
        $this->transfer($completed, 'paid-completed');
        BookingAttendance::create([
            'organization_id' => $completed->organization_id,
            'reservation_id' => $completed->id,
            'status' => BookingAttendance::STATUS_CHECKED_OUT,
            'checked_in_at' => $this->start(),
            'checked_in_by' => $this->fixture['owner']->id,
            'checked_out_at' => $this->start()->addHour(),
            'checked_out_by' => $this->fixture['owner']->id,
            'notes' => 'Staff only',
        ]);
        $completed->forceFill(['metadata' => [...$completed->fresh()->metadata, 'operational_status' => 'completed']])->save();

        $this->detail($completed)->assertOk()
            ->assertJsonPath('reservation.metadata.operational_status', 'completed')
            ->assertJsonPath('reservation.payment.status', 'paid')
            ->assertJsonPath('reservation.payment.receipt.status', OwnerPaymentReceipt::STATUS_ALLOCATED)
            ->assertJsonPath('reservation.attendance.checked_in_at', $this->start()->toJSON())
            ->assertJsonPath('reservation.attendance.checked_out_at', $this->start()->addHour()->toJSON())
            ->assertJsonMissingPath('reservation.attendance.notes');

        $expired = $this->held($this->start()->addHours(2));
        $this->travel(11)->minutes();
        app(ExpirePendingBookings::class)->execute();

        $this->detail($expired)->assertOk()
            ->assertJsonPath('reservation.status', 'expired')
            ->assertJsonPath('reservation.payment.status', 'expired');
    }

    private function start(): CarbonInterface
    {
        return now()->addDays(2)->setTime(18, 0);
    }

    private function held(CarbonInterface $start): Reservation
    {
        $code = $this->checkoutFor($this->fixture['payer'], $this->fixture['organization'], $this->fixture['court'], [$start])
            ->assertCreated()->assertJsonPath('reservation.status', 'held')->json('reservation.code');

        return Reservation::where('code', $code)->firstOrFail();
    }

    private function attempt(Reservation $reservation): OwnerPaymentAttempt
    {
        return OwnerPaymentAttempt::where('purpose_id', $reservation->code)->firstOrFail();
    }

    /** The full amount, with the booking's payment code. */
    private function transfer(Reservation $reservation, string $eventId): OwnerPaymentReceipt
    {
        $attempt = $this->attempt($reservation);

        return $this->payOwner($this->fixture['connection'], $this->fixture['secret'], $attempt->reference, (int) $attempt->amount, $eventId);
    }

    private function cancel(Reservation $reservation, ?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->fixture['payer'])->withHeaders([
            'X-Organization-ID' => (string) $this->fixture['organization']->id,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson("/api/reservations/{$reservation->code}/cancel-payment");
    }

    private function detail(Reservation $reservation): TestResponse
    {
        return $this->actingAs($this->fixture['payer'])
            ->withHeaders(['X-Organization-ID' => (string) $this->fixture['organization']->id])
            ->getJson("/api/reservations/{$reservation->code}");
    }
}
