<?php

namespace Tests\Feature\Booking;

use App\Models\Coupon;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\OwnerPaymentAttempt;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** BR-79: a payable hold expires at the earliest of ten minutes, the booking start and the voucher end. */
class PaymentDeadlineTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use RefreshDatabase;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->day = now()->addDays(3)->toDateString();
    }

    public function test_start_time_caps_the_deadline_and_a_later_transfer_is_an_exception(): void
    {
        $fixture = $this->directOwnerCourt();
        $this->travelTo($this->at('14:58'));

        $code = $this->checkoutFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$this->at('15:00')])
            ->assertCreated()
            ->assertJsonPath('reservation.status', 'held')
            ->json('reservation.code');

        $reservation = Reservation::where('code', $code)->firstOrFail();
        $attempt = OwnerPaymentAttempt::where('purpose_id', $code)->firstOrFail();
        $this->assertTrue($reservation->hold_expires_at->equalTo($this->at('15:00')));
        $this->assertTrue($attempt->expires_at->equalTo($this->at('15:00')));

        $this->travelTo($this->at('15:01'));
        $receipt = $this->payOwner($fixture['connection'], $fixture['secret'], $attempt->reference, 200000, 'late-transfer', $this->at('15:01')->toIso8601String());

        $this->assertSame('transfer_after_expiry', $receipt->exception_code);
        $this->assertSame('held', $reservation->fresh()->status);
        $this->assertTrue(FinanceException::withoutGlobalScopes()->where('type', 'owner_payment_transfer_after_expiry')->exists());
    }

    public function test_voucher_end_caps_the_deadline(): void
    {
        $fixture = $this->directOwnerCourt();
        $this->voucher($fixture['organization'], $this->at('14:59'));
        $this->travelTo($this->at('14:50'));

        $code = $this->checkoutFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$this->at('16:00')], ['coupon_code' => 'ENDSOON'])
            ->assertCreated()
            ->assertJsonPath('reservation.total_amount', 180000)
            ->json('reservation.code');

        $this->assertTrue(Reservation::where('code', $code)->firstOrFail()->hold_expires_at->equalTo($this->at('14:59')));
        $this->assertTrue(OwnerPaymentAttempt::where('purpose_id', $code)->firstOrFail()->expires_at->equalTo($this->at('14:59')));
    }

    public function test_ten_minute_cap_ignores_the_venue_hold_setting_and_reopening_keeps_the_same_request(): void
    {
        $fixture = $this->directOwnerCourt(booking: ['hold_ttl_minutes' => 30]);
        $this->travelTo($this->at('09:00'));

        $code = $this->checkoutFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$this->at('18:00')])
            ->assertCreated()
            ->json('reservation.code');
        $reservation = Reservation::where('code', $code)->firstOrFail();
        $this->assertTrue($reservation->hold_expires_at->equalTo($this->at('09:10')));
        $attempt = OwnerPaymentAttempt::where('purpose_id', $code)->firstOrFail();

        $this->travelTo($this->at('09:05'));
        $headers = ['X-Organization-ID' => (string) $fixture['organization']->id];
        foreach (['getJson', 'postJson'] as $method) {
            $this->actingAs($fixture['payer'])->withHeaders($headers + ['Idempotency-Key' => (string) Str::uuid()])
                ->{$method}("/api/reservations/{$code}/payment")
                ->assertOk()
                ->assertJsonPath('payment_attempt.reference', $attempt->reference)
                ->assertJsonPath('payment_attempt.amount', 200000)
                ->assertJsonPath('payment_attempt.receiver.account_number', '0987654321')
                ->assertJsonPath('payment_attempt.expires_at', $this->at('09:10')->toIso8601String());
        }

        $this->assertSame(1, OwnerPaymentAttempt::where('purpose_id', $code)->count());
        $this->assertTrue($reservation->fresh()->hold_expires_at->equalTo($this->at('09:10')));
    }

    public function test_no_payment_window_at_the_start_time_creates_nothing(): void
    {
        $fixture = $this->directOwnerCourt();
        $this->travelTo($this->at('15:00'));

        $this->checkoutFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$this->at('15:00')])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PAYMENT_WINDOW_CLOSED');

        $this->assertNothingHeld();
    }

    public function test_no_payment_window_when_the_voucher_ends_now_creates_nothing(): void
    {
        $fixture = $this->directOwnerCourt();
        $voucher = $this->voucher($fixture['organization'], $this->at('14:50'));
        $this->travelTo($this->at('14:50'));

        $this->checkoutFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$this->at('16:00')], ['coupon_code' => 'ENDSOON'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PAYMENT_WINDOW_CLOSED');

        $this->assertNothingHeld();
        $this->assertSame(0, $voucher->fresh()->usage_count);
    }

    private function at(string $time): CarbonInterface
    {
        return Date::parse($this->day.' '.$time, 'Asia/Ho_Chi_Minh');
    }

    private function voucher(Organization $organization, CarbonInterface $endsAt): Coupon
    {
        return Coupon::create([
            'organization_id' => $organization->id,
            'code' => 'ENDSOON',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'is_active' => true,
            'status' => Coupon::STATUS_ACTIVE,
            'ends_at' => $endsAt,
        ]);
    }

    private function assertNothingHeld(): void
    {
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_slots', 0);
        $this->assertDatabaseCount('reservation_entitlements', 0);
        $this->assertDatabaseCount('owner_payment_attempts', 0);
    }
}
