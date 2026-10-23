<?php

namespace Tests\Feature\Module07;

use App\Models\FeedPost;
use App\Models\OutboxMessage;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Module07\Concerns\ServesCustomers;
use Tests\TestCase;

/** FR-112..FR-114, BR-144..BR-146 (UC-7.4–UC-7.8): the platform-wide feed (D12) with share and matchmaking posts. */
class CommunityFeedTest extends TestCase
{
    use RefreshDatabase;
    use ServesCustomers;

    private User $author;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['name' => 'Tác giả']);
        $this->other = User::factory()->create(['name' => 'Người khác']);
    }

    public function test_fr_112_br_144_anyone_reads_the_feed_and_hidden_posts_never_reach_a_reader(): void
    {
        $shown = FeedPost::create(['user_id' => $this->author->id, 'type' => FeedPost::TYPE_SHARE, 'content' => 'Sân mới đẹp quá']);
        FeedPost::create(['user_id' => $this->author->id, 'type' => FeedPost::TYPE_MATCHMAKING, 'content' => 'Thiếu 2 người', 'sport' => 'badminton', 'address' => 'Cầu Giấy', 'play_at' => now()->addDay()]);
        $hidden = FeedPost::create(['user_id' => $this->author->id, 'type' => FeedPost::TYPE_SHARE, 'content' => 'Spam', 'is_hidden' => true]);

        $this->getJson('/api/social/feed')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.is_mine', false);
        $this->getJson('/api/social/feed?type=matchmaking')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.address', 'Cầu Giấy');
        $this->getJson("/api/social/feed/{$shown->id}")->assertOk()->assertJsonPath('post.author.name', 'Tác giả');
        $this->getJson("/api/social/feed/{$hidden->id}")->assertNotFound();

        $this->actingAs($this->author)->getJson('/api/social/feed')->assertJsonPath('data.0.is_mine', true);
        $this->actingAs($this->other)->postJson('/api/social/feed', ['type' => 'share', 'content' => 'Xin chào'])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/social/feed', ['type' => 'share', 'content' => 'Khách vãng lai'])->assertUnauthorized();
    }

    public function test_fr_113_br_145_a_post_holds_5000_characters_and_10_uploaded_images(): void
    {
        Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        $this->actingAs($this->author);
        $url = $this->postJson('/api/uploads/community-images', ['image' => UploadedFile::fake()->image('a.webp')])->assertCreated()->json('url');
        $this->postJson('/api/uploads/community-images', ['image' => UploadedFile::fake()->image('a.gif')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson('/api/uploads/community-images', ['image' => UploadedFile::fake()->image('a.png')->size(5121)])->assertUnprocessable();

        $this->post_(['content' => str_repeat('a', 5000), 'images' => array_fill(0, 10, $url)])->assertCreated()->assertJsonCount(10, 'post.images');
        $this->post_(['content' => str_repeat('a', 5001)])->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->post_(['content' => 'Ảnh', 'images' => array_fill(0, 11, $url)])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->post_(['content' => 'Ảnh ngoài', 'images' => ['https://example.com/a.jpg']])->assertUnprocessable()->assertJsonValidationErrors('images.0');
    }

    public function test_br_146_a_matchmaking_post_needs_sport_time_and_place_and_books_nothing(): void
    {
        $court = $this->bookableCourt();
        $reservations = Reservation::count();
        $this->actingAs($this->author);

        $this->post_(['type' => 'matchmaking', 'content' => 'Thiếu người'])
            ->assertUnprocessable()->assertJsonValidationErrors(['sport', 'play_at', 'venue_id']);
        $this->post_(['type' => 'matchmaking', 'content' => 'Thiếu người', 'sport' => 'badminton', 'play_at' => '2026-12-01 19:00', 'address' => 'Nhà văn hoá phường'])
            ->assertCreated()->assertJsonPath('post.venue', null)->assertJsonPath('post.address', 'Nhà văn hoá phường');
        $id = $this->post_(['type' => 'matchmaking', 'content' => 'Kèo tối', 'sport' => 'badminton', 'play_at' => '2026-12-01 19:00', 'venue_id' => $court->venue_id, 'court_code' => $court->code])
            ->assertCreated()->assertJsonPath('post.venue.id', $court->venue_id)->assertJsonPath('post.court_code', $court->code)->json('post.id');
        $this->post_(['type' => 'share', 'content' => 'Chia sẻ', 'sport' => 'tennis', 'address' => 'Bỏ qua'])
            ->assertCreated()->assertJsonPath('post.sport', null)->assertJsonPath('post.address', null);

        $this->assertSame($reservations, Reservation::count());
        $this->actingAs($this->other)->postJson("/api/social/feed/{$id}/join")->assertNotFound();
    }

    public function test_br_144_only_the_author_edits_or_deletes_a_post_even_against_a_venue_owner(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $id = $this->actingAs($this->author)->post_(['content' => 'Bài gốc', 'type' => 'matchmaking', 'sport' => 'badminton', 'play_at' => '2026-12-01 19:00', 'venue_id' => $court->venue_id])->json('post.id');

        foreach ([$this->other, $owner] as $user) {
            $this->actingAs($user)->putJson("/api/social/feed/{$id}", ['type' => 'share', 'content' => 'Sửa'])->assertForbidden()->assertJsonPath('code', 'FEED_NOT_AUTHOR');
            $this->actingAs($user)->deleteJson("/api/social/feed/{$id}")->assertForbidden();
        }
        $this->actingAs($this->author)->putJson("/api/social/feed/{$id}", ['type' => 'share', 'content' => 'Đã sửa'])
            ->assertOk()->assertJsonPath('post.content', 'Đã sửa')->assertJsonPath('post.type', 'share')->assertJsonPath('post.venue', null);
        $this->actingAs($this->author)->deleteJson("/api/social/feed/{$id}")->assertNoContent();
        $this->getJson("/api/social/feed/{$id}")->assertNotFound();
    }

    public function test_fr_114_d11_a_comment_notifies_the_post_author_but_never_for_their_own_comment(): void
    {
        $id = $this->actingAs($this->author)->post_(['content' => 'Ai đi đánh không?'])->json('post.id');
        $this->actingAs($this->author)->postJson("/api/social/feed/{$id}/comments", ['content' => 'Tự bình luận'])->assertCreated();
        $this->assertSame(0, OutboxMessage::where('event_type', 'community.comment')->count());

        $comment = $this->actingAs($this->other)->postJson("/api/social/feed/{$id}/comments", ['content' => 'Mình đi'])
            ->assertCreated()->assertJsonPath('comment.post_id', $id)->assertJsonPath('comment.is_mine', true)->json('comment.id');
        $notice = OutboxMessage::where('event_type', 'community.comment')->sole();
        $this->assertSame($this->author->id, $notice->payload['user_id']);
        $this->assertSame("/community/posts/{$id}", $notice->payload['action_url']);

        $this->actingAs($this->other)->postJson("/api/social/feed/{$id}/comments", ['content' => str_repeat('a', 2001)])->assertUnprocessable();
        $this->actingAs($this->author)->putJson("/api/social/feed/comments/{$comment}", ['content' => 'Sửa hộ'])->assertForbidden()->assertJsonPath('code', 'FEED_NOT_AUTHOR');
        $this->actingAs($this->other)->putJson("/api/social/feed/comments/{$comment}", ['content' => 'Mình đi nhé'])->assertOk()->assertJsonPath('comment.content', 'Mình đi nhé');
        $this->actingAs($this->other)->deleteJson("/api/social/feed/comments/{$comment}")->assertNoContent();
        $this->getJson("/api/social/feed/{$id}")->assertJsonPath('post.comments_count', 1);
    }

    /** @param  array<string, mixed>  $body */
    private function post_(array $body): TestResponse
    {
        return $this->postJson('/api/social/feed', $body + ['type' => 'share']);
    }
}
