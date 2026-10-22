<?php

namespace App\Services\Package;

use App\Contracts\Repositories\PlatformFeeReportRepositoryInterface;
use App\Contracts\Repositories\PlatformFeeRequestRepositoryInterface;
use App\Contracts\Repositories\PlatformFeeVenueRepositoryInterface;
use App\Domain\Package\DTOs\PlatformFeeReportFilterData;
use App\Domain\Package\DTOs\PlatformFeeRequestFilterData;
use App\Domain\Package\DTOs\PlatformFeeVenueFilterData;
use App\Domain\Package\Enums\PackageStatus;
use App\Domain\Package\Enums\PackageTerm;
use App\Http\Requests\Admin\ListPlatformFeeRequestsRequest;
use App\Http\Requests\Admin\ListPlatformFeeVenuesRequest;
use App\Http\Requests\Admin\ShowPlatformFeeReportRequest;
use App\Models\PackagePaymentRequest;
use App\Models\Venue;
use App\Services\Export\ExcelReportWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** D14, tasks 6.1–6.3: the three platform fee screens as Excel, each from the same query and filters as its screen. */
class PlatformFeeExport
{
    private const REQUEST_STATUSES = ['pending' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'expired' => 'Hết hạn thanh toán'];

    private const KINDS = ['purchase' => 'Mua mới', 'renewal' => 'Gia hạn'];

    private const PACKAGE_STATUSES = ['none' => 'Chưa có gói', 'active' => 'Đang hoạt động', 'expired' => 'Đã hết hạn', 'terminated' => 'Đã chấm dứt'];

    private const VENUE_STATUSES = ['active' => 'Đang hoạt động', 'inactive' => 'Chưa hoạt động', 'paused' => 'Tạm dừng', 'closed' => 'Đã đóng'];

    public function __construct(
        private readonly PlatformFeeRequestRepositoryInterface $requests,
        private readonly PlatformFeeVenueRepositoryInterface $venues,
        private readonly PlatformFeeReportRepositoryInterface $reports,
        private readonly ExcelReportWriter $writer,
    ) {}

    public function requests(ListPlatformFeeRequestsRequest $request): StreamedResponse
    {
        $now = now();
        $filter = PlatformFeeRequestFilterData::fromRequest($request);
        $rows = $this->requests->all($filter, $now);
        $from = $request->validated('from');

        $summary = [['Trạng thái', 'Số yêu cầu', 'Số tiền (VND)']];
        foreach (self::REQUEST_STATUSES as $status => $label) {
            $matching = $rows->filter(fn (PackagePaymentRequest $row) => $row->statusAt($now) === $status);
            $summary[] = [$label, $matching->count(), (int) $matching->sum('amount')];
        }
        $summary[] = ['Tổng', $rows->count(), (int) $rows->sum('amount')];

        return $this->writer->download(
            'yeu-cau-thanh-toan-goi-'.$now->format('Ymd-Hi').'.xlsx',
            'Yêu cầu thanh toán gói',
            [
                'Trạng thái' => $filter->status ? self::REQUEST_STATUSES[$filter->status] : 'Tất cả trạng thái',
                'Cơ sở' => $this->venueName($filter->venueId),
                'Kỳ hạn' => $this->termLabel($filter->term),
                'Ngày tạo' => $from ? ExcelReportWriter::period($from, (string) $request->validated('to')) : 'Mọi ngày',
            ],
            $summary,
            ['Mã yêu cầu', 'Mã thanh toán', 'Cơ sở', 'Tổ chức', 'Chủ sở hữu', 'Email chủ sở hữu', 'Loại', 'Kỳ hạn (ngày)', 'Số tiền (VND)', 'Trạng thái', 'Tạo lúc', 'Hạn thanh toán', 'Thanh toán lúc', 'Gói bắt đầu', 'Gói kết thúc'],
            $rows->map(fn (PackagePaymentRequest $row) => [
                $row->request_code,
                $row->payment_code,
                $row->venue?->name,
                $row->organization?->name,
                $row->organization?->owner?->name,
                $row->organization?->owner?->email,
                self::KINDS[$row->kind] ?? $row->kind,
                $row->term_days->value,
                $row->amount,
                self::REQUEST_STATUSES[$row->statusAt($now)] ?? $row->statusAt($now),
                ExcelReportWriter::time($row->created_at),
                ExcelReportWriter::time($row->expires_at),
                ExcelReportWriter::time($row->paid_at),
                ExcelReportWriter::time($row->period_starts_at),
                ExcelReportWriter::time($row->period_ends_at),
            ])->all(),
        );
    }

