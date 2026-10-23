<?php

namespace Tests\Feature\Module07;

use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Module07\Concerns\ServesCustomers;
use Tests\TestCase;

/** FR-132, BR-160 (UC-7.9, UC-7.16): anyone reports; only the platform admin hides, restores or keeps (D14). */
class ContentModerationTest extends TestCase
{
    use RefreshDatabase;
    use ServesCustomers;

    private User $author;

    private FeedPost $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['name' => 'Tác giả']);
        $this->post = FeedPost::create(['user_id' => $this->author->id, 'type' => FeedPost::TYPE_SHARE, 'content' => 'Bán vợt giá rẻ']);
    }

    public function test_fr_132_a_user_reports_content_once_and_never_their_own(): void
    {
        $reporter = User::factory()->create();
        $this->report($reporter, 'post', $this->post->id)->assertCreated()->assertJsonPath('report.status', 'pending')->assertJsonPath('report.type', 'post');
        $this->report($reporter, 'post', $this->post->id)->assertConflict()->assertJsonPath('code', 'REPORT_DUPLICATE');
        $this->report($this->author, 'post', $this->post->id)->assertUnprocessable()->assertJsonPath('code', 'REPORT_OWN_CONTENT');
        $this->report($reporter, 'post', 999999)->assertNotFound();
        $this->report($reporter, 'review', 999999)->assertNotFound();
        $this->actingAs($reporter)->postJson('/api/content-reports', ['type' => 'post', 'id' => $this->post->id])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_d14_owners_staff_and_customers_cannot_moderate(): void
    {
        $court = $this->bookableCourt();
        $report = $this->report(User::factory()->create(), 'post', $this->post->id)->json('report.id');

        foreach ([$this->ownerOf($court->venue->organization), $this->staffOf($court->venue), User::factory()->create()] as $user) {
            $this->actingAs($user)->getJson('/api/admin/content-reports')->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/content-reports/{$report}/hide", ['note' => 'Ẩn'])->assertForbidden();
        }
        $this->assertFalse($this->post->fresh()->is_hidden);
    }

    public function test_br_160_reports_never_hide_by_themselves_and_the_admin_decision_closes_them_all(): void
    {
        $reports = collect(range(1, 3))->map(fn () => $this->report(User::factory()->create(), 'post', $this->post->id)->json('report.id'));
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/social/feed/{$this->post->id}")->assertOk();

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->getJson('/api/admin/content-reports')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.target.excerpt', 'Bán vợt giá rẻ');
        $this->actingAs($admin)->getJson("/api/admin/content-reports/{$reports[0]}")->assertOk()
            ->assertJsonPath('pending_reports', 3)->assertJsonPath('target.content', 'Bán vợt giá rẻ')->assertJsonPath('target.is_hidden', false);
        $this->actingAs($admin)->postJson("/api/admin/content-reports/{$reports[0]}/hide", [])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->actingAs($admin)->postJson("/api/admin/content-reports/{$reports[0]}/restore", ['note' => 'Thử'])->assertUnprocessable()->assertJsonPath('code', 'CONTENT_NOT_HIDDEN');

        $this->actingAs($admin)->postJson("/api/admin/content-reports/{$reports[0]}/hide", ['note' => 'Quảng cáo'])
            ->assertOk()->assertJsonPath('report.action_taken', 'hidden')->assertJsonPath('report.reviewer.id', $admin->id);
        $this->assertSame(3, FeedReport::where('status', 'resolved')->where('action_taken', 'hidden')->count());
        $this->assertDatabaseHas('moderation_records', ['target_id' => $this->post->id, 'action' => 'hide', 'actor_id' => $admin->id, 'reason' => 'Quảng cáo']);
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/social/feed/{$this->post->id}")->assertNotFound();

        $this->actingAs($admin)->postJson("/api/admin/content-reports/{$reports[1]}/keep", ['note' => 'Giữ'])->assertUnprocessable()->assertJsonPath('code', 'CONTENT_HIDDEN');
        $this->actingAs($admin)->postJson("/api/admin/content-reports/{$reports[1]}/restore", ['note' => 'Báo nhầm'])->assertOk()->assertJsonPath('report.action_taken', 'restored');
        $this->actingAs($admin)->getJson("/api/admin/content-reports/{$reports[0]}")->assertJsonCount(2, 'history')->assertJsonPath('history.1.action', 'restore');
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/social/feed/{$this->post->id}")->assertOk();
    }

    public function test_br_160_a_comment_report_shows_its_post_and_keep_leaves_it_visible(): void
    {
        $comment = FeedComment::create(['feed_post_id' => $this->post->id, 'user_id' => $this->author->id, 'content' => 'Lời lẽ khó nghe']);
        $report = $this->report(User::factory()->create(), 'comment', $comment->id)->json('report.id');
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->getJson("/api/admin/content-reports/{$report}")->assertOk()
            ->assertJsonPath('context.post.id', $this->post->id)->assertJsonPath('target.type', 'comment');
        $this->actingAs($admin)->postJson("/api/admin/content-reports/{$report}/keep", ['note' => 'Không vi phạm'])->assertOk()->assertJsonPath('report.action_taken', 'kept');
        $this->assertFalse($comment->fresh()->is_hidden);
        $this->actingAs($admin)->getJson('/api/admin/content-reports?status=pending')->assertJsonPath('meta.total', 0);
    }

    private function report(User $user, string $type, int $id): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/content-reports', ['type' => $type, 'id' => $id, 'reason' => 'Vi phạm']);
    }
}
