<?php

namespace Tests\Feature;

use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\FeedReport;
use App\Models\Friendship;
use App\Models\ModerationRecord;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFeedTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;
    private User $alice;
    private User $bob;
    private User $stranger;
    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Community Hub',
            'slug' => 'aura-hub-' . uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->alice = User::factory()->create(['name' => 'Alice Nguyen', 'email' => 'alice@feed.vn']);
        $this->bob = User::factory()->create(['name' => 'Bob Tran', 'email' => 'bob@feed.vn']);
        $this->stranger = User::factory()->create(['name' => 'Stranger Le', 'email' => 'stranger@feed.vn']);
        $this->moderator = User::factory()->create(['name' => 'Mod Manager', 'email' => 'mod@feed.vn']);

        foreach ([$this->alice, $this->bob, $this->stranger] as $u) {
            OrganizationMember::create([
                'organization_id' => $this->tenant->id,
                'user_id' => $u->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
        }

        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->moderator->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);

        // Alice and Bob are accepted friends
        Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => Friendship::STATUS_ACCEPTED,
        ]);
    }

    private function headers(string $prefix = 'feed-'): array
    {
        return [
            'X-Organization-Id' => (string) $this->tenant->id,
            'Idempotency-Key' => uniqid($prefix),
        ];
    }

    public function test_organization_scoped_and_friends_scoped_post_creation_and_visibility(): void
    {
        // 1. Alice creates organization post
        $orgPostRes = $this->actingAs($this->alice)
            ->withHeaders($this->headers('p-org-'))
            ->postJson('/api/v2/social/feed', [
                'content' => 'Chào mọi người tại CLB Aura! Chiều nay ai rảnh giao lưu không?',
                'audience' => 'organization',
            ]);
        $orgPostRes->assertCreated();
        $orgPostId = $orgPostRes->json('post.id');

        // 2. Alice creates friends-only post
        $friendPostRes = $this->actingAs($this->alice)
            ->withHeaders($this->headers('p-fr-'))
            ->postJson('/api/v2/social/feed', [
                'content' => 'Hôm nay đánh cầu với Bob vui quá, set 3 căng thẳng!',
                'audience' => 'friends',
            ]);
        $friendPostRes->assertCreated();
        $friendPostId = $friendPostRes->json('post.id');

        // 3. Bob (friend) lists feed -> sees both posts
        $bobFeed = $this->actingAs($this->bob)
            ->withHeaders($this->headers('b-feed-'))
            ->getJson('/api/v2/social/feed');
        $bobFeed->assertOk();
        $bobPostIds = collect($bobFeed->json('data'))->pluck('id');
        $this->assertTrue($bobPostIds->contains($orgPostId));
        $this->assertTrue($bobPostIds->contains($friendPostId));

        // Bob can view friends-only post directly
        $bobShow = $this->actingAs($this->bob)
            ->withHeaders($this->headers('b-show-'))
            ->getJson("/api/v2/social/feed/{$friendPostId}");
        $bobShow->assertOk();

        // 4. Stranger (non-friend) lists feed -> sees only organization post, NOT friends-only post
        $strangerFeed = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('s-feed-'))
            ->getJson('/api/v2/social/feed');
        $strangerFeed->assertOk();
        $strangerPostIds = collect($strangerFeed->json('data'))->pluck('id');
        $this->assertTrue($strangerPostIds->contains($orgPostId));
        $this->assertFalse($strangerPostIds->contains($friendPostId));

        // 5. Scenario: Private post -> Non-friend gets 404 when requesting friends-only post
        $strangerShow = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('s-show-'))
            ->getJson("/api/v2/social/feed/{$friendPostId}");
        $strangerShow->assertStatus(404);
    }

    public function test_post_ownership_checks_author_only_edit_and_delete(): void
    {
        $post = FeedPost::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->alice->id,
            'content' => 'Bài viết gốc của Alice',
            'audience' => 'organization',
        ]);

        // Bob attempts to edit Alice's post -> 403
        $unauthEdit = $this->actingAs($this->bob)
            ->withHeaders($this->headers('edit-unauth-'))
            ->putJson("/api/v2/social/feed/{$post->id}", [
                'content' => 'Bob sửa trộm nội dung',
            ]);
        $unauthEdit->assertStatus(403);
        $this->assertSame('Bài viết gốc của Alice', $post->fresh()->content);

        // Alice edits her own post -> 200
        $authorEdit = $this->actingAs($this->alice)
            ->withHeaders($this->headers('edit-auth-'))
            ->putJson("/api/v2/social/feed/{$post->id}", [
                'content' => 'Nội dung cập nhật chính thức',
            ]);
        $authorEdit->assertOk();
        $this->assertSame('Nội dung cập nhật chính thức', $post->fresh()->content);

        // Stranger attempts to delete Alice's post -> 403
        $unauthDel = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('del-unauth-'))
            ->deleteJson("/api/v2/social/feed/{$post->id}");
        $unauthDel->assertStatus(403);
        $this->assertDatabaseHas('feed_posts', ['id' => $post->id]);

        // Alice deletes her own post -> 200
        $authorDel = $this->actingAs($this->alice)
            ->withHeaders($this->headers('del-auth-'))
            ->deleteJson("/api/v2/social/feed/{$post->id}");
        $authorDel->assertOk();
        $this->assertDatabaseMissing('feed_posts', ['id' => $post->id]);
    }

    public function test_bounded_inputs_and_escaped_rendering(): void
    {
        // 1. Post content > 2000 chars fails
        $tooLong = str_repeat('A', 2001);
        $resLong = $this->actingAs($this->alice)
            ->withHeaders($this->headers('long-'))
            ->postJson('/api/v2/social/feed', [
                'content' => $tooLong,
            ]);
        $resLong->assertStatus(422);

        // 2. Empty post content fails
        $resEmpty = $this->actingAs($this->alice)
            ->withHeaders($this->headers('empty-'))
            ->postJson('/api/v2/social/feed', [
                'content' => '   ',
            ]);
        $resEmpty->assertStatus(422);

        // 3. HTML/Script content is safely stored as literal text without execution
        $xssContent = "<script>alert('hack');</script> & <img src=x onerror=alert(1)>";
        $xssRes = $this->actingAs($this->alice)
            ->withHeaders($this->headers('xss-'))
            ->postJson('/api/v2/social/feed', [
                'content' => $xssContent,
            ]);
        $xssRes->assertCreated();
        $this->assertSame($xssContent, $xssRes->json('post.content'));
    }

    public function test_comments_lifecycle_and_ownership(): void
    {
        $post = FeedPost::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->alice->id,
            'content' => 'Bài viết thảo luận',
            'audience' => 'organization',
        ]);

        // 1. Bob adds comment
        $commentRes = $this->actingAs($this->bob)
            ->withHeaders($this->headers('c-add-'))
            ->postJson("/api/v2/social/feed/{$post->id}/comments", [
                'content' => 'Đồng ý với quan điểm này!',
            ]);
        $commentRes->assertCreated();
        $commentId = $commentRes->json('comment.id');

        // 2. Stranger tries to edit Bob's comment -> 403
        $unauthEdit = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('c-edit-unauth-'))
            ->putJson("/api/v2/social/feed/comments/{$commentId}", [
                'content' => 'Sửa bình luận trái phép',
            ]);
        $unauthEdit->assertStatus(403);

        // 3. Bob edits his comment
        $authEdit = $this->actingAs($this->bob)
            ->withHeaders($this->headers('c-edit-auth-'))
            ->putJson("/api/v2/social/feed/comments/{$commentId}", [
                'content' => 'Đồng ý 100% với quan điểm này!',
            ]);
        $authEdit->assertOk();
        $this->assertSame('Đồng ý 100% với quan điểm này!', $authEdit->json('comment.content'));

        // 4. Bob deletes his comment
        $authDel = $this->actingAs($this->bob)
            ->withHeaders($this->headers('c-del-auth-'))
            ->deleteJson("/api/v2/social/feed/comments/{$commentId}");
        $authDel->assertOk();
        $this->assertDatabaseMissing('feed_comments', ['id' => $commentId]);
    }

    public function test_reactions_toggle_once_per_type(): void
    {
        $post = FeedPost::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->alice->id,
            'content' => 'Bài viết nhận reaction',
            'audience' => 'organization',
        ]);

        // 1. Bob reacts 'like' -> added
        $reactRes1 = $this->actingAs($this->bob)
            ->withHeaders($this->headers('r1-'))
            ->postJson("/api/v2/social/feed/{$post->id}/react", [
                'type' => 'like',
            ]);
        $reactRes1->assertOk();
        $this->assertSame('added', $reactRes1->json('action'));
        $this->assertSame(1, $reactRes1->json('counts.like'));

        // 2. Bob reacts 'like' again -> removed (toggle off)
        $reactRes2 = $this->actingAs($this->bob)
            ->withHeaders($this->headers('r2-'))
            ->postJson("/api/v2/social/feed/{$post->id}/react", [
                'type' => 'like',
            ]);
        $reactRes2->assertOk();
        $this->assertSame('removed', $reactRes2->json('action'));
        $this->assertNull($reactRes2->json('counts.like'));

        // 3. Bob reacts 'celebrate'
        $reactRes3 = $this->actingAs($this->bob)
            ->withHeaders($this->headers('r3-'))
            ->postJson("/api/v2/social/feed/{$post->id}/react", [
                'type' => 'celebrate',
            ]);
        $reactRes3->assertOk();
        $this->assertSame('added', $reactRes3->json('action'));
        $this->assertSame(1, $reactRes3->json('counts.celebrate'));
    }

    public function test_reporting_and_audited_moderation_hide_and_restore(): void
    {
        $post = FeedPost::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->alice->id,
            'content' => 'Nội dung gây tranh cãi',
            'audience' => 'organization',
        ]);

        // 1. Stranger reports post
        $reportRes = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('rep-sub-'))
            ->postJson("/api/v2/social/feed/{$post->id}/report", [
                'reason' => 'Nội dung spam quảng cáo sai sự thật',
            ]);
        $reportRes->assertCreated();
        $this->assertDatabaseHas('feed_reports', [
            'reportable_id' => $post->id,
            'reason' => 'Nội dung spam quảng cáo sai sự thật',
            'status' => 'pending',
        ]);

        // 2. Stranger (ordinary customer) tries to hide post -> 403 Forbidden
        $unauthHide = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('hide-unauth-'))
            ->postJson("/api/v2/social/feed/{$post->id}/hide", [
                'reason' => 'Tôi muốn giấu bài này',
            ]);
        $unauthHide->assertStatus(403);
        $this->assertFalse($post->fresh()->is_hidden);

        // 3. Authorized Moderator hides post with audit reason
        $modHide = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('hide-auth-'))
            ->postJson("/api/v2/social/feed/{$post->id}/hide", [
                'reason' => 'Vi phạm tiêu chuẩn cộng đồng về quảng cáo',
            ]);
        $modHide->assertOk();
        $this->assertTrue($post->fresh()->is_hidden);

        // Audit moderation record created
        $this->assertDatabaseHas('moderation_records', [
            'organization_id' => $this->tenant->id,
            'actor_id' => $this->moderator->id,
            'action' => 'content_hidden',
            'reason' => 'Vi phạm tiêu chuẩn cộng đồng về quảng cáo',
        ]);

        // Scenario: Moderation -> ordinary readers cannot see it
        $readerShow = $this->actingAs($this->bob)
            ->withHeaders($this->headers('show-hid-'))
            ->getJson("/api/v2/social/feed/{$post->id}");
        $readerShow->assertStatus(404);

        // 4. Moderator restores post
        $modRestore = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('restore-auth-'))
            ->postJson("/api/v2/social/feed/{$post->id}/restore", [
                'reason' => 'Đã xác minh lại, bài viết hợp lệ sau khi giải trình',
            ]);
        $modRestore->assertOk();
        $this->assertFalse($post->fresh()->is_hidden);

        // Reader can see it again
        $readerShowAgain = $this->actingAs($this->bob)
            ->withHeaders($this->headers('show-res-'))
            ->getJson("/api/v2/social/feed/{$post->id}");
        $readerShowAgain->assertOk();
    }

    public function test_controlled_friends_only_report_review_and_unauthorized_access(): void
    {
        // 1. Alice creates a friends-only post (visible only to friends: Bob)
        $privatePost = FeedPost::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->alice->id,
            'content' => 'Nội dung riêng tư chỉ bạn bè xem',
            'audience' => 'friends',
        ]);

        // Moderator browsing ordinary tenant feed does NOT see Alice's friends-only post
        $modFeed = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('mod-feed-'))
            ->getJson('/api/v2/social/feed');
        $modFeed->assertOk();
        $this->assertFalse(collect($modFeed->json('data'))->contains('id', $privatePost->id));

        // 2. Bob (friend) reports the private post
        $reportRes = $this->actingAs($this->bob)
            ->withHeaders($this->headers('bob-rep-'))
            ->postJson("/api/v2/social/feed/{$privatePost->id}/report", [
                'reason' => 'Bài viết chứa ngôn từ xúc phạm',
            ]);
        $reportRes->assertCreated();
        $reportId = $reportRes->json('report.id');

        // 3. Unauthorized access check: ordinary customer (Stranger) tries to access moderation reports -> 403
        $strangerReports = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('s-rep-unauth-'))
            ->getJson('/api/v2/social/feed/reports');
        $strangerReports->assertStatus(403);

        // 4. Authorized moderator reviews reports and can inspect the reported private post
        $modReports = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('mod-rep-auth-'))
            ->getJson('/api/v2/social/feed/reports');
        $modReports->assertOk();
        $this->assertTrue(collect($modReports->json('data'))->contains('id', $reportId));

        // Moderator inspects report directly through audited moderation path
        $reportDetail = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('mod-rep-det-'))
            ->getJson("/api/v2/social/feed/reports/{$reportId}");
        $reportDetail->assertOk();
        $this->assertSame($privatePost->id, $reportDetail->json('report.reportable.id'));
        $this->assertSame('Nội dung riêng tư chỉ bạn bè xem', $reportDetail->json('report.reportable.content'));

        // Moderator can now also view the post directly via audited moderation path
        $modShowPost = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('mod-show-'))
            ->getJson("/api/v2/social/feed/{$privatePost->id}");
        $modShowPost->assertOk();
        $this->assertSame($privatePost->id, $modShowPost->json('post.id'));

        // But stranger STILL cannot view the post -> 404
        $strangerShow = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('s-show2-'))
            ->getJson("/api/v2/social/feed/{$privatePost->id}");
        $strangerShow->assertStatus(404);
    }
}
