<?php

namespace Tests\Feature\Venue;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricedCourtVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Priced Org', 'slug' => 'priced-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $this->venue = Venue::create(['organization_id' => $org->id, 'name' => 'Priced Venue', 'slug' => 'priced-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);
    }

    private function court(string $name): Court
    {
        return Court::create(['venue_id' => $this->venue->id, 'sport_type' => 'badminton', 'name' => $name, 'slug' => 'court-'.uniqid(), 'is_active' => true]);
    }

    private function sportRule(): PriceRule
    {
        return PriceRule::create(['organization_id' => $this->venue->organization_id, 'venue_id' => $this->venue->id, 'sport_type' => 'badminton', 'type' => 'base', 'price' => 100000, 'is_active' => true]);
    }

    public function test_court_without_rules_is_hidden_and_not_bookable(): void
    {
        $unpriced = $this->court('Unpriced Court');
        $slotPriced = $this->court('Slot Priced Court');
        CourtPriceSlot::create(['court_id' => $slotPriced->id, 'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 90000, 'is_active' => true]);
        PriceRule::create(['organization_id' => $this->venue->organization_id, 'venue_id' => $this->venue->id, 'sport_type' => 'tennis', 'type' => 'base', 'price' => 100000, 'is_active' => true]);
        PriceRule::create(['organization_id' => $this->venue->organization_id, 'court_id' => $unpriced->id, 'type' => 'base', 'price' => 100000, 'is_active' => false]);

        $ids = collect($this->getJson('/api/courts')->assertOk()->json())->pluck('id')->all();
        $this->assertSame([$slotPriced->id], $ids);

        $this->getJson("/api/courts/{$unpriced->slug}")->assertNotFound();
        $this->getJson("/api/availability?court_id={$unpriced->id}&date=".now()->addDay()->toDateString())->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->withHeader('Idempotency-Key', uniqid('unpriced-'))
            ->postJson('/api/reservations', ['court_id' => $unpriced->id, 'slots' => [now()->addDay()->setTime(10, 0)->toIso8601String()]])
            ->assertNotFound();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_court_priced_only_by_an_organization_rule_stays_public(): void
    {
        $court = $this->court('Org Priced Court');
        PriceRule::create(['organization_id' => $this->venue->organization_id, 'type' => 'base', 'price' => 120000, 'is_active' => true]);

        $this->getJson('/api/courts')->assertOk()->assertJsonPath('0.id', $court->id);
        $this->getJson("/api/courts/{$court->slug}")->assertOk()->assertJsonPath('id', $court->id);
    }

    public function test_new_price_rule_refreshes_the_cached_public_list(): void
    {
        $court = $this->court('Late Priced Court');
        $this->getJson('/api/courts')->assertOk()->assertJsonCount(0);

        $this->sportRule();

        $this->getJson('/api/courts')->assertOk()->assertJsonPath('0.id', $court->id);
    }

    public function test_public_list_query_count_does_not_grow_with_courts(): void
    {
        $this->sportRule();
        // Returns [query count, listed courts] for an uncached request.
        $measure = function (): array {
            Cache::flush();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $listed = count($this->getJson('/api/courts')->assertOk()->json());
            DB::disableQueryLog();

            return [count(DB::getQueryLog()), $listed];
        };

        $this->court('Court 1');
        $this->court('Court 2');
        [$fewQueries, $fewListed] = $measure();
        foreach (range(3, 10) as $number) {
            $court = $this->court("Court {$number}");
            CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 90000, 'is_active' => true]);
        }
        [$manyQueries, $manyListed] = $measure();

        $this->assertSame([2, 10], [$fewListed, $manyListed]);
        $this->assertSame($fewQueries, $manyQueries);
    }
}
