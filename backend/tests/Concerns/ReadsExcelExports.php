<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads an ExcelReportWriter download back as rows per sheet (openspout, already installed).
 *
 *     $sheets = $this->excelSheets($this->get($url)->assertOk());
 *     $sheets['Tổng hợp'][0][0];   // the report title
 */
trait ReadsExcelExports
{
    /** @return array<string, list<list<mixed>>> */
    protected function excelSheets(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        return $sheets;
    }

    /**
     * The "label => value" scope rows under the title, up to the blank row.
     *
     * @param  list<list<mixed>>  $summarySheet
     * @return array<string, mixed>
     */
    protected function excelScope(array $summarySheet): array
    {
        $scope = [];
        foreach (array_slice($summarySheet, 1) as $row) {
            if (($row[0] ?? null) === null || $row[0] === '') {
                break;
            }
            $scope[$row[0]] = $row[1] ?? null;
        }

        return $scope;
    }
}
