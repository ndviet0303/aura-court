<?php

namespace Tests\Feature\Module05;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePaymentRequest;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PaysPackages;
use Tests\Concerns\ReadsExcelExports;
use Tests\TestCase;

/** UC-5.17, UC-5.18, FR-99, FR-100, BR-90, BR-114, BR-115, BR-117: fees collected and the admin's Excel exports. */
class PlatformFeeReportTest extends TestCase
{
    use PaysPackages;
    use ReadsExcelExports;
    use RefreshDatabase;

    private Venue $first;

    private Venue $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usesAuraCourtAccount();
        $this->travelTo(Carbon::parse('2026-12-05 10:00', 'Asia/Ho_Chi_Minh'));
        $this->first = Venue::factory()->withoutPackage()->create(['name' => 'Aura Ba Đình']);
        $this->second = Venue::factory()->withoutPackage()->create(['name' => 'Aura Hà Đông']);
        $this->paid($this->first, PackageTerm::Days30, 300000, '2026-11-01 00:00');
        $this->paid($this->first, PackageTerm::Days30, 300000, '2026-11-15 12:00', PackagePaymentRequest::KIND_RENEWAL);
        $this->paid($this->second, PackageTerm::Days90, 800000, '2026-11-30 23:59:59');
        // Outside the month, unpaid, or expired: never fees collected.
        $this->paid($this->second, PackageTerm::Days90, 800000, '2026-12-01 00:00');
        $this->paid($this->first, PackageTerm::Days90, 800000, '2026-10-31 23:59:59');
        PackagePaymentRequest::factory()->create(['venue_id' => $this->first->id, 'amount' => 999000, 'created_at' => Carbon::parse('2026-11-10')]);
        PackagePaymentRequest::factory()->create(['venue_id' => $this->second->id, 'status' => PackagePaymentRequest::STATUS_EXPIRED, 'expires_at' => Carbon::parse('2026-11-10'), 'created_at' => Carbon::parse('2026-11-10')]);
    }

    public function test_fr_99_br_114_fees_are_the_paid_amounts_by_payment_time_by_term_and_venue(): void
    {
        // A price change after payment never re-prices what was collected.
        $this->packagePrice(PackageTerm::Days30, 450000);
        $report = $this->actingAs($this->platformAdmin())->getJson('/api/admin/platform-fees/report?from=2026-11-01&to=2026-11-30')->assertOk()->json('data');

        $this->assertSame(['amount' => 1400000, 'purchases' => 2, 'renewals' => 1], $report['totals']);
        $byTerm = collect($report['by_term'])->keyBy('term_days');
        $this->assertSame([30, 90, 180, 360], $byTerm->keys()->all());
        $this->assertSame([600000, 1, 1], [$byTerm[30]['amount'], $byTerm[30]['purchases'], $byTerm[30]['renewals']]);
        $this->assertSame([800000, 1, 0], [$byTerm[90]['amount'], $byTerm[90]['purchases'], $byTerm[90]['renewals']]);
        $this->assertSame(0, $byTerm[180]['amount']);
        $this->assertSame([[$this->first->id, 30, 600000], [$this->second->id, 90, 800000]], array_map(fn ($row) => [$row['venue_id'], $row['term_days'], $row['amount']], $report['by_venue']));
    }

    public function test_br_114_filters_take_exactly_their_requests_and_a_terminated_package_keeps_its_fee(): void
    {
        VenuePackage::query()->where('venue_id', $this->first->id)->update(['terminated_at' => now()]);
        $admin = $this->platformAdmin();

        $this->assertSame(600000, $this->actingAs($admin)->getJson("/api/admin/platform-fees/report?from=2026-11-01&to=2026-11-30&venue_id={$this->first->id}")->json('data.totals.amount'));
        $this->assertSame(800000, $this->actingAs($admin)->getJson('/api/admin/platform-fees/report?from=2026-11-01&to=2026-11-30&term=90')->json('data.totals.amount'));
        $this->assertSame(300000, $this->actingAs($admin)->getJson('/api/admin/platform-fees/report?from=2026-11-01&to=2026-11-01')->json('data.totals.amount'));
    }

    public function test_fr_100_br_117_the_fee_report_export_matches_the_screen(): void
    {
        $sheets = $this->excelSheets($this->actingAs($this->platformAdmin())->get('/api/admin/platform-fees/report/export?from=2026-11-01&to=2026-11-30&term=30')->assertOk());

        $summary = $sheets['Tổng hợp'];
        $this->assertSame('Báo cáo thu phí nền tảng', $summary[0][0]);
        $scope = $this->excelScope($summary);
        $this->assertSame(['Tất cả cơ sở', '30 ngày', '01/11/2026 – 30/11/2026', '05/12/2026 10:00'], [$scope['Cơ sở'], $scope['Kỳ hạn'], $scope['Kỳ'], $scope['Thời điểm xuất']]);
        $this->assertContains(['Tổng', 1, 1, 600000], $summary);
        $this->assertSame([['Aura Ba Đình', '30 ngày', 1, 1, 600000]], array_slice($sheets['Chi tiết'], 1));
    }

    public function test_fr_100_br_115_the_request_export_matches_the_filter_and_never_adds_unpaid_amounts_into_a_total(): void
    {
        $sheets = $this->excelSheets($this->actingAs($this->platformAdmin())->get('/api/admin/platform-fees/requests/export?from=2026-11-01&to=2026-11-30')->assertOk());

        $summary = $sheets['Tổng hợp'];
        $this->assertSame('Yêu cầu thanh toán gói', $summary[0][0]);
        $this->assertSame('01/11/2026 – 30/11/2026', $this->excelScope($summary)['Ngày tạo']);
        $this->assertContains(['Chờ thanh toán', 1, 999000], $summary);
        $this->assertContains(['Hết hạn thanh toán', 1, 300000], $summary);
        $total = collect($summary)->first(fn (array $row) => ($row[0] ?? null) === 'Tổng');
        // Created in November: the pending, the expired and three paid requests (the 01/11 00:00 one was requested on 31/10, the 01/12 00:00 one on 30/11).
        $this->assertContains(['Đã thanh toán', 3, 1900000], $summary);
        $this->assertSame(5, $total[1]);
        $this->assertEmpty($total[2] ?? null, "No amount is added across unpaid and paid requests.");
        $this->assertCount(5, array_slice($sheets['Chi tiết'], 1));
    }

    public function test_fr_100_br_116_the_venue_package_export_matches_the_filter(): void
    {
        $sheets = $this->excelSheets($this->actingAs($this->platformAdmin())->get('/api/admin/platform-fees/venues/export?status=active')->assertOk());

        $summary = $sheets['Tổng hợp'];
        $this->assertSame('Tình trạng gói của các cơ sở', $summary[0][0]);
        $this->assertSame('Đang hoạt động', $this->excelScope($summary)['Tình trạng gói']);
        $this->assertEqualsCanonicalizing(['Aura Ba Đình', 'Aura Hà Đông'], array_column(array_slice($sheets['Chi tiết'], 1), 0));
    }

    public function test_br_89_owners_cannot_export_platform_fees(): void
    {
        $owner = $this->ownerOf($this->first->organization);
        foreach (['report/export?from=2026-11-01&to=2026-11-30', 'requests/export', 'venues/export'] as $path) {
            $this->actingAs($owner)->get("/api/admin/platform-fees/{$path}")->assertForbidden();
        }
    }

    private function paid(Venue $venue, PackageTerm $term, int $amount, string $paidAt, string $kind = PackagePaymentRequest::KIND_PURCHASE): void
    {
        $at = Carbon::parse($paidAt, 'Asia/Ho_Chi_Minh');
        $package = VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => $term, 'starts_at' => $at->copy()->utc()]);
        PackagePaymentRequest::factory()->create([
            'venue_id' => $venue->id, 'term_days' => $term, 'amount' => $amount, 'kind' => $kind, 'status' => PackagePaymentRequest::STATUS_PAID,
            'paid_at' => $at->copy()->utc(), 'venue_package_id' => $package->id, 'created_at' => $at->copy()->subMinutes(5),
        ]);
    }
}
