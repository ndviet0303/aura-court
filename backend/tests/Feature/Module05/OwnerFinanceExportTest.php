<?php

namespace Tests\Feature\Module05;

use App\Models\BookingAttendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\ReadsExcelExports;
use Tests\Concerns\RecordsOwnerPayments;
use Tests\TestCase;

/** UC-5.12, FR-96, BR-89, BR-117: the owner's report as Excel, matching the screen and its filters. */
class OwnerFinanceExportTest extends TestCase
{
    use BuildsVenues;
    use ReadsExcelExports;
    use RecordsOwnerPayments;
    use RefreshDatabase;

    public function test_fr_96_br_117_the_file_has_title_scope_period_export_time_metrics_and_details(): void
    {
        $this->travelTo(Carbon::parse('2026-12-05 10:00', 'Asia/Ho_Chi_Minh'));
        $venue = $this->openVenue(['name' => 'Aura Cầu Giấy']);
        $court = $this->activeCourt($venue, ['code' => 'S01']);
        $at = Carbon::parse('2026-11-10 09:00', 'Asia/Ho_Chi_Minh');
        $done = $this->booking($court, 200000, $at);
        $this->receipt($venue, 200000, $at, $done);
        $this->attendance($done, BookingAttendance::STATUS_CHECKED_OUT, $at->copy()->addDay());
        $this->booking($this->activeCourt($this->openVenue(['organization_id' => $venue->organization_id])), 400000, $at);
        $owner = $this->ownerOf($venue->organization);
        $query = "from=2026-11-01&to=2026-11-30&venue_id={$venue->id}&court_id={$court->id}";
        $base = "/api/partner/organizations/{$venue->organization_id}/finance/report";

        $screen = $this->actingAs($owner)->getJson("{$base}?{$query}")->assertOk()->json('data.metrics');
        $sheets = $this->excelSheets($this->actingAs($owner)->get("{$base}/export?{$query}")->assertOk());

        $summary = $sheets['Tổng hợp'];
        $this->assertSame('Báo cáo tài chính cơ sở', $summary[0][0]);
        $scope = $this->excelScope($summary);
        $this->assertSame('Aura Cầu Giấy', $scope['Cơ sở']);
        $this->assertSame('S01', $scope['Sân']);
        $this->assertSame('01/11/2026 – 30/11/2026', $scope['Kỳ']);
        $this->assertSame('05/12/2026 10:00', $scope['Thời điểm xuất']);
        $metrics = collect($summary)->slice(count($scope) + 3)->mapWithKeys(fn (array $row) => [$row[0] => [$row[1], $row[2]]])->all();
        $this->assertSame([$screen['received']['amount'], $screen['received']['count']], $metrics['Tiền đã nhận']);
        $this->assertSame([$screen['completed']['amount'], $screen['completed']['count']], $metrics['Đã hoàn thành']);
        $this->assertSame([0, 0], $metrics['Chưa sử dụng / đang sử dụng']);
        $this->assertCount(5, $metrics, 'Five metrics and no revenue total (BR-113).');

        $details = array_slice($sheets['Chi tiết'], 1);
        $this->assertCount(2, $details);
        $this->assertSame([$done->code, $done->code], array_column($details, 1));
        $this->assertSame(['Aura Cầu Giấy', 'S01'], [$details[0][3], $details[0][4]]);
    }

    public function test_br_89_staff_and_other_owners_cannot_export(): void
    {
        $venue = $this->openVenue();
        $foreign = $this->openVenue();
        $url = "/api/partner/organizations/{$venue->organization_id}/finance/report/export?from=2026-11-01&to=2026-11-30";

        $this->actingAs($this->staffOf($venue))->get($url)->assertForbidden();
        $this->actingAs($this->ownerOf($foreign->organization))->get($url)->assertForbidden();
        $this->actingAs($this->ownerOf($venue->organization))->get("{$url}&venue_id={$foreign->id}")->assertForbidden();
    }
}
