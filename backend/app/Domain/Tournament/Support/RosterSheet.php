<?php

namespace App\Domain\Tournament\Support;

use App\Domain\Tournament\DTOs\EntryData;
use App\Domain\Tournament\Rules\EntryShape;
use App\Models\Tournament;
use App\Models\TournamentEntryMember;
use Carbon\Carbon;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UC-7.27, FR-126, BR-150: an internal roster as a spreadsheet, one athlete per row; rows sharing a unit name form one unit.
 * Reading never writes: it returns the units it understood and every problem with its row, so the screen can show
 * them before anything is saved.
 */
final class RosterSheet
{
    private const HEADER = ['Tên đơn vị', 'Họ tên', 'Ngày sinh (dd/mm/yyyy)', 'Vai trò (chính/dự bị)'];

    private const MAX_ROWS = 2000;

    public static function template(Tournament $tournament): StreamedResponse
    {
        return response()->streamDownload(function () use ($tournament) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName('Danh sách');
            $writer->addRow(Row::fromValuesWithStyle([...self::HEADER, ...(array) $tournament->athlete_fields], (new Style)->withFontBold(true)));
            $writer->close();
        }, 'danh-sach-giai.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array{entries: list<EntryData>, errors: list<array{row: int, field: string, message: string}>} */
    public static function read(Tournament $tournament, string $path): array
    {
        $fields = array_values((array) $tournament->athlete_fields);
        [$units, $errors] = [[], []];
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            $line = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                if ($line === 1 || $row->isEmpty()) {
                    continue;
                }
                if ($line > self::MAX_ROWS + 1) {
                    $errors[] = ['row' => $line, 'field' => 'file', 'message' => 'Tệp quá '.self::MAX_ROWS.' dòng.'];
                    break;
                }
                [$member, $unit, $rowErrors] = self::member($row->toArray(), $fields, $line);
                array_push($errors, ...$rowErrors);
                if ($unit !== '') {
                    $key = mb_strtolower($unit);
                    $units[$key] ??= ['name' => $unit, 'row' => $line, 'members' => []];
                    $units[$key]['members'][] = $member;
                }
            }
            break;
        }
        $reader->close();

        $entries = [];
        foreach ($units as $unit) {
            $entry = new EntryData($unit['name'], $unit['members']);
            foreach (EntryShape::errors($tournament, $entry) as $error) {
                if ($error['field'] === 'members') {
                    $errors[] = ['row' => $unit['row'], 'field' => 'members', 'message' => "{$unit['name']}: {$error['message']}"];
                }
            }
            $entries[] = $entry;
        }
        if ($entries === [] && $errors === []) {
            $errors[] = ['row' => 2, 'field' => 'file', 'message' => 'Tệp chưa có vận động viên nào.'];
        }
        usort($errors, fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return ['entries' => $entries, 'errors' => $errors];
    }

    /**
     * @param  list<mixed>  $cells
     * @param  list<string>  $fields
     * @return array{0: array{full_name: string, birth_date: string, extra: array<string, string>, role: string}, 1: string, 2: list<array{row: int, field: string, message: string}>}
     */
    private static function member(array $cells, array $fields, int $line): array
    {
        $text = fn (int $index): string => trim((string) (($cells[$index] ?? '') instanceof DateTimeInterface ? '' : ($cells[$index] ?? '')));
        $errors = [];
        $unit = mb_substr($text(0), 0, 120);
        $name = mb_substr($text(1), 0, 120);
        $birth = self::date($cells[2] ?? null);
        $role = match (mb_strtolower($text(3))) {
            '', 'chính', 'chinh' => TournamentEntryMember::ROLE_MAIN,
            'dự bị', 'du bi' => TournamentEntryMember::ROLE_RESERVE,
            default => null,
        };
        $extra = [];
        foreach ($fields as $offset => $field) {
            $extra[$field] = mb_substr($text(4 + $offset), 0, 255);
            if ($extra[$field] === '') {
                $errors[] = ['row' => $line, 'field' => $field, 'message' => "Thiếu {$field}."];
            }
        }
        foreach (['unit' => [$unit === '', 'Thiếu tên đơn vị.'], 'full_name' => [$name === '', 'Thiếu họ tên.'], 'birth_date' => [$birth === null, 'Ngày sinh phải theo dạng dd/mm/yyyy và ở quá khứ.'], 'role' => [$role === null, 'Vai trò phải là "chính" hoặc "dự bị".']] as $field => [$bad, $message]) {
            if ($bad) {
                $errors[] = ['row' => $line, 'field' => $field, 'message' => $message];
            }
        }

        return [['full_name' => $name, 'birth_date' => (string) $birth, 'extra' => $extra, 'role' => (string) $role], $unit, $errors];
    }

    /** A spreadsheet date cell, or dd/mm/yyyy text, as Y-m-d when it is a real past date. */
    private static function date(mixed $cell): ?string
    {
        if ($cell instanceof DateTimeInterface) {
            $date = Carbon::instance($cell);
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim((string) $cell), $parts) === 1 && checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3])) {
            $date = Carbon::createFromDate((int) $parts[3], (int) $parts[2], (int) $parts[1]);
        } else {
            return null;
        }

        return $date->isPast() ? $date->format('Y-m-d') : null;
    }
}
