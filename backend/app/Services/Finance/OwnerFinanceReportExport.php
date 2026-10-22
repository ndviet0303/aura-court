<?php

namespace App\Services\Finance;

use App\Contracts\Repositories\OwnerFinanceReportRepositoryInterface;
use App\Domain\Finance\DTOs\OwnerFinanceFilterData;
use App\Http\Requests\Partner\ShowOwnerFinanceReportRequest;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Venue;
use App\Services\Export\ExcelReportWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** D14, task 5.4: the owner's financial report as Excel, from the same five metrics the screen shows and with no total. */
class OwnerFinanceReportExport
{
    private const METRICS = [
        'received' => 'Tiền đã nhận',
        'completed' => 'Đã hoàn thành',
        'unfinished' => 'Chưa sử dụng / đang sử dụng',
        'no_show' => 'Vắng mặt',
        'incident' => 'Kết thúc do sự cố',
    ];

    private const BOOKING_STATUSES = [
        'held' => 'Đang giữ chỗ',
        'confirmed' => 'Đã xác nhận',
        'in_use' => 'Đang sử dụng',
        'completed' => 'Đã hoàn thành',
        'cancelled' => 'Đã hủy',
        'expired' => 'Đã hết hạn',
        'disputed' => 'Đang tranh chấp',
        'legacy' => 'Đơn hệ thống cũ',
    ];

    public function __construct(
        private readonly OwnerFinanceReportRepositoryInterface $reports,
        private readonly ExcelReportWriter $writer,
    ) {}

    public function download(ShowOwnerFinanceReportRequest $request, int $organizationId): StreamedResponse
    {
        $filter = OwnerFinanceFilterData::fromRequest($request, $organizationId);
        $metrics = $this->reports->metrics($filter);
        $from = (string) $request->validated('from');
        $to = (string) $request->validated('to');

        $summary = [['Chỉ số', 'Số tiền (VND)', 'Số đơn']];
        foreach (self::METRICS as $key => $label) {
            $summary[] = [$label, $metrics[$key]['amount'], $metrics[$key]['count']];
        }

        return $this->writer->download(
            "bao-cao-tai-chinh-{$from}-{$to}.xlsx",
            'Báo cáo tài chính cơ sở',
            [
                'Tổ chức' => (string) Organization::query()->find($organizationId)?->name,
                'Cơ sở' => $filter->venueId ? (string) Venue::query()->find($filter->venueId)?->name : 'Tất cả cơ sở',
                'Sân' => $filter->courtId ? (string) Court::query()->find($filter->courtId)?->code : 'Tất cả sân',
                'Kỳ' => ExcelReportWriter::period($from, $to),
            ],
            $summary,
            ['Chỉ số', 'Mã đơn', 'Trạng thái đơn', 'Cơ sở', 'Sân', 'Bắt đầu', 'Kết thúc', 'Thời điểm ghi nhận', 'Số tiền (VND)', 'Phải trả (Pᵢ)'],
            $this->details($metrics),
        );
    }

    /**
     * @param  array<string, array{amount: int, count: int, rows: iterable<int, Reservation>}>  $metrics
     * @return \Generator<int, list<int|string|null>>
     */
    private function details(array $metrics): \Generator
    {
        foreach (self::METRICS as $key => $label) {
            foreach ($metrics[$key]['rows'] as $row) {
                yield [
                    $label,
                    $row->code,
                    self::BOOKING_STATUSES[$row->status] ?? $row->status,
                    $row->court?->venue?->name,
                    $row->court?->code,
                    ExcelReportWriter::time($row->starts_at),
                    ExcelReportWriter::time($row->ends_at),
                    ExcelReportWriter::time($row->getAttribute('finance_at')),
                    (int) $row->getAttribute('finance_amount'),
                    (int) $row->total_amount,
                ];
            }
        }
    }
}
