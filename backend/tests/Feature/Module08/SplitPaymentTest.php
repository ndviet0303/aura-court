<?php

namespace Tests\Feature\Module08;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\SplitGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksCourts;
use Tests\TestCase;

/** Split payment: the customer of a held booking splits it into shares that teammates pay by transfer. */
class SplitPaymentTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
        config(['features.flags.marketplace_split_payments' => true]);
    }

    public function test_customer_opens_or_creates_the_split_of_a_held_booking_and_paid_shares_confirm_it(): void
    {
        $court = $this->payableCourt();
        $host = $this->customer();
        // A customer belongs to no venue organization; another organization is the one resolved by default.
        $other = Organization::create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $other->id, 'user_id' => $host->id, 'role' => 'customer', 'status' => 'active']);
        $code = $this->checkout($host, $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');
        $url = "/api/reservations/{$code}/split";

        $this->actingAs($host)->getJson($url)->assertOk()
            ->assertJsonPath('reservation.status', 'held')
            ->assertJsonPath('split', null);
        $this->actingAs($host)->getJson('/api/auth/me')->assertJsonPath('capabilities.split_payments_enabled', true);

        $this->command($host, $url, ['shares' => [['amount' => 50001], ['amount' => 49999]]])->assertCreated()
            ->assertJsonPath('split.reservation.code', $code);
        $shares = $this->actingAs($host)->getJson($url)->assertOk()->assertJsonCount(2, 'split.shares')->json('split.shares');
        $this->actingAs($host)->getJson('/api/me/reservations')->assertJsonPath('data.0.has_split_group', true);

        // One group per booking; another customer cannot see it.
        $this->command($host, $url, ['shares' => [['amount' => 50000], ['amount' => 50000]]])->assertStatus(409);
        $this->assertSame(1, SplitGroup::count());
        $this->actingAs($this->customer())->getJson($url)->assertNotFound();

        foreach ($shares as $share) {
            $this->assertSame($share['amount'], $share['payment_attempt']['amount']);
            $this->transfer($court, $share['payment_attempt']['reference'], $share['amount'])->assertOk();
        }

        $this->assertSame('confirmed', Reservation::where('code', $code)->value('status'));
        $this->actingAs($host)->getJson($url)->assertOk()->assertJsonPath('split.status', 'confirmed');
    }

    public function test_split_is_refused_when_it_is_off_for_the_venue_or_the_booking_is_not_held(): void
    {
        $court = $this->payableCourt();
        $host = $this->customer();
        $code = $this->checkout($host, $court, '2026-11-03 18:00', '2026-11-03 19:00', 100000)->assertCreated()->json('reservation.code');
        $url = "/api/reservations/{$code}/split";

        config(['features.pilot_organizations' => [(string) ($court->venue->organization_id + 1)]]);
        $this->actingAs($host)->getJson($url)->assertNotFound()->assertJsonPath('code', 'CAPABILITY_DISABLED');

        config(['features.pilot_organizations' => ['*']]);
        Reservation::where('code', $code)->update(['status' => 'expired']);
        $this->command($host, $url, ['shares' => [['amount' => 50000], ['amount' => 50000]]])->assertStatus(409);
        $this->assertSame(0, SplitGroup::count());
    }
}
