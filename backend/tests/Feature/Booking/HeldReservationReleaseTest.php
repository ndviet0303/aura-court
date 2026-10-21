<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Coupon;
use App\Models\OwnerPaymentAttempt;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Services\Reservation\HeldReservationRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** Design D2: one release of an unpaid hold, shared by expiry, cancellation, overdue release and price change. */
class HeldReservationReleaseTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use RefreshDatabase;

    public function test_releasing_twice_gives_back_the_voucher_and_reserved_points_once(): void
    {
        [$reservation, $voucher] = $this->heldWithVoucher();
        PointLedger::create([
            'organization_id' => $reservation->organization_id,
            'user_id' => $reservation->user_id,
            'delta' => -50,
            'balance_after' => 0,
            'source_type' => 'reservation',
            'source_id' => $reservation->id,
        ]);

        $this->assertTrue($this->release($reservation, 'cancelled', 'customer_payment_cancel'));
        $this->assertFalse($this->release($reservation, 'cancelled', 'customer_payment_cancel'));

        $released = $reservation->fresh();
        $this->assertSame('cancelled', $released->status);
        $this->assertNotNull($released->cancelled_at);
        $this->assertSame('customer_payment_cancel', $released->metadata['release_reason']);
        $this->assertSame(['cancelled'], $released->slots()->pluck('status')->unique()->values()->all());
        $this->assertSame(['cancelled'], $released->entitlements()->pluck('status')->unique()->values()->all());
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, OwnerPaymentAttempt::where('purpose_id', $released->code)->firstOrFail()->status);
        $this->assertSame(0, $voucher->fresh()->usage_count);
        $this->assertSame(1, PointLedger::where('source_type', 'reservation_release')->where('source_id', $released->id)->count());
        $this->assertSame(0, (int) PointLedger::where('user_id', $released->user_id)->sum('delta'));
    }

    public function test_a_confirmed_reservation_is_left_as_it_is(): void
    {
        [$reservation, $voucher] = $this->heldWithVoucher();
        $reservation->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();

        $this->assertFalse($this->release($reservation, 'expired', 'payment_deadline'));

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertArrayNotHasKey('release_reason', $reservation->fresh()->metadata);
        $this->assertSame(1, $voucher->fresh()->usage_count);
    }

    public function test_expiry_job_releases_through_the_shared_step(): void
    {
        [$reservation, $voucher] = $this->heldWithVoucher();
        $this->travel(11)->minutes();

        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());
        $this->assertSame(0, app(ExpirePendingBookings::class)->execute());

        $expired = $reservation->fresh();
        $this->assertSame('expired', $expired->status);
        $this->assertSame('payment_deadline', $expired->metadata['release_reason']);
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, OwnerPaymentAttempt::where('purpose_id', $expired->code)->firstOrFail()->status);
        $this->assertSame(0, $voucher->fresh()->usage_count);
    }

    /** @return array{0: Reservation, 1: Coupon} */
    private function heldWithVoucher(): array
    {
        $fixture = $this->directOwnerCourt();
        $voucher = Coupon::create([
            'organization_id' => $fixture['organization']->id,
            'code' => 'SAVE20K',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'is_active' => true,
            'status' => Coupon::STATUS_ACTIVE,
        ]);
        $code = $this->checkoutFor($fixture['payer'], $fixture['organization'], $fixture['court'], [now()->addDays(2)->setTime(18, 0)], ['coupon_code' => 'SAVE20K'])
            ->assertCreated()->json('reservation.code');
        $this->assertSame(1, $voucher->fresh()->usage_count);

        return [Reservation::where('code', $code)->firstOrFail(), $voucher];
    }

    private function release(Reservation $reservation, string $status, string $reason): bool
    {
        $release = app(HeldReservationRelease::class);

        return DB::transaction(fn (): bool => $release->releaseHeld($release->lock($reservation), $status, $reason));
    }
}
