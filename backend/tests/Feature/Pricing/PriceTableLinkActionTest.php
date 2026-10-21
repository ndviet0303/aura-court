<?php

namespace Tests\Feature\Pricing;

use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\User;
use App\Models\Venue;
use App\Services\Pricing\RentalPriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\PricesCourts;
use Tests\Concerns\PricingRoles;
use Tests\TestCase;

/** Applying, replacing and ending the price table links of courts, and retiring tables (design D2, D3, D25). */
class PriceTableLinkActionTest extends TestCase
{
    use PricesCourts;
    use PricingRoles;
    use RefreshDatabase;

    private const SWITCH_AT = '2026-10-25T00:00:00+07:00';

    private const SWITCH_AT_UTC = '2026-10-24T17:00:00+00:00';

    private Venue $venue;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        // The venue has no schedule, so it opens all day and a table must price every minute.
        $this->venue = $this->pricingVenue();
        $this->owner = $this->ownerOf($this->venue->organization);
    }

    public function test_owner_applies_a_table_to_courts_from_the_switch_time(): void
    {
        [$first, $second] = [$this->court('Sân 1'), $this->court('Sân 2')];
        $old = $this->priceCourt([$first, $second], 100000);
        $new = $this->fullDayTable(150000);
        $version = Cache::get('courts_version');

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$new->id}/links", ['court_ids' => [$first->id, $second->id], 'starts_at' => self::SWITCH_AT])
            ->assertOk()
            ->assertJsonPath('data.id', $new->id)
            ->assertJsonPath('data.links', [
                ['court_id' => $first->id, 'court_name' => 'Sân 1', 'starts_at' => self::SWITCH_AT_UTC, 'ends_at' => null],
                ['court_id' => $second->id, 'court_name' => 'Sân 2', 'starts_at' => self::SWITCH_AT_UTC, 'ends_at' => null],
            ]);

        foreach ([$first, $second] as $court) {
            $this->assertSame([[$old->id, '2026-10-18T01:00:00+00:00', self::SWITCH_AT_UTC], [$new->id, self::SWITCH_AT_UTC, null]], $this->links($court));
            $this->assertSame(100000, $this->rental($court, '2026-10-24 23:00'));
            $this->assertSame(150000, $this->rental($court, '2026-10-25 00:00'));
        }
        $this->assertSame($version + 1, Cache::get('courts_version'));
    }

    public function test_applying_a_table_before_a_scheduled_one_removes_it(): void
    {
        $court = $this->court('Sân 1');
        $current = $this->createPriceTable($court, PriceTable::KIND_NORMAL, [['00:00', '23:59', 100000]]);
        $scheduled = $this->fullDayTable(120000);
        $this->linkPriceTable($current, $court, null, CarbonImmutable::parse('2026-10-31', 'Asia/Ho_Chi_Minh'));
        $this->linkPriceTable($scheduled, $court, CarbonImmutable::parse('2026-10-31', 'Asia/Ho_Chi_Minh'));
        $replacement = $this->fullDayTable(130000);

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$replacement->id}/links", ['court_ids' => [$court->id], 'starts_at' => self::SWITCH_AT])
            ->assertOk();

        $this->assertSame([[$current->id, '2026-10-18T01:00:00+00:00', self::SWITCH_AT_UTC], [$replacement->id, self::SWITCH_AT_UTC, null]], $this->links($court));
        $this->assertSame(130000, $this->rental($court, '2026-10-31 10:00'));
    }

    public function test_ending_the_normal_link_that_leaves_a_gap_changes_nothing(): void
    {
        $court = $this->court('Sân 1');
        $table = $this->priceCourt($court, 100000);
        $version = Cache::get('courts_version');

        $response = $this->actingAs($this->owner)->postJson("/api/admin/courts/{$court->id}/price-table-links/end", ['kind' => 'normal', 'ends_at' => self::SWITCH_AT])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRICE_COVERAGE_INCOMPLETE')
            ->assertJsonPath('message', 'Bảng giá chưa phủ kín giờ mở của sân. Vui lòng bổ sung giá cho các khung giờ còn thiếu.')
            ->assertJsonPath('missing_items.0', ['court_id' => $court->id, 'court_name' => 'Sân 1', 'weekday' => 1, 'date' => null, 'start' => '00:00', 'end' => '23:59']);

        $this->assertCount(7, $response->json('missing_items'));
        $this->assertSame([[$table->id, '2026-10-18T01:00:00+00:00', null]], $this->links($court));
        $this->assertSame($version, Cache::get('courts_version'));
    }

    public function test_ending_the_holiday_link_also_removes_later_holiday_links(): void
    {
        $court = $this->court('Sân 1');
        $normal = $this->priceCourt($court, 100000);
        $holiday = $this->createPriceTable($court, PriceTable::KIND_HOLIDAY, [['00:00', '23:59', 180000, ['2026-12-31']]]);
        $nextHoliday = $this->createPriceTable($court, PriceTable::KIND_HOLIDAY, [['00:00', '23:59', 200000, ['2027-01-01']]]);
        $this->linkPriceTable($holiday, $court, null, CarbonImmutable::parse('2026-11-01', 'Asia/Ho_Chi_Minh'));
        $this->linkPriceTable($nextHoliday, $court, CarbonImmutable::parse('2026-11-01', 'Asia/Ho_Chi_Minh'));
        $version = Cache::get('courts_version');

        $this->actingAs($this->owner)->postJson("/api/admin/courts/{$court->id}/price-table-links/end", ['kind' => 'holiday', 'ends_at' => self::SWITCH_AT])
            ->assertOk()
            ->assertExactJson(['data' => ['court_id' => $court->id, 'kind' => 'holiday', 'ends_at' => self::SWITCH_AT_UTC]]);

        $this->assertSame([[$normal->id, '2026-10-18T01:00:00+00:00', null], [$holiday->id, '2026-10-18T01:00:00+00:00', self::SWITCH_AT_UTC]], $this->links($court));
        $this->assertSame(100000, $this->rental($court, '2027-01-01 10:00'));
        $this->assertSame($version + 1, Cache::get('courts_version'));
    }

    public function test_court_on_a_retired_table_keeps_its_price_and_the_table_takes_no_new_court(): void
    {
        [$court, $other] = [$this->court('Sân 1'), $this->court('Sân 2')];
        $table = $this->priceCourt($court, 100000);
        $this->priceCourt($other, 90000);
        $version = Cache::get('courts_version');

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/retire")
            ->assertOk()
            ->assertJsonPath('data.status', 'retired')
            ->assertJsonPath('data.links.0.court_id', $court->id);

        $this->assertSame(100000, $this->rental($court, '2026-10-26 10:00'));
        $this->assertSame($version, Cache::get('courts_version'));

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/links", ['court_ids' => [$other->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['price_table' => ['Bảng giá đã ngừng sử dụng nên không áp được cho sân.']]);
        $this->assertCount(1, $this->links($other));
    }

    public function test_courts_outside_the_tables_venue_are_rejected(): void
    {
        $court = $this->court('Sân 1');
        $this->priceCourt($court, 100000);
        $elsewhere = Court::create(['venue_id' => $this->pricingVenue('Sân khác')->id, 'name' => 'Sân 9', 'is_active' => true]);
        $table = $this->fullDayTable(150000);

        foreach ([[$court->id, $elsewhere->id], [$court->id, 999999]] as $courtIds) {
            $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/links", ['court_ids' => $courtIds])
                ->assertUnprocessable()
                ->assertJsonPath('errors', ['court_ids' => ['Sân được chọn không thuộc cơ sở của bảng giá.']]);
        }

        $this->assertCount(1, $this->links($court));
        $this->assertSame([], $this->links($elsewhere));
    }

    public function test_switch_time_in_the_past_is_rejected(): void
    {
        $court = $this->court('Sân 1');
        $this->priceCourt($court, 100000);
        $table = $this->fullDayTable(150000);
        $past = now()->subMinute()->toIso8601String();

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/links", ['court_ids' => [$court->id], 'starts_at' => $past])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['starts_at' => ['Thời điểm chuyển bảng giá không được ở quá khứ.']]);
        $this->actingAs($this->owner)->postJson("/api/admin/courts/{$court->id}/price-table-links/end", ['kind' => 'holiday', 'ends_at' => $past])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['ends_at' => ['Thời điểm chuyển bảng giá không được ở quá khứ.']]);

        $this->assertCount(1, $this->links($court));
    }

    public function test_applying_a_table_that_leaves_a_gap_changes_nothing(): void
    {
        $court = $this->court('Sân 1');
        $table = $this->priceCourt($court, 100000);
        $short = $this->createPriceTable($court, PriceTable::KIND_NORMAL, [['06:00', '22:00', 150000]]);
        $version = Cache::get('courts_version');

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$short->id}/links", ['court_ids' => [$court->id]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PRICE_COVERAGE_INCOMPLETE')
            ->assertJsonPath('missing_items.0', ['court_id' => $court->id, 'court_name' => 'Sân 1', 'weekday' => 1, 'date' => null, 'start' => '00:00', 'end' => '06:00']);

        $this->assertSame([[$table->id, '2026-10-18T01:00:00+00:00', null]], $this->links($court));
        $this->assertSame($version, Cache::get('courts_version'));
    }

    public function test_staff_and_another_organizations_owner_cannot_change_links(): void
    {
        $court = $this->court('Sân 1');
        $table = $this->priceCourt($court, 100000);
        $new = $this->fullDayTable(150000);
        // The staff member manages this venue day to day, but prices belong to the owner (D25).
        $users = [$this->staffOf($this->venue), $this->ownerOfAnotherOrganization()];
        $version = Cache::get('courts_version');

        foreach ($users as $user) {
            $this->actingAs($user)->postJson("/api/admin/price-tables/{$new->id}/links", ['court_ids' => [$court->id], 'starts_at' => self::SWITCH_AT])
                ->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/courts/{$court->id}/price-table-links/end", ['kind' => 'normal', 'ends_at' => self::SWITCH_AT])
                ->assertForbidden();
        }

        $this->assertSame([[$table->id, '2026-10-18T01:00:00+00:00', null]], $this->links($court));
        $this->assertSame($version, Cache::get('courts_version'));
    }

    private function court(string $name): Court
    {
        return Court::create(['venue_id' => $this->venue->id, 'name' => $name, 'is_active' => true]);
    }

    private function fullDayTable(int $pricePerHour): PriceTable
    {
        $table = PriceTable::create(['venue_id' => $this->venue->id, 'name' => "Giá {$pricePerHour}", 'kind' => PriceTable::KIND_NORMAL, 'status' => PriceTable::STATUS_ACTIVE]);
        $this->publishPriceVersion($table, [['00:00', '23:59', $pricePerHour]], now()->subDay(), now()->subDay());

        return $table;
    }

    /** @return list<array{0: int, 1: string, 2: ?string}> the court's links as [table id, starts_at, ends_at] in UTC */
    private function links(Court $court): array
    {
        return CourtPriceTableLink::where('court_id', $court->id)->orderBy('starts_at')->get()
            ->map(fn (CourtPriceTableLink $link): array => [(int) $link->price_table_id, $link->starts_at->utc()->toIso8601String(), $link->ends_at?->utc()->toIso8601String()])
            ->all();
    }

    /** The rental of one hour from $start, the venue's local time. */
    private function rental(Court $court, string $start): int
    {
        $from = CarbonImmutable::parse($start, 'Asia/Ho_Chi_Minh');

        return app(RentalPriceCalculator::class)->quote($court, $from, $from->addHour(), null)->rental;
    }
}
