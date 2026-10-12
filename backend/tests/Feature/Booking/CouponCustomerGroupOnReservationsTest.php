<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\DiscountService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Returning/first-time coupon groups read reservations only; a 'legacy' mirror counts by its legacy_status, any other reservation by its own status.
 */
class CouponCustomerGroupOnReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returning_group_is_satisfied_by_a_legacy_completed_mirror_alone(): void
    {
        [$org, $court] = $this->court();
        $customer = User::factory()->create();
        $this->mirroredBooking($court, $customer, 'completed');
        $this->assertSame('legacy', $this->reservationsOf($customer)->value('status'));
        $this->coupon($org, Coupon::GROUP_RETURNING, 'BACK10');
        $this->coupon($org, Coupon::GROUP_FIRST_TIME, 'FIRST10');

        $this->assertSame(90000, app(DiscountService::class)->preview($customer, 100000, 'BACK10', $org->id)['total']);
        $this->expectException(ValidationException::class);
        app(DiscountService::class)->preview($customer, 100000, 'FIRST10', $org->id);
    }

    public function test_first_time_group_ignores_cancelled_reservations_including_a_cancelled_legacy_mirror(): void
    {
        [$org, $court] = $this->court();
        $customer = User::factory()->create();
        Reservation::create([
            'organization_id' => $org->id, 'user_id' => $customer->id, 'court_id' => $court->id, 'code' => 'RSV-CXL-'.uniqid(),
            'status' => 'cancelled', 'currency' => 'VND', 'total_amount' => 100000, 'payment_model' => 'legacy_wallet_v1',
        ]);
        $this->mirroredBooking($court, $customer, 'cancelled');
        $this->assertSame(['cancelled', 'legacy'], $this->reservationsOf($customer)->orderBy('status')->pluck('status')->all());
        $this->coupon($org, Coupon::GROUP_FIRST_TIME, 'FIRST10');
        $this->coupon($org, Coupon::GROUP_RETURNING, 'BACK10');

        $this->assertSame(90000, app(DiscountService::class)->preview($customer, 100000, 'FIRST10', $org->id)['total']);
        $this->expectException(ValidationException::class);
        app(DiscountService::class)->preview($customer, 100000, 'BACK10', $org->id);
    }

    public function test_returning_group_counts_a_v1_no_show_by_its_confirmed_mirror(): void
    {
        [$org, $court] = $this->court();
        $customer = User::factory()->create();
        // What the V1 admin no-show does: legacy_status follows, the mirror stays confirmed as a V2 no-show does.
        $booking = $this->mirroredBooking($court, $customer, 'confirmed');
        $booking->update(['status' => 'no_show']);
        app(LegacyBookingBackfillService::class)->syncProjection($booking);
        $this->assertSame('confirmed', $this->reservationsOf($customer)->value('status'));
        $this->coupon($org, Coupon::GROUP_RETURNING, 'BACK10');
        $this->coupon($org, Coupon::GROUP_FIRST_TIME, 'FIRST10');

        $this->assertSame(90000, app(DiscountService::class)->preview($customer, 100000, 'BACK10', $org->id)['total']);
        $this->expectException(ValidationException::class);
        app(DiscountService::class)->preview($customer, 100000, 'FIRST10', $org->id);
    }

    /**
     * @return array{0: Organization, 1: Court}
     */
    private function court(): array
    {
        $org = Organization::create(['name' => 'Coupon Org', 'slug' => 'coupon-org-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Coupon Venue', 'slug' => 'coupon-venue-'.uniqid()]);

        return [$org, Court::create(['venue_id' => $venue->id, 'name' => 'Coupon Court'])];
    }

    private function mirroredBooking(Court $court, User $customer, string $status): Booking
    {
        $booking = Booking::create(['code' => 'BK-CPN-'.uniqid(), 'user_id' => $customer->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => $status]);
        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'starts_at' => now()->subDays(2)->setTime(18, 0),
            'ends_at' => now()->subDays(2)->setTime(19, 0),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        return $booking;
    }

    private function coupon(Organization $org, string $group, string $code): void
    {
        Coupon::create([
            'organization_id' => $org->id, 'code' => $code, 'discount_type' => 'percent', 'discount_value' => 10,
            'is_active' => true, 'status' => Coupon::STATUS_ACTIVE, 'eligibility_group' => $group,
        ]);
    }

    private function reservationsOf(User $customer)
    {
        return Reservation::withoutGlobalScope('tenant_isolation')->where('user_id', $customer->id);
    }
}
