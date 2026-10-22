<?php

namespace Tests\Feature\Module02;

use App\Models\Court;
use App\Models\OutboxMessage;
use App\Models\SuspensionRemediation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSuspension;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-47..FR-52, BR-43..BR-46, BR-48; design D14. UC-2.30, UC-2.31, UC-2.32, UC-2.33. */
class SuspensionRemediationTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    private const CONTENT = 'Đã lắp bình chữa cháy mới';

    private Venue $venue;

    private User $owner;

    private User $admin;

    private VenueSuspension $suspension;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->venue = $this->openVenue();
        $this->owner = $this->ownerOf($this->venue->organization);
        $this->admin = $this->platformAdmin();
        $this->actingAs($this->admin)->postJson("/api/admin/venues/{$this->venue->id}/suspensions", ['reason' => 'Vi phạm quy định an toàn'])->assertCreated();
        $this->suspension = VenueSuspension::sole();
    }

    private function pdf(int $kilobytes = 100, string $name = 'minh-chung.pdf', string $mime = 'application/pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, $mime);
    }

    private function submit(array $body = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(
            "/api/partner/organizations/{$this->venue->organization_id}/venues/{$this->venue->id}/suspensions/{$this->suspension->id}/remediations",
            $body + ['content' => self::CONTENT, 'files' => [$this->pdf()]],
            ['Accept' => 'application/json'],
        );
    }

    private function review(SuspensionRemediation $remediation, string $decision, ?string $feedback = 'Đã kiểm tra hồ sơ')
    {
        return $this->actingAs($this->admin)->postJson("/api/admin/venue-remediations/{$remediation->id}/review", array_filter(['decision' => $decision, 'feedback' => $feedback]));
    }

    public function test_br_44_remediation_validation_boundaries(): void
    {
        $invalid = [
            'content 9' => ['content' => str_repeat('a', 9)],
            'content 5001' => ['content' => str_repeat('a', 5001)],
            'no files' => ['files' => []],
            '11 files' => ['files' => array_map(fn () => $this->pdf(), range(1, 11))],
            'docx' => ['files' => [$this->pdf(10, 'a.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]],
            '11 MB' => ['files' => [$this->pdf(11 * 1024)]],
        ];
        foreach ($invalid as $case => $body) {
            $this->submit($body)->assertStatus(422);
            $this->assertSame(0, SuspensionRemediation::count(), $case);
        }
        $this->assertSame([], Storage::disk('local')->allFiles(), 'a refused submission stores no file');

        $this->submit(['content' => '  '.str_repeat('a', 10).'  '])->assertCreated();
        $this->review(SuspensionRemediation::sole(), 'request_more_info')->assertOk();
        $this->submit(['content' => str_repeat('a', 5000), 'files' => array_map(fn () => $this->pdf(), range(1, 10))])->assertCreated();
        $this->assertSame(2, SuspensionRemediation::count());
        $this->assertStringStartsWith('private/remediations/', SuspensionRemediation::latest('id')->first()->files[0]['path']);
    }

    public function test_br_45_pending_request_blocks_a_new_submission(): void
    {
        $this->submit()->assertCreated();

        $this->submit()->assertStatus(409);
        $this->assertSame(1, SuspensionRemediation::count());
        $this->assertSame('pending', SuspensionRemediation::sole()->status);
        $this->assertNull($this->suspension->fresh()->lifted_at, 'submitting never lifts');
    }

    public function test_br_45_the_partial_unique_index_keeps_one_pending_row(): void
    {
        $this->submit()->assertCreated();

        $this->expectException(UniqueConstraintViolationException::class);
        SuspensionRemediation::create([
            'venue_suspension_id' => $this->suspension->id, 'content' => self::CONTENT, 'files' => [], 'status' => 'pending', 'submitted_at' => now(),
        ]);
    }

    public function test_br_45_resubmission_keeps_history(): void
    {
        $this->submit()->assertCreated();
        $this->review(SuspensionRemediation::sole(), 'reject', 'Thiếu ảnh hiện trường')->assertOk();
        $this->submit(['content' => 'Bổ sung ảnh hiện trường'])->assertCreated();

        $url = "/api/partner/organizations/{$this->venue->organization_id}/venues/{$this->venue->id}/suspensions/{$this->suspension->id}";
        $this->actingAs($this->owner)->getJson($url)->assertOk()
            ->assertJsonPath('data.remediation_status', 'pending')
            ->assertJsonPath('data.remediations.0.status', 'rejected')
            ->assertJsonPath('data.remediations.0.review_note', 'Thiếu ảnh hiện trường')
            ->assertJsonPath('data.remediations.1.content', 'Bổ sung ảnh hiện trường')
            ->assertJsonStructure(['data' => ['remediations' => [['files' => [['url']], 'submitted_at', 'reviewed_at']]]]);
        $this->assertStringContainsString('signature=', $this->actingAs($this->owner)->getJson($url)->json('data.remediations.0.files.0.url'));
    }

    public function test_br_43_not_the_owner(): void
    {
        $this->submit([], $this->staffOf($this->venue))->assertForbidden();
        $this->submit([], $this->ownerOf($this->openVenue()->organization))->assertForbidden();
        $this->submit([], $this->admin)->assertForbidden();
        $this->assertSame(0, SuspensionRemediation::count());
    }

    public function test_fr_49_admin_queue_lists_pending_first_then_oldest(): void
    {
        $this->submit()->assertCreated();
        $other = $this->openVenue();
        $this->actingAs($this->admin)->postJson("/api/admin/venues/{$other->id}/suspensions", ['reason' => 'Vi phạm quy định an toàn'])->assertCreated();
        $otherSuspension = VenueSuspension::query()->where('venue_id', $other->id)->sole();
        $this->actingAs($this->ownerOf($other->organization))->post(
            "/api/partner/organizations/{$other->organization_id}/venues/{$other->id}/suspensions/{$otherSuspension->id}/remediations",
            ['content' => self::CONTENT, 'files' => [$this->pdf()]], ['Accept' => 'application/json'],
        )->assertCreated();
        $this->review(SuspensionRemediation::query()->where('venue_suspension_id', $this->suspension->id)->sole(), 'reject')->assertOk();

        $rows = $this->actingAs($this->admin)->getJson('/api/admin/venue-remediations')->assertOk()->json('data');
        $this->assertSame(['pending', 'rejected'], array_column($rows, 'status'));
        $this->actingAs($this->admin)->getJson('/api/admin/venue-remediations?status=rejected')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->owner)->getJson('/api/admin/venue-remediations')->assertForbidden();

        $first = SuspensionRemediation::query()->where('venue_suspension_id', $this->suspension->id)->sole();
        $this->actingAs($this->admin)->getJson("/api/admin/venue-remediations/{$first->id}")->assertOk()
            ->assertJsonPath('data.suspension.reason', 'Vi phạm quy định an toàn')
            ->assertJsonPath('data.suspension.remediations.0.id', $first->id);
    }

    public function test_fr_50_review_rules(): void
    {
        $this->submit()->assertCreated();
        $remediation = SuspensionRemediation::sole();

        $this->review($remediation, 'approve', null)->assertStatus(422)->assertJsonValidationErrors('feedback');
        $this->review($remediation, 'request_more_info')->assertOk();
        $this->review($remediation, 'approve')->assertStatus(409);
        $this->assertSame('more_info_requested', $remediation->fresh()->status);
        $this->assertNull($this->suspension->fresh()->lifted_at);
    }

    public function test_br_46_approval_lifts_without_reopening(): void
    {
        $court = $this->activeCourt($this->venue, ['status' => Court::STATUS_PAUSED]);
        $this->venue->forceFill(['status' => Venue::STATUS_PAUSED])->save();
        $this->submit()->assertCreated();

        $this->review(SuspensionRemediation::sole(), 'approve', 'Đã khắc phục đầy đủ')->assertOk();

        $suspension = $this->suspension->fresh();
        $this->assertNotNull($suspension->lifted_at);
        $this->assertSame([$this->admin->id, SuspensionRemediation::sole()->id], [$suspension->lifted_by, $suspension->remediation_id]);
        $this->assertSame(Venue::STATUS_PAUSED, $this->venue->fresh()->status, 'approval never reopens');
        $this->assertSame(Court::STATUS_PAUSED, $court->fresh()->status);
        $this->actingAs($this->owner)->getJson("/api/partner/organizations/{$this->venue->organization_id}/venues/{$this->venue->id}/suspensions/{$suspension->id}")
            ->assertJsonPath('data.conclusion', 'Đã khắc phục đầy đủ')->assertJsonPath('data.is_active', false);
        $this->submit()->assertStatus(409);
    }

    public function test_br_46_approval_at_an_active_venue_takes_bookings_again(): void
    {
        $court = $this->activeCourt($this->venue);
        $this->priceCourt($court);
        $this->submit()->assertCreated();
        $this->getJson('/api/courts')->assertJsonMissing(['id' => $court->id]);

        $this->review(SuspensionRemediation::sole(), 'approve')->assertOk();

        $this->assertSame(Venue::STATUS_ACTIVE, $this->venue->fresh()->status);
        $this->getJson('/api/courts')->assertJsonFragment(['id' => $court->id]);
    }

    public function test_br_48_notification_recipients(): void
    {
        $staff = $this->staffOf($this->venue);
        $lockedAdmin = User::factory()->create(['role' => 'platform_admin', 'status' => 'locked']);
        $this->submit()->assertCreated();
        $this->review(SuspensionRemediation::sole(), 'reject')->assertOk();

        $submitted = OutboxMessage::query()->where('event_type', 'venue.remediation.submitted')->get()->map(fn ($m) => $m->payload['user_id']);
        $this->assertContains($this->admin->id, $submitted);
        $this->assertNotContains($lockedAdmin->id, $submitted);
        $this->assertNotContains($this->owner->id, $submitted);
        $reviewed = OutboxMessage::query()->where('event_type', 'venue.remediation.rejected')->get();
        $this->assertSame([$this->owner->id], $reviewed->map(fn ($m) => $m->payload['user_id'])->all());
        $this->assertNotContains($staff->id, $reviewed->map(fn ($m) => $m->payload['user_id']));
        $this->assertNotEmpty($reviewed->first()->payload['action_url']);
    }
}
