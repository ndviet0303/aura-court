<?php

namespace App\Services\Export;

use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * D14: an Excel report streamed to the browser. The "Tổng hợp" sheet opens with the report name, its scope and the
 * export time in Vietnam time, then the summary table; the "Chi tiết" sheet holds the detail rows.
 */
class ExcelReportWriter
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    /**
     * @param  array<string, string>  $scope  label => value, such as the venue, the court and the period
     * @param  list<list<int|string|null>>  $summary  the first row is the header
     * @param  list<string>  $detailHeader
     * @param  iterable<list<int|string|null>>  $details
     */
    public function download(string $fileName, string $title, array $scope, array $summary, array $detailHeader, iterable $details): StreamedResponse
    {
        $exportedAt = now(self::TIMEZONE)->format('d/m/Y H:i');

        return response()->streamDownload(function () use ($title, $scope, $summary, $detailHeader, $details, $exportedAt) {
            $bold = (new Style)->withFontBold(true);
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->getCurrentSheet()->setName('Tổng hợp');
            $writer->addRow(Row::fromValuesWithStyle([$title], $bold));
            foreach ([...$scope, 'Thời điểm xuất' => $exportedAt] as $label => $value) {
                $writer->addRow(Row::fromValues([$label, $value]));
            }
            $writer->addRow(Row::fromValues([]));
            foreach ($summary as $index => $row) {
                $writer->addRow($index === 0 ? Row::fromValuesWithStyle($row, $bold) : Row::fromValues($row));
            }

            $writer->addNewSheetAndMakeItCurrent()->setName('Chi tiết');
            $writer->addRow(Row::fromValuesWithStyle($detailHeader, $bold));
            foreach ($details as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** A time as the screens show it, in Vietnam time. */
    public static function time(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y H:i');
    }

    /** A Y-m-d period as d/m/Y – d/m/Y. */
    public static function period(string $from, string $to): string
    {
        $day = fn (string $date) => implode('/', array_reverse(explode('-', $date)));

        return $day($from).' – '.$day($to);
    }
}
