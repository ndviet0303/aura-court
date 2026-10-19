<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Court;
use App\Models\Organization;
use App\Models\PriceRule;
use App\Models\Venue;
use App\Services\Reservation\PriceQuoteService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RemoveSportZonesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_sport_slot_length_and_zone_rules_move_without_changing_prices(): void
    {
        [$venue, $badminton, $tennis] = $this->venueWithCourts();
        PriceRule::create(['organization_id' => $venue->organization_id, 'venue_id' => $venue->id, 'type' => 'base', 'price' => 120000, 'version' => 1]);
        $this->rollBackToZones();
        $zones = DB::table('sport_zones')->pluck('id', 'sport_type');
        DB::table('sport_zones')->where('id', $zones['tennis'])->update(['slot_duration' => 90]);
        $zoneRuleId = $this->insertRule(['organization_id' => $venue->organization_id, 'sport_zone_id' => $zones['badminton'], 'price' => 140000]);

        $this->migration('000001')->up();
        $this->migration('000002')->up();

        $this->assertFalse(Schema::hasTable('sport_zones'));
        $this->assertFalse(Schema::hasColumn('courts', 'sport_zone_id'));
        $this->assertFalse(Schema::hasColumn('price_rules', 'sport_zone_id'));
        $this->assertSame(['badminton', 60], [$badminton->fresh()->sport_type, $badminton->fresh()->slot_duration]);
        $this->assertSame(['tennis', 90], [$tennis->fresh()->sport_type, $tennis->fresh()->slot_duration]);
        $this->assertSame([$venue->id, 'badminton'], [PriceRule::findOrFail($zoneRuleId)->venue_id, PriceRule::findOrFail($zoneRuleId)->sport_type]);
        // The zone rule still prices only its own courts; the tennis court keeps the venue price.
        $this->assertSame(140000, $this->unitPrice($badminton));
        $this->assertSame(120000, $this->unitPrice($tennis));
    }

    public function test_drop_refuses_when_a_zone_rule_would_reach_another_zone_and_keeps_the_zones(): void
    {
        [$venue, $badminton] = $this->venueWithCourts();
        $second = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Badminton 2', 'is_active' => true]);
        $this->rollBackToZones(3);
        // Two badminton zones at one venue: the zone rule prices only the first zone's court today.
        $zone = DB::table('sport_zones')->where('sport_type', 'badminton')->value('id');
        $otherZone = DB::table('sport_zones')->insertGetId(['venue_id' => $venue->id, 'name' => json_encode(['vi' => 'Khu 2']), 'sport_type' => 'badminton', 'slot_duration' => 60, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('courts')->where('id', $second->id)->update(['sport_zone_id' => $otherZone]);
        $ruleId = $this->insertRule(['organization_id' => $venue->organization_id, 'sport_zone_id' => $zone, 'price' => 140000]);
        $this->migration('000001')->up();

        try {
            $this->migration('000002')->up();
            $this->fail('The drop migration should refuse a zone rule that would start pricing another zone.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("priced zone courts differ from venue + sport courts (ids: {$zone})", $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('sport_zones'));
        $this->assertSame($zone, DB::table('price_rules')->where('id', $ruleId)->value('sport_zone_id'));
        $this->assertSame([$zone, $otherZone], [DB::table('courts')->where('id', $badminton->id)->value('sport_zone_id'), DB::table('courts')->where('id', $second->id)->value('sport_zone_id')]);
    }

    public function test_rollback_refuses_to_drop_the_sport_of_a_court_without_a_zone(): void
    {
        [, , $tennis] = $this->venueWithCourts();
        $this->migration('000002')->down();
        DB::table('courts')->where('id', $tennis->id)->update(['sport_zone_id' => null]);

        $this->expectExceptionMessage("courts {$tennis->id}");
        $this->migration('000001')->down();
    }

    /** @return array{0: Venue, 1: Court, 2: Court} */
    private function venueWithCourts(): array
    {
        $org = Organization::create(['name' => 'Zone Org', 'slug' => 'zone-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Zone Venue', 'slug' => 'zone-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);

        return [
            $venue,
            Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Badminton 1', 'is_active' => true]),
            Court::create(['venue_id' => $venue->id, 'sport_type' => 'tennis', 'name' => 'Tennis 1', 'is_active' => true]),
        ];
    }

    /** Rebuilds the zone schema (one zone per venue and sport) the two migrations start from. */
    private function rollBackToZones(int $courts = 2): void
    {
        $this->migration('000002')->down();
        $this->migration('000001')->down();
        $this->assertSame($courts, DB::table('courts')->whereNotNull('sport_zone_id')->count());
    }

    private function insertRule(array $values): int
    {
        return DB::table('price_rules')->insertGetId($values + ['type' => 'base', 'version' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function unitPrice(Court $court): int
    {
        return app(PriceQuoteService::class)->priceSnapshots($court->fresh(), [now()->addDays(2)->setTime(18, 0)])[0]['unit_price'];
    }

    private function migration(string $step): Migration
    {
        $name = $step === '000001' ? 'add_sport_fields_to_courts_and_price_rules' : 'drop_sport_zones';

        return require database_path("migrations/2026_10_20_{$step}_{$name}.php");
    }
}
