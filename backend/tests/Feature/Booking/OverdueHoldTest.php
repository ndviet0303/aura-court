<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Coupon;
use App\Models\OwnerPaymentAttempt;
use App\Models\Reservation;
use App\Notifications\AuraBookingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** BR-77, BR-73: only live holds block the court, and one booking is one continuous interval. */
class OverdueHoldTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use RefreshDatabase;

    public function test_overdue_hold_does_not_block_and_is_released_once_by_the_new_booking(): void
    {
        Notification::fake();
        $fixture = $this->directOwnerCourt();
        $voucher = Coupon::create([
            'organization_id' => $fixture['organization']->id,
            'code' => 'SAVE20K',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'is_active' => true,
            'status' => Coupon::STATUS_ACTIVE,
        ]);
        $start = now()->addDays(2)->setTime(15, 0);
        $overdueCode = $this->checkoutFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$start], ['coupon_code' => 'SAVE20K'])
            ->assertCreated()->json('reservation.code');
        $this->assertSame(1, $voucher->fresh()->usage_count);

        $this->travel(11)->minutes();
        $other = $this->bookingCustomer($fixture['organization']);
        $this->checkoutFor($other, $fixture['organization'], $fixture['court'], [$start])
            ->assertCreated()
            ->assertJsonPath('reservation.status', 'held');

        $overdue = Reservation::where('code', $overdueCode)->firstOrFail();
        $this->assertSame('expired', $overdue->status);
        $this->assertSame('payment_deadline', $overdue->metadata['release_reason']);
        $this->assertSame(['expired'], $overdue->slots()->pluck('status')->unique()->values()->all());
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, OwnerPaymentAttempt::where('purpose_id', $overdueCode)->firstOrFail()->status);
        $this->assertSame(0, $voucher->fresh()->usage_count);
        Notification::assertSentTo($fixture['payer'], AuraBookingNotification::class);

        $this->assertSame(0, app(ExpirePendingBookings::class)->execute());
        $this->assertSame(0, $voucher->fresh()->usage_count);
    }

    public function test_live_hold_blocks_an_overlapping_request(): void
    {
        $fixture = $this->directOwnerCourt();
        $start = now()->addDays(2)->setTime(15, 0);
        $this->checkoutFor($fixture['payer'], $fixture['organization'], $fixture['court'], [$start])->assertCreated();

        $this->travel(9)->minutes();
        $this->submitCheckout($this->bookingCustomer($fixture['organization']), $fixture['organization'], [
            'court_id' => $fixture['court']->id,
            'slots' => [$start->toIso8601String()],
            'expected_total' => 200000,
        ])->assertConflict();

        $this->assertSame(1, Reservation::count());
    }

    public function test_gapped_selection_is_refused_even_where_the_venue_once_allowed_it(): void
    {
        $fixture = $this->directOwnerCourt(booking: ['require_continuity' => false]);
        $day = now()->addDays(2);

        $this->submitCheckout($fixture['payer'], $fixture['organization'], [
            'court_id' => $fixture['court']->id,
            'slots' => [$day->copy()->setTime(10, 0)->toIso8601String(), $day->copy()->setTime(12, 0)->toIso8601String()],
            'expected_total' => 400000,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('reservations', 0);
    }
}
