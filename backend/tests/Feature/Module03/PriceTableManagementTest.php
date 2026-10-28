<?php

namespace Tests\Feature\Module03;

use App\Models\Court;
use App\Models\PriceTable;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** Module 03 price tables: who manages them, what a table holds, its list, detail and name (FR-53, FR-54, FR-56, UC-3.1..3.3, UC-3.5). */
class PriceTableManagementTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    private Venue $venue;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->openVenue();
        $this->owner = $this->ownerOf($this->venue->organization);
    }

    public function test_fr_54_br_51_br_54_owner_creates_a_normal_table_with_adjacent_intervals(): void
    {
        $this->create([
            'name' => 'Giờ thường',
            'kind' => 'normal',
            'effective_at' => '2026-10-25T00:00:00+07:00',
            'intervals' => [
                ['weekdays' => [1, 2, 3, 4, 5], 'start' => '10:00', 'end' => '16:00', 'price_per_hour' => 100000],
                ['weekdays' => [5, 4, 3, 2, 1, 1], 'start' => '16:00', 'end' => '22:00', 'price_per_hour' => 140000],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Giờ thường')
            ->assertJsonPath('data.kind', 'normal')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.links', [])
            ->assertJsonPath('data.versions.0.effective_at', '2026-10-24T17:00:00+00:00')
            ->assertJsonPath('data.versions.0.intervals', [
                ['weekdays' => [1, 2, 3, 4, 5], 'dates' => null, 'start' => '10:00', 'end' => '16:00', 'price_per_hour' => 100000],
                ['weekdays' => [1, 2, 3, 4, 5], 'dates' => null, 'start' => '16:00', 'end' => '22:00', 'price_per_hour' => 140000],
            ]);
    }

    public function test_fr_54_holiday_table_is_set_by_dates_until_midnight(): void
    {
        $this->create([
            'name' => 'Ngày lễ 2027',
            'kind' => 'holiday',
            'effective_at' => now()->toIso8601String(),
            'intervals' => [['dates' => ['2027-04-30', '2027-01-01'], 'start' => '06:00', 'end' => '23:59', 'price_per_hour' => 180000]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'holiday')
            ->assertJsonPath('data.versions.0.intervals', [
                ['weekdays' => null, 'dates' => ['2027-01-01', '2027-04-30'], 'start' => '06:00', 'end' => '23:59', 'price_per_hour' => 180000],
            ]);

        $this->assertDatabaseHas('price_table_intervals', ['start_minute' => 360, 'end_minute' => 1440]);
    }

    public function test_br_52_fr_54_overlapping_intervals_on_a_shared_day_are_rejected_naming_the_overlap(): void
    {
        $this->create($this->normalTable([
            ['weekdays' => [1, 2], 'start' => '10:00', 'end' => '16:00', 'price_per_hour' => 100000],
            ['weekdays' => [1, 3], 'start' => '15:00', 'end' => '18:00', 'price_per_hour' => 120000],
            // Same hours on a day the first does not use: no overlap.
            ['weekdays' => [3], 'start' => '10:00', 'end' => '15:00', 'price_per_hour' => 120000],
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['intervals.1' => ['Khoảng thứ 2 (15:00–18:00) chồng lấn khoảng thứ 1 (10:00–16:00) vào Thứ Hai trong 15:00–16:00.']]);

        $this->assertDatabaseCount('price_tables', 0);
    }

    public function test_br_51_price_must_be_a_whole_vnd_above_zero(): void
    {
        foreach ([0, -100000, 100000.5, 'abc'] as $price) {
            $this->create($this->normalTable([['weekdays' => [1], 'start' => '06:00', 'end' => '12:00', 'price_per_hour' => $price]]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['intervals.0.price_per_hour' => 'Đơn giá của khoảng thứ 1 phải là số nguyên VND lớn hơn 0.']);
        }

        $this->assertDatabaseCount('price_tables', 0);
    }

    public function test_br_51_name_kind_days_intervals_and_effective_time_are_required(): void
    {
        $this->create([])->assertUnprocessable()->assertJsonPath('errors', [
            'name' => ['Vui lòng nhập tên bảng giá.'],
            'kind' => ['Vui lòng chọn loại bảng giá.'],
            'effective_at' => ['Vui lòng chọn thời điểm hiệu lực.'],
            'intervals' => ['Bảng giá cần ít nhất một khoảng giờ.'],
        ]);

        $this->create($this->normalTable([
            ['start' => '06:00', 'end' => '12:00', 'price_per_hour' => 100000],
            ['weekdays' => [2], 'start' => '12:00', 'end' => '10:00', 'price_per_hour' => 100000],
        ]))->assertUnprocessable()->assertJsonPath('errors', [
            'intervals.0.weekdays' => ['Khoảng thứ 1 cần ít nhất một thứ áp dụng.'],
            'intervals.1.end' => ['Giờ kết thúc của khoảng thứ 2 phải sau giờ bắt đầu.'],
        ]);

        // A holiday table names dates, not weekdays.
        $this->create(['name' => 'Lễ', 'kind' => 'holiday', 'effective_at' => now()->toIso8601String(), 'intervals' => [
            ['weekdays' => [1], 'start' => '06:00', 'end' => '12:00', 'price_per_hour' => 180000],
        ]])->assertUnprocessable()->assertJsonPath('errors', ['intervals.0.dates' => ['Khoảng thứ 1 cần ít nhất một ngày áp dụng.']]);

        $this->assertDatabaseCount('price_tables', 0);
    }

    public function test_fr_53_uc_3_1_venue_without_tables_lists_none(): void
    {
        $this->actingAs($this->owner)->getJson("/api/admin/venues/{$this->venue->id}/price-tables")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_fr_53_uc_3_1_uc_3_2_list_and_detail_show_intervals_effective_time_and_courts_using_the_table(): void
    {
        [$first, $second, $ended] = [$this->activeCourt($this->venue), $this->activeCourt($this->venue), $this->activeCourt($this->venue)];
        $table = $this->createPriceTable($first, PriceTable::KIND_NORMAL, [['06:00', '16:00', 100000, [1, 2, 3]], ['16:00', '23:59', 140000]]);
        $this->linkPriceTable($table, $first);
        $this->linkPriceTable($table, $second, now()->addDays(3));
        $this->linkPriceTable($table, $ended, now()->subDays(3), now()->subDay());
        $links = [
            ['court_id' => $first->id, 'court_name' => $first->code, 'starts_at' => '2026-10-18T01:00:00+00:00', 'ends_at' => null],
            ['court_id' => $second->id, 'court_name' => $second->code, 'starts_at' => '2026-10-22T01:00:00+00:00', 'ends_at' => null],
        ];

        $this->actingAs($this->owner)->getJson("/api/admin/venues/{$this->venue->id}/price-tables")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Bảng giá normal')
            ->assertJsonPath('data.0.links', $links);

        $this->actingAs($this->owner)->getJson("/api/admin/price-tables/{$table->id}")
            ->assertOk()
            ->assertJsonPath('data.links', $links)
            ->assertJsonPath('data.versions.0.state', 'active')
            ->assertJsonPath('data.versions.0.effective_at', '2026-10-18T01:00:00+00:00')
            ->assertJsonPath('data.versions.0.intervals.0', ['weekdays' => [1, 2, 3], 'dates' => null, 'start' => '06:00', 'end' => '16:00', 'price_per_hour' => 100000]);
    }

    public function test_fr_56_uc_3_5_owner_renames_a_table_in_use_but_not_a_retired_one(): void
    {
        $table = $this->priceCourt($this->activeCourt($this->venue));

        $this->actingAs($this->owner)->patchJson("/api/admin/price-tables/{$table->id}", ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Vui lòng nhập tên bảng giá.']);
        $this->actingAs($this->owner)->patchJson("/api/admin/price-tables/{$table->id}", ['name' => 'Giá mùa hè'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Giá mùa hè');
        $this->assertSame(1, $table->versions()->count());

        $this->actingAs($this->owner)->postJson("/api/admin/price-tables/{$table->id}/retire")->assertOk();
        $this->actingAs($this->owner)->patchJson("/api/admin/price-tables/{$table->id}", ['name' => 'Giá cũ'])
            ->assertUnprocessable()->assertJsonValidationErrors(['price_table']);
    }

    public function test_br_49_staff_and_another_organizations_owner_cannot_read_or_change_prices(): void
    {
        $court = $this->activeCourt($this->venue);
        $table = $this->priceCourt($court);
        $other = $this->ownerOf($this->openVenue()->organization);
        $body = $this->normalTable([['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'start' => '00:00', 'end' => '23:59', 'price_per_hour' => 90000]]);

        foreach ([$this->staffOf($this->venue), $other] as $user) {
            $this->actingAs($user)->getJson("/api/admin/venues/{$this->venue->id}/price-tables")->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/venues/{$this->venue->id}/price-tables", $body)->assertForbidden();
            $this->actingAs($user)->getJson("/api/admin/price-tables/{$table->id}")->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/price-tables/{$table->id}/versions", ['intervals' => $body['intervals']])->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/price-tables/{$table->id}/links", ['court_ids' => [$court->id]])->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/price-tables/{$table->id}/retire")->assertForbidden();
            $this->actingAs($user)->patchJson("/api/admin/price-tables/{$table->id}", ['name' => 'Đổi tên'])->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/courts/{$court->id}/price-table-links/end", ['kind' => 'normal'])->assertForbidden();
            $this->actingAs($user)->getJson("/api/admin/courts/{$court->id}/price-coverage")->assertForbidden();
        }

        $this->assertSame(1, PriceTable::count());
        $this->assertSame(1, $table->versions()->count());
        $this->assertSame(PriceTable::STATUS_ACTIVE, $table->fresh()->status);
        $this->assertSame(1, Court::find($court->id)->priceTableLinks()->whereNull('ends_at')->count());
    }

    /** @param  array<string, mixed>  $body */
    private function create(array $body): TestResponse
    {
        return $this->actingAs($this->owner)->postJson("/api/admin/venues/{$this->venue->id}/price-tables", $body);
    }

    /**
     * @param  list<array<string, mixed>>  $intervals
     * @return array<string, mixed>
     */
    private function normalTable(array $intervals): array
    {
        return ['name' => 'Giờ thường', 'kind' => 'normal', 'effective_at' => now()->toIso8601String(), 'intervals' => $intervals];
    }
}
