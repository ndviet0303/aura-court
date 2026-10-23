<?php

namespace Tests\Feature\PosVenueInventory;

use App\Models\CashShift;
use App\Models\PointLedger;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module02\Concerns\SeedsBookings;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\TestCase;

/** pos-shift-venue-scope: shifts and orders belong to one venue; users see their own open shift and their venues' shifts. */
class ShiftVenueScopeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsBookings;
    use SellsAtVenues;

    private Venue $venueA;

    private Venue $venueB;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePos();
        $this->venueA = $this->openVenue(['name' => ['vi' => 'Cầu Giấy']]);
        $this->venueB = $this->openVenue(['organization_id' => $this->venueA->organization_id, 'name' => ['vi' => 'Đống Đa']]);
        $this->owner = $this->ownerOf($this->venueA->organization);
    }

    public function test_owner_of_several_venues_must_choose_one_to_open_a_shift(): void
    {
        $this->pos($this->owner, 'POST', 'pos/shifts', $this->venueA, ['opening_cash' => 0])
            ->assertStatus(422)->assertJsonPath('message', 'Vui lòng chọn cơ sở bạn được giao.');

        $this->pos($this->owner, 'POST', 'pos/shifts', $this->venueA, ['opening_cash' => 0, 'venue_id' => $this->venueB->id])
            ->assertCreated()->assertJsonPath('shift.venue_id', $this->venueB->id);
    }

    public function test_staff_with_one_venue_opens_there_and_cannot_open_elsewhere(): void
    {
        $staff = $this->staffOf($this->venueA);

        $this->pos($staff, 'POST', 'pos/shifts', $this->venueA, ['opening_cash' => 0, 'venue_id' => $this->venueB->id])->assertForbidden();
        $this->pos($staff, 'POST', 'pos/shifts', $this->venueA, ['opening_cash' => 0])
            ->assertCreated()->assertJsonPath('shift.venue_id', $this->venueA->id);
    }

    public function test_owner_without_a_shift_sees_none_while_staff_work(): void
    {
        $this->shiftAt($this->staffOf($this->venueA), $this->venueA);

        $this->pos($this->owner, 'GET', 'pos/shifts/active', $this->venueA)->assertOk()->assertJsonPath('shift', null);
    }

    public function test_cashier_gets_their_own_shift_with_its_venue_and_totals(): void
    {
        $staff = $this->staffOf($this->venueA);
        $shift = $this->shiftAt($staff, $this->venueA, openingCash: 300000);
        $this->shiftAt($this->staffOf($this->venueA), $this->venueA);

        $this->pos($staff, 'GET', 'pos/shifts/active', $this->venueA)->assertOk()
            ->assertJsonPath('shift.id', $shift->id)
            ->assertJsonPath('shift.venue.name', 'Cầu Giấy')
            ->assertJsonPath('shift.total_sales', 0)
            ->assertJsonPath('shift.expected_cash', 300000);
    }

    public function test_shift_list_holds_only_operable_venues_and_legacy_shifts_for_the_owner(): void
    {
        $staffA = $this->staffOf($this->venueA);
        $atA = $this->shiftAt($staffA, $this->venueA);
        $atB = $this->shiftAt($this->staffOf($this->venueB), $this->venueB);
        $legacy = $this->shiftAt($this->owner, $this->venueA, withoutVenue: true);

        $this->pos($staffA, 'GET', 'pos/shifts', $this->venueA)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $atA->id)
            ->assertJsonPath('data.0.venue.name', 'Cầu Giấy');

        $shifts = collect($this->pos($this->owner, 'GET', 'pos/shifts', $this->venueA)->assertOk()->json('data'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$atA->id, $atB->id, $legacy->id], $shifts->keys()->all());
        $this->assertSame('Đống Đa', $shifts[$atB->id]['venue']['name']);
        $this->assertNull($shifts[$legacy->id]['venue_id']);
        $this->assertNull($shifts[$legacy->id]['venue']);
    }

    public function test_order_belongs_to_the_shift_venue(): void
    {
        $staff = $this->staffOf($this->venueA, $this->venueB);
        $shift = $this->shiftAt($staff, $this->venueA);
        $product = $this->product($this->venueA, ['category' => 'services']);

        $this->pos($staff, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product, extra: ['venue_id' => $this->venueB->id]))
            ->assertStatus(422)->assertJsonPath('message', 'Đơn phải thuộc cơ sở của ca đang mở.');

        $this->pos($staff, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product))
            ->assertCreated()
            ->assertJsonPath('order.venue_id', $this->venueA->id)
            ->assertJsonPath('order.cash_shift_id', $shift->id);
    }

    public function test_sale_needs_an_open_shift_with_a_venue(): void
    {
        $product = $this->product($this->venueA, ['category' => 'services']);

        $this->pos($this->owner, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product))->assertStatus(409);

        $this->shiftAt($this->owner, $this->venueA, withoutVenue: true);
        $this->pos($this->owner, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product))->assertStatus(422);
        $this->assertSame(0, PosOrder::count());
    }

    public function test_booking_at_another_venue_is_refused_without_any_effect(): void
    {
        $staff = $this->staffOf($this->venueA, $this->venueB);
        $this->shiftAt($staff, $this->venueA);
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);
        $booking = $this->booking($this->activeCourt($this->venueB), $this->tomorrowAt(10));

        $this->pos($staff, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product, extra: ['reservation_id' => $booking->id, 'customer_id' => $booking->user_id]))
            ->assertStatus(422)->assertJsonPath('message', 'Lượt đặt sân thuộc cơ sở khác với ca đang mở.');

        $this->assertSame(0, PosOrder::count());
        $this->assertSame(0, PosPayment::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, PointLedger::count());
    }

    public function test_service_tab_of_another_venue_is_not_settled_on_this_shift(): void
    {
        $staff = $this->staffOf($this->venueA, $this->venueB);
        $this->shiftAt($staff, $this->venueA);
        $product = $this->product($this->venueB);
        $this->stockAt($this->venueB, $product, 5);
        $booking = $this->booking($this->activeCourt($this->venueB), $this->tomorrowAt(10));
        $tab = $this->pos($staff, 'POST', 'service-tabs', $this->venueB, ['reservation_id' => $booking->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertCreated()->json('tab.id');

        $this->pos($staff, 'POST', "service-tabs/{$tab}/settle", $this->venueB, ['payment_method' => 'cash'])
            ->assertStatus(422)->assertJsonPath('message', 'Lượt đặt sân thuộc cơ sở khác với ca đang mở.');
        $this->assertSame(0, PosOrder::count());

        CashShift::query()->update(['venue_id' => $this->venueB->id]);
        $this->pos($staff, 'POST', "service-tabs/{$tab}/settle", $this->venueB, ['payment_method' => 'cash'])
            ->assertOk()->assertJsonPath('order.venue_id', $this->venueB->id);
    }
}
