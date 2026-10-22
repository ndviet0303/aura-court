<?php

namespace Tests\Feature\Module03;

use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\User;
use App\Models\Venue;
use App\Services\Pricing\RentalPriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/**
 * Applying tables to courts, coverage of the bookable hours, replacing and retiring tables (FR-55, FR-57, BR-50,
 * BR-52, BR-53, BR-57; UC-3.4, UC-3.6). The venue opens 06:00–22:00 every day.
 */
class PriceTableLinkTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    private const SWITCH_AT = '2026-10-25T00:00:00+07:00';

    private const SWITCH_AT_UTC = '2026-10-24T17:00:00+00:00';

    private Venue $venue;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->openVenue();
        $this->owner = $this->ownerOf($this->venue->organization);
    }

    public function test_fr_55_br_50_one_table_applies_to_several_courts_of_its_venue_from_the_switch_time(): void
    {
        [$first, $second] = [$this->activeCourt($this->venue), $this->activeCourt($this->venue)];
        $old = $this->priceCourt([$first, $second], 100000);
        $new = $this->table(150000);

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$new->id}/links", ['court_ids' => [$first->id, $second->id], 'starts_at' => self::SWITCH_AT])
            ->assertOk()
            ->assertJsonPath('data.links.0.court_id', $first->id)
            ->assertJsonPath('data.links.1.court_id', $second->id);

        foreach ([$first, $second] as $court) {
            $this->assertSame([[$old->id, '2026-10-18T01:00:00+00:00', self::SWITCH_AT_UTC], [$new->id, self::SWITCH_AT_UTC, null]], $this->links($court));
            $this->assertSame(100000, $this->rental($court, '2026-10-24 21:00'));
            $this->assertSame(150000, $this->rental($court, '2026-10-25 06:00'));
        }
    }

    public function test_br_50_fr_55_courts_of_another_venue_are_refused(): void
    {
        $court = $this->activeCourt($this->venue);
        $this->priceCourt($court);
        $elsewhere = $this->activeCourt($this->openVenue(['organization_id' => $this->venue->organization_id]));
        $table = $this->table(150000);

        foreach ([[$court->id, $elsewhere->id], [$court->id, 999999]] as $courtIds) {
            $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/links", ['court_ids' => $courtIds])
                ->assertUnprocessable()
                ->assertJsonPath('errors', ['court_ids' => ['Sân được chọn không thuộc cơ sở của bảng giá.']]);
        }

        $this->assertCount(1, $this->links($court));
        $this->assertSame([], $this->links($elsewhere));
    }

    public function test_br_52_fr_55_a_table_that_leaves_open_hours_unpriced_is_refused_naming_the_gap(): void
    {
        $court = $this->activeCourt($this->venue);
        $table = $this->priceCourt($court);
        $short = $this->createPriceTable($court, PriceTable::KIND_NORMAL, [['08:00', '22:00', 150000]]);

        $response = $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$short->id}/links", ['court_ids' => [$court->id]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRICE_COVERAGE_INCOMPLETE')
            ->assertJsonPath('missing_items.0', ['court_id' => $court->id, 'court_name' => $court->code, 'weekday' => 1, 'date' => null, 'start' => '06:00', 'end' => '08:00']);

        $this->assertCount(7, $response->json('missing_items'));
        $this->assertSame([[$table->id, '2026-10-18T01:00:00+00:00', null]], $this->links($court));
    }

    public function test_br_53_a_holiday_table_must_cover_the_open_hours_of_its_dates_without_falling_back(): void
    {
        $court = $this->activeCourt($this->venue);
        $this->priceCourt($court, 100000);
        $holiday = $this->createPriceTable($court, PriceTable::KIND_HOLIDAY, [['06:00', '12:00', 180000, ['2026-12-31']]]);

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$holiday->id}/links", ['court_ids' => [$court->id]])
            ->assertUnprocessable()
            ->assertJsonPath('missing_items', [['court_id' => $court->id, 'court_name' => $court->code, 'weekday' => null, 'date' => '2026-12-31', 'start' => '12:00', 'end' => '22:00']]);
        $this->assertSame(0, CourtPriceTableLink::where('kind', 'holiday')->count());

        // Covering the whole open day is accepted, and the holiday price wins on that date only.
        $full = $this->createPriceTable($court, PriceTable::KIND_HOLIDAY, [['06:00', '22:00', 180000, ['2026-12-31']]]);
        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$full->id}/links", ['court_ids' => [$court->id]])->assertOk();
        $this->assertSame(180000, $this->rental($court, '2026-12-31 10:00'));
        $this->assertSame(100000, $this->rental($court, '2026-12-30 10:00'));
    }

    public function test_br_52_publishing_a_version_that_leaves_a_gap_on_linked_courts_publishes_nothing(): void
    {
        $court = $this->activeCourt($this->venue);
        $table = $this->priceCourt($court, 100000);

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/versions", ['intervals' => [
            ['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'start' => '07:00', 'end' => '22:00', 'price_per_hour' => 120000],
        ]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRICE_COVERAGE_INCOMPLETE');

        $this->assertSame(1, $table->versions()->count());
    }

    public function test_br_52_coverage_reports_a_courts_gaps(): void
    {
        $court = $this->activeCourt($this->venue);
        $this->priceCourtWith($court, [['06:00', '21:00', 100000]]);

        $this->actingAs($this->owner)->getJson("/api/admin/courts/{$court->id}/price-coverage")
            ->assertOk()
            ->assertJsonPath('data.covered', false)
            ->assertJsonPath('data.missing_items.0', ['court_id' => $court->id, 'court_name' => $court->code, 'weekday' => 1, 'date' => null, 'start' => '21:00', 'end' => '22:00']);
    }

    public function test_fr_57_br_57_retired_table_keeps_its_courts_priced_and_visible_but_takes_no_new_court(): void
    {
        [$court, $other] = [$this->activeCourt($this->venue), $this->activeCourt($this->venue)];
        $table = $this->priceCourt($court, 100000);
        $this->priceCourt($other, 90000);

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/retire")
            ->assertOk()
            ->assertJsonPath('data.status', 'retired')
            ->assertJsonPath('data.links.0.court_id', $court->id);

        $this->assertSame(100000, $this->rental($court, '2026-10-26 10:00'));
        $this->actingAs($this->owner)->getJson("/api/admin/venues/{$this->venue->id}/price-tables")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'retired');

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/links", ['court_ids' => [$other->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['price_table' => ['Bảng giá đã ngừng sử dụng nên không áp được cho sân.']]);
        $this->assertCount(1, $this->links($other));
    }

    public function test_fr_57_br_57_ending_a_link_that_leaves_a_gap_is_refused_and_a_replacement_switches_cleanly(): void
    {
        $court = $this->activeCourt($this->venue);
        $table = $this->priceCourt($court, 100000);

        $this->actingAs($this->owner)->postJson("/api/admin/courts/{$court->id}/price-table-links/end", ['kind' => 'normal', 'ends_at' => self::SWITCH_AT])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRICE_COVERAGE_INCOMPLETE');
        $this->assertSame([[$table->id, '2026-10-18T01:00:00+00:00', null]], $this->links($court));

        // The replacement takes over at the switch time with no gap or overlap.
        $replacement = $this->table(130000);
        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$replacement->id}/links", ['court_ids' => [$court->id], 'starts_at' => self::SWITCH_AT])->assertOk();
        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/retire")->assertOk();
        $this->assertSame([[$table->id, '2026-10-18T01:00:00+00:00', self::SWITCH_AT_UTC], [$replacement->id, self::SWITCH_AT_UTC, null]], $this->links($court));
        $this->assertSame(1, PriceTable::whereKey($table->id)->where('status', 'retired')->count());
    }

    public function test_br_57_an_inactive_court_may_keep_a_gap(): void
    {
        $court = $this->activeCourt($this->venue, ['status' => Court::STATUS_INACTIVE]);
        $table = $this->createPriceTable($court, PriceTable::KIND_NORMAL, [['08:00', '12:00', 100000]]);

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/links", ['court_ids' => [$court->id]])->assertOk();
        // A new version of a table linked only to inactive courts is not held back by their gaps either.
        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/versions", ['intervals' => [
            ['weekdays' => [1], 'start' => '09:00', 'end' => '10:00', 'price_per_hour' => 120000],
        ]])->assertCreated();
    }

    public function test_br_57_a_switch_time_in_the_past_is_refused(): void
    {
        $court = $this->activeCourt($this->venue);
        $this->priceCourt($court, 100000);
        $table = $this->table(150000);

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/links", ['court_ids' => [$court->id], 'starts_at' => now()->subMinute()->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['starts_at' => ['Thời điểm chuyển bảng giá không được ở quá khứ.']]);
        $this->assertCount(1, $this->links($court));
    }

    /** An unlinked normal table of the venue pricing every minute at $pricePerHour. */
    private function table(int $pricePerHour): PriceTable
    {
        $table = PriceTable::create(['venue_id' => $this->venue->id, 'name' => "Giá {$pricePerHour}", 'kind' => PriceTable::KIND_NORMAL, 'status' => PriceTable::STATUS_ACTIVE]);
        $this->publishPriceVersion($table, [['00:00', '23:59', $pricePerHour]], now()->subDay(), now()->subDay());

        return $table;
    }

    /** @return list<array{0: int, 1: string, 2: ?string}> [table id, starts_at, ends_at] in UTC */
    private function links(Court $court): array
    {
        return CourtPriceTableLink::where('court_id', $court->id)->orderBy('starts_at')->get()
            ->map(fn (CourtPriceTableLink $link): array => [(int) $link->price_table_id, $link->starts_at->utc()->toIso8601String(), $link->ends_at?->utc()->toIso8601String()])
            ->all();
    }

    private function rental(Court $court, string $start): int
    {
        $from = CarbonImmutable::parse($start, 'Asia/Ho_Chi_Minh');

        return app(RentalPriceCalculator::class)->quote($court, $from, $from->addHour(), null)->rental;
    }
}
