<?php

namespace Tests\Feature;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

class HarnessSmokeTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    public function test_a_bookable_court_is_eligible_and_priced(): void
    {
        $court = $this->bookableCourt(120000);

        $this->assertTrue(app(VerifyEffectiveBookingEligibility::class)->execute($court)['eligible']);
        $date = now('Asia/Ho_Chi_Minh')->addDay()->toDateString();
        $slots = $this->getJson("/api/availability?court_id={$court->id}&date={$date}")->assertOk()->json();
        $this->assertCount(32, $slots); // 06:00–22:00 on the 30-minute grid
        $this->assertSame(60000, $slots[0]['price']);
    }
}
