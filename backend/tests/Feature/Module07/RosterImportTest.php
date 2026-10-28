<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\ReadsExcelExports;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-126, BR-150, UC-7.27: the internal roster from the spreadsheet template or typed unit by unit; athletes get no account. */
class RosterImportTest extends TestCase
{
    use ReadsExcelExports;
    use RefreshDatabase;
    use RunsTournaments;

    private const HEADER = ['Tên đơn vị', 'Họ tên', 'Ngày sinh (dd/mm/yyyy)', 'Vai trò (chính/dự bị)', 'Phòng ban'];

    private Court $court;

    private Tournament $tournament;

    private User $representative;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->court = $this->bookableCourt();
        $this->tournament = $this->internalTournament($this->court, ['entry_type' => 'double', 'athlete_fields' => ['Phòng ban']]);
        $this->representative = $this->tournament->grants()->first()->user;
    }

    /** @param  list<list<string>>  $rows */
    private function sheet(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ([self::HEADER, ...$rows] as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'danh-sach.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_fr_126_the_template_carries_the_tournaments_athlete_fields(): void
    {
        $sheets = $this->excelSheets($this->actingAs($this->representative)->get("/api/managed/tournaments/{$this->tournament->uuid}/roster/template")->assertOk());

        $this->assertSame(self::HEADER, $sheets['Danh sách'][0]);
    }

    public function test_fr_126_preview_names_each_bad_row_and_field_and_saves_nothing(): void
    {
        $file = $this->sheet([
            ['Cặp Kế toán', 'Lê Thị Hoa', '12/04/1990', 'chính', 'Kế toán'],
            ['Cặp Kế toán', 'Phạm Văn Nam', '31/02/1991', 'chính', 'Kế toán'],
            ['Cặp Kỹ thuật', 'Đỗ Minh', '01/01/1992', 'thủ môn', ''],
            ['', 'Không đơn vị', '01/01/1993', 'chính', 'IT'],
        ]);

        $errors = $this->actingAs($this->representative)->post("/api/managed/tournaments/{$this->tournament->uuid}/roster/preview", ['file' => $file])
            ->assertOk()->assertJsonCount(2, 'entries')->json('errors');

        $this->assertEqualsCanonicalizing(
            [[3, 'birth_date'], [4, 'Phòng ban'], [4, 'role'], [4, 'members'], [5, 'unit']],
            array_map(fn (array $error): array => [$error['row'], $error['field']], $errors),
        );
        $this->assertSame(0, TournamentEntry::count());
        $this->post("/api/managed/tournaments/{$this->tournament->uuid}/roster/confirm", ['file' => $file])
            ->assertUnprocessable()->assertJsonPath('code', 'ROSTER_INVALID');
        $this->assertSame(0, TournamentEntry::count());
    }

    public function test_fr_126_br_150_confirm_replaces_the_roster_with_confirmed_units_and_creates_no_account(): void
    {
        $this->entry($this->tournament, TournamentEntry::STATUS_CONFIRMED, name: 'Cũ', members: 2);
        $users = User::count();

        $this->actingAs($this->representative)->post("/api/managed/tournaments/{$this->tournament->uuid}/roster/confirm", ['file' => $this->sheet([
            ['Cặp Kế toán', 'Lê Thị Hoa', '12/04/1990', 'chính', 'Kế toán'],
            ['Cặp Kế toán', 'Phạm Văn Nam', '01/02/1991', 'chính', 'Kế toán'],
            ['Cặp Kỹ thuật', 'Đỗ Minh', '01/01/1992', '', 'Kỹ thuật'],
            ['cặp kỹ thuật', 'Vũ An', '02/01/1992', 'chính', 'Kỹ thuật'],
        ])])->assertOk()->assertJsonCount(2, 'entries');

        $this->assertSame(['Cặp Kế toán', 'Cặp Kỹ thuật'], TournamentEntry::orderBy('name')->pluck('name')->all());
        $this->assertSame(['confirmed'], TournamentEntry::distinct()->pluck('status')->all());
        $this->assertSame($users, User::count());
    }

    public function test_fr_126_units_are_typed_or_removed_until_the_roster_is_locked(): void
    {
        $this->actingAs($this->representative);
        $id = $this->postJson("/api/managed/tournaments/{$this->tournament->uuid}/roster/entries", ['name' => 'Cặp Nhân sự', 'members' => [
            $this->athlete('A', extra: ['Phòng ban' => 'Nhân sự']), $this->athlete('B', extra: ['Phòng ban' => 'Nhân sự']),
        ]])->assertCreated()->assertJsonPath('entry.status', 'confirmed')->assertJsonPath('entry.representative', null)->json('entry.id');
        $this->postJson("/api/managed/tournaments/{$this->tournament->uuid}/roster/entries", ['name' => 'Thiếu', 'members' => [$this->athlete('A', extra: ['Phòng ban' => 'X'])]])
            ->assertUnprocessable()->assertJsonValidationErrors('members');
        $this->deleteJson("/api/tournament-entries/{$id}")->assertNoContent();
        $this->entry($this->tournament, TournamentEntry::STATUS_CONFIRMED, members: 2);
        $kept = $this->entry($this->tournament, TournamentEntry::STATUS_CONFIRMED, members: 2);

        $this->postJson("/api/managed/tournaments/{$this->tournament->uuid}/lock-roster")->assertOk()->assertJsonPath('tournament.status', 'roster_locked');
        $this->deleteJson("/api/tournament-entries/{$kept->id}")->assertConflict()->assertJsonPath('code', 'ROSTER_LOCKED');
        $this->post("/api/managed/tournaments/{$this->tournament->uuid}/roster/preview", ['file' => $this->sheet([])])->assertConflict()->assertJsonPath('code', 'ROSTER_LOCKED');
    }

    public function test_uc_7_27_a2_a_unit_is_corrected_until_the_roster_is_locked(): void
    {
        $this->actingAs($this->representative);
        $entry = $this->entry($this->tournament, TournamentEntry::STATUS_CONFIRMED, members: 2);
        $this->entry($this->tournament, TournamentEntry::STATUS_CONFIRMED, members: 2);
        $body = ['name' => 'Cặp Kế toán', 'members' => [$this->athlete('C', extra: ['Phòng ban' => 'Kế toán']), $this->athlete('D', extra: ['Phòng ban' => 'Kế toán'])]];

        $this->putJson("/api/tournament-entries/{$entry->id}", ['name' => 'Thiếu', 'members' => [$this->athlete('C', extra: ['Phòng ban' => 'X'])]])
            ->assertUnprocessable()->assertJsonValidationErrors('members');
        $this->putJson("/api/tournament-entries/{$entry->id}", $body)->assertOk()
            ->assertJsonPath('entry.name', 'Cặp Kế toán')->assertJsonPath('entry.members.1.full_name', 'D')->assertJsonCount(2, 'entry.members');
        $this->assertSame(['C', 'D'], $entry->members()->orderBy('position')->pluck('full_name')->all());

        $this->postJson("/api/managed/tournaments/{$this->tournament->uuid}/lock-roster")->assertOk();
        $this->putJson("/api/tournament-entries/{$entry->id}", $body)->assertConflict()->assertJsonPath('code', 'ROSTER_LOCKED');
    }

    public function test_fr_126_only_those_running_the_tournament_manage_its_roster(): void
    {
        $this->actingAs(User::factory()->create())->get("/api/managed/tournaments/{$this->tournament->uuid}/roster/template")->assertForbidden();
        $this->actingAs($this->staffOf($this->court->venue))->postJson("/api/managed/tournaments/{$this->tournament->uuid}/roster/entries", ['name' => 'X', 'members' => [
            $this->athlete('A', extra: ['Phòng ban' => 'X']), $this->athlete('B', extra: ['Phòng ban' => 'X']),
        ]])->assertForbidden();
        $public = $this->tournament($this->court);
        $this->actingAs($this->ownerOf($this->court->venue->organization))->get("/api/managed/tournaments/{$public->uuid}/roster/template")->assertForbidden();
    }
}