    public function venues(ListPlatformFeeVenuesRequest $request): StreamedResponse
    {
        $now = now();
        $filter = PlatformFeeVenueFilterData::fromRequest($request);
        $rows = $this->venues->all($filter, $now);
        $statusOf = fn (Venue $venue) => ($venue->currentPackage?->statusAt($now) ?? PackageStatus::None)->value;

        $summary = [['Tình trạng gói', 'Số cơ sở']];
        foreach (self::PACKAGE_STATUSES as $status => $label) {
            $summary[] = [$label, $rows->filter(fn (Venue $venue) => $statusOf($venue) === $status)->count()];
        }
        $summary[] = ['Tổng', $rows->count()];
        // BR-116: the expiring groups count every venue, as the screen's chips do, not only the filtered ones.
        foreach ($this->venues->expiringCounts($now) as $days => $count) {
            $summary[] = ["Sắp hết hạn trong {$days} ngày (toàn hệ thống)", $count];
        }

        return $this->writer->download(
            'tinh-trang-goi-'.$now->format('Ymd-Hi').'.xlsx',
            'Tình trạng gói của các cơ sở',
            [
                'Tình trạng gói' => $filter->status ? self::PACKAGE_STATUSES[$filter->status->value] : 'Tất cả tình trạng',
                'Sắp hết hạn' => $filter->expiringWithin ? "Trong {$filter->expiringWithin} ngày" : 'Không lọc',
                'Tìm cơ sở' => $filter->search ?? 'Không lọc',
                'Tính tại' => (string) ExcelReportWriter::time($now),
            ],
            $summary,
            ['Cơ sở', 'Trạng thái cơ sở', 'Tổ chức', 'Chủ sở hữu', 'Email chủ sở hữu', 'Tình trạng gói', 'Kỳ hạn (ngày)', 'Bắt đầu', 'Kết thúc', 'Chấm dứt lúc'],
            $rows->map(fn (Venue $venue) => [
                $venue->name,
                self::VENUE_STATUSES[$venue->status] ?? $venue->status,
                $venue->organization?->name,
                $venue->organization?->owner?->name,
                $venue->organization?->owner?->email,
                self::PACKAGE_STATUSES[$statusOf($venue)],
                $venue->currentPackage?->term_days->value,
                ExcelReportWriter::time($venue->currentPackage?->starts_at),
                ExcelReportWriter::time($venue->currentPackage?->ends_at),
                ExcelReportWriter::time($venue->currentPackage?->terminated_at),
            ])->all(),
        );
    }

    public function report(ShowPlatformFeeReportRequest $request): StreamedResponse
    {
        $filter = PlatformFeeReportFilterData::fromRequest($request);
        $report = $this->reports->report($filter);
        $term = fn (int $days) => "{$days} ngày";

        $summary = [['Kỳ hạn', 'Mua mới', 'Gia hạn', 'Phí đã thu (VND)']];
        foreach ($report['by_term'] as $row) {
            $summary[] = [$term($row['term_days']), $row['purchases'], $row['renewals'], $row['amount']];
        }
        $summary[] = ['Tổng', $report['totals']['purchases'], $report['totals']['renewals'], $report['totals']['amount']];

        return $this->writer->download(
            "bao-cao-thu-phi-{$filter->from}-{$filter->to}.xlsx",
            'Báo cáo thu phí nền tảng',
            [
                'Cơ sở' => $this->venueName($filter->venueId),
                'Kỳ hạn' => $this->termLabel($filter->term),
                'Kỳ' => ExcelReportWriter::period($filter->from, $filter->to),
            ],
            $summary,
            ['Cơ sở', 'Kỳ hạn', 'Mua mới', 'Gia hạn', 'Phí đã thu (VND)'],
            array_map(fn (array $row) => [$row['venue_name'], $term($row['term_days']), $row['purchases'], $row['renewals'], $row['amount']], $report['by_venue']),
        );
    }

    private function venueName(?int $venueId): string
    {
        return $venueId ? (string) Venue::query()->find($venueId)?->name : 'Tất cả cơ sở';
    }

    private function termLabel(?PackageTerm $term): string
    {
        return $term ? "{$term->value} ngày" : 'Tất cả kỳ hạn';
    }
}
