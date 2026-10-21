<?php

namespace Tests\Feature\Pricing;

use App\Models\Court;
use App\Models\PlatformSubscription;
use App\Models\PriceTable;
use App\Models\User;
use App\Models\Venue;
use App\Services\Pricing\RentalPriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PricesCourts;
use Tests\Concerns\PricingRoles;
use Tests\TestCase;

/** Creating, listing and reading venue price tables (design D1, D15, D25). */
class PriceTableManagementTest extends TestCase
{
    use PricesCourts;
    use PricingRoles;
    use RefreshDatabase;

    private Venue $venue;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->pricingVenue();
        $this->owner = $this->ownerOf($this->venue->organization);
    }

    public function test_owner_creates_a_normal_table_with_adjacent_intervals(): void
    {
        Cache::put('courts_version', 5);

        $response = $this->create([
            'name' => 'Giờ thường',
            'kind' => 'normal',
            'effective_at' => '2026-10-25T00:00:00+07:00',
            'intervals' => [
                ['weekdays' => [1, 2, 3, 4, 5], 'start' => '10:00', 'end' => '16:00', 'price_per_hour' => 100000],
                ['weekdays' => [5, 4, 3, 2, 1, 1], 'start' => '16:00', 'end' => '22:00', 'price_per_hour' => 140000],
            ],
        ])->assertCreated();

        $table = PriceTable::sole();
        $response->assertExactJson(['data' => [
            'id' => $table->id,
            'venue_id' => $this->venue->id,
            'name' => 'Giờ thường',
            'kind' => 'normal',
            'status' => 'active',
            'versions' => [[
                'id' => $table->versions()->sole()->id,
                'published_at' => '2026-10-19T01:00:00+00:00',
                'effective_at' => '2026-10-24T17:00:00+00:00',
                'intervals' => [
                    ['weekdays' => [1, 2, 3, 4, 5], 'dates' => null, 'start' => '10:00', 'end' => '16:00', 'price_per_hour' => 100000],
                    ['weekdays' => [1, 2, 3, 4, 5], 'dates' => null, 'start' => '16:00', 'end' => '22:00', 'price_per_hour' => 140000],
                ],
            ]],
            'links' => [],
        ]]);
        // A new table has no link, so the cached public court list stays.
        $this->assertSame(5, Cache::get('courts_version'));

        $court = Court::create(['venue_id' => $this->venue->id, 'name' => 'Sân 1', 'is_active' => true]);
        $this->linkPriceTable($table, $court);
        $quote = app(RentalPriceCalculator::class)->quote(
            $court,
            CarbonImmutable::parse('2026-10-26 16:00', 'Asia/Ho_Chi_Minh'),
            CarbonImmutable::parse('2026-10-26 17:00', 'Asia/Ho_Chi_Minh'),
            null,
        );
        $this->assertSame(140000, $quote->rental);
    }

    public function test_overlapping_intervals_are_rejected_naming_the_day_and_the_overlap(): void
    {
        $this->create($this->normalTable([
            ['weekdays' => [1, 2], 'start' => '10:00', 'end' => '16:00', 'price_per_hour' => 100000],
            ['weekdays' => [1, 3], 'start' => '15:00', 'end' => '18:00', 'price_per_hour' => 120000],
            ['weekdays' => [2], 'start' => '16:00', 'end' => '18:00', 'price_per_hour' => 120000],
        ]))
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Khoảng thứ 2 (15:00–18:00) chồng lấn khoảng thứ 1 (10:00–16:00) vào Thứ Hai trong 15:00–16:00.',
                'errors' => ['intervals.1' => ['Khoảng thứ 2 (15:00–18:00) chồng lấn khoảng thứ 1 (10:00–16:00) vào Thứ Hai trong 15:00–16:00.']],
            ]);

        $this->assertDatabaseCount('price_tables', 0);
        $this->assertDatabaseCount('price_table_versions', 0);
    }

    public function test_zero_negative_or_fractional_price_is_rejected_naming_the_interval(): void
    {
        foreach ([0, -100000, 100000.5] as $price) {
            $this->create($this->normalTable([
                ['weekdays' => [1], 'start' => '06:00', 'end' => '12:00', 'price_per_hour' => 100000],
                ['weekdays' => [1], 'start' => '12:00', 'end' => '23:59', 'price_per_hour' => $price],
            ]))
                ->assertUnprocessable()
                ->assertJsonPath('errors', ['intervals.1.price_per_hour' => ['Đơn giá của khoảng thứ 2 phải là số nguyên VND lớn hơn 0.']]);
        }

        $this->assertDatabaseCount('price_tables', 0);
    }

    public function test_missing_fields_and_days_are_rejected_naming_each_problem(): void
    {
        $this->create([])->assertUnprocessable()->assertJsonPath('errors', [
            'name' => ['Vui lòng nhập tên bảng giá.'],
            'kind' => ['Vui lòng chọn loại bảng giá.'],
            'effective_at' => ['Vui lòng chọn thời điểm hiệu lực.'],
            'intervals' => ['Bảng giá cần ít nhất một khoảng giờ.'],
        ]);

        $this->create($this->normalTable([
            ['start' => '06:00', 'end' => '12:00', 'price_per_hour' => 100000],
            ['weekdays' => [], 'start' => '12:00', 'end' => '10:00', 'price_per_hour' => 100000],
        ]))->assertUnprocessable()->assertJsonPath('errors', [
            'intervals.0.weekdays' => ['Khoảng thứ 1 cần ít nhất một thứ áp dụng.'],
            'intervals.1.weekdays' => ['Khoảng thứ 2 cần ít nhất một thứ áp dụng.'],
            'intervals.1.end' => ['Giờ kết thúc của khoảng thứ 2 phải sau giờ bắt đầu.'],
        ]);

        $this->create(['name' => 'Lễ', 'kind' => 'holiday', 'effective_at' => now()->toIso8601String(), 'intervals' => [
            ['weekdays' => [1], 'start' => '06:00', 'end' => '12:00', 'price_per_hour' => 180000],
        ]])->assertUnprocessable()->assertJsonPath('errors', ['intervals.0.dates' => ['Khoảng thứ 1 cần ít nhất một ngày áp dụng.']]);

        $this->assertDatabaseCount('price_tables', 0);
    }

    public function test_holiday_table_prices_its_dates_until_midnight(): void
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

        $this->assertDatabaseHas('price_table_intervals', ['start_minute' => 360, 'end_minute' => 1440, 'price_per_hour' => 180000]);
    }

    public function test_effective_time_in_the_past_takes_effect_at_publication(): void
    {
        $this->create($this->normalTable([['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'start' => '00:00', 'end' => '23:59', 'price_per_hour' => 100000]], now()->subHours(2)->toIso8601String()))
            ->assertCreated()
            ->assertJsonPath('data.versions.0.published_at', '2026-10-19T01:00:00+00:00')
            ->assertJsonPath('data.versions.0.effective_at', '2026-10-19T01:00:00+00:00');
    }

    public function test_expired_business_plan_creates_nothing(): void
    {
        PlatformSubscription::where('organization_id', $this->venue->organization_id)->update(['ends_at' => now()->subDay()]);

        $this->create($this->normalTable([['weekdays' => [1], 'start' => '06:00', 'end' => '22:00', 'price_per_hour' => 100000]]))
            ->assertForbidden()
            ->assertJsonPath('code', 'SUBSCRIPTION_EXPIRED');

        $this->assertDatabaseCount('price_tables', 0);
    }

    public function test_staff_and_another_organizations_owner_are_denied_without_changes(): void
    {
        $table = $this->createPriceTable(Court::create(['venue_id' => $this->venue->id, 'name' => 'Sân 1', 'is_active' => true]), PriceTable::KIND_NORMAL, [['00:00', '23:59', 100000]]);

        foreach ([$this->staffOf($this->venue), $this->ownerOfAnotherOrganization()] as $user) {
            $this->actingAs($user)->getJson("/api/admin/venues/{$this->venue->id}/price-tables")->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/venues/{$this->venue->id}/price-tables", $this->normalTable([
                ['weekdays' => [1], 'start' => '06:00', 'end' => '22:00', 'price_per_hour' => 100000],
            ]))->assertForbidden();
            $this->actingAs($user)->getJson("/api/admin/price-tables/{$table->id}")->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/price-tables/{$table->id}/retire")->assertForbidden();
        }

        $this->assertSame([[$table->id, 'active']], PriceTable::all()->map(fn (PriceTable $row): array => [$row->id, $row->status])->all());
    }

    public function test_venue_without_price_tables_lists_none(): void
    {
        $this->actingAs($this->owner)->getJson("/api/admin/venues/{$this->venue->id}/price-tables")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_list_and_detail_show_the_courts_using_the_table_from_its_links(): void
    {
        $courts = collect(['Sân 1', 'Sân 2', 'Sân 3'])->map(fn (string $name): Court => Court::create(['venue_id' => $this->venue->id, 'name' => $name, 'is_active' => true]));
        $table = $this->createPriceTable($courts[0], PriceTable::KIND_NORMAL, [['06:00', '16:00', 100000, [1, 2, 3]], ['16:00', '23:59', 140000]]);
        $this->linkPriceTable($table, $courts[0]);
        $this->linkPriceTable($table, $courts[1], now()->addDays(3));
        // A link that has ended no longer counts.
        $this->linkPriceTable($table, $courts[2], now()->subDays(3), now()->subDay());
        $links = [
            ['court_id' => $courts[0]->id, 'court_name' => 'Sân 1', 'starts_at' => '2026-10-18T01:00:00+00:00', 'ends_at' => null],
            ['court_id' => $courts[1]->id, 'court_name' => 'Sân 2', 'starts_at' => '2026-10-22T01:00:00+00:00', 'ends_at' => null],
        ];

        $this->actingAs($this->owner)->getJson("/api/admin/venues/{$this->venue->id}/price-tables")
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $table->id, 'venue_id' => $this->venue->id, 'name' => 'Bảng giá normal', 'kind' => 'normal', 'status' => 'active', 'links' => $links,
            ]]]);

        $this->actingAs($this->owner)->getJson("/api/admin/price-tables/{$table->id}")
            ->assertOk()
            ->assertJsonPath('data.links', $links)
            ->assertJsonPath('data.versions.0.effective_at', '2026-10-18T01:00:00+00:00')
            ->assertJsonPath('data.versions.0.intervals', [
                ['weekdays' => [1, 2, 3], 'dates' => null, 'start' => '06:00', 'end' => '16:00', 'price_per_hour' => 100000],
                ['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'dates' => null, 'start' => '16:00', 'end' => '23:59', 'price_per_hour' => 140000],
            ]);
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
    private function normalTable(array $intervals, ?string $effectiveAt = null): array
    {
        return ['name' => 'Giờ thường', 'kind' => 'normal', 'effective_at' => $effectiveAt ?? now()->toIso8601String(), 'intervals' => $intervals];
    }
}
