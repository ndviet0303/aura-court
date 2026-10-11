<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private User $alice;

    private User $bob;

    private User $charlie;

    private User $eve;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Badminton Club',
            'slug' => 'aura-club-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->alice = User::factory()->create(['name' => 'Alice Nguyen', 'email' => 'alice@test.vn', 'phone' => '0901000001']);
        $this->bob = User::factory()->create(['name' => 'Bob Tran', 'email' => 'bob@test.vn', 'phone' => '0902000002']);
        $this->charlie = User::factory()->create(['name' => 'Charlie Le', 'email' => 'charlie@test.vn', 'phone' => '0903000003']);
        $this->eve = User::factory()->create(['name' => 'Eve Mallor', 'email' => 'eve@test.vn', 'phone' => '0904000004']);

        foreach ([$this->alice, $this->bob, $this->charlie, $this->eve] as $user) {
            OrganizationMember::create([
                'organization_id' => $this->tenant->id,
                'user_id' => $user->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
        }
    }

    private function headers(string $prefix = 'friend-'): array
    {
        return [
            'X-Organization-Id' => (string) $this->tenant->id,
            'Idempotency-Key' => uniqid($prefix),
        ];
    }

    public function test_player_can_send_friendship_request_and_persist_pending_state(): void
    {
        $response = $this->actingAs($this->alice)
            ->withHeaders($this->headers('send-'))
            ->postJson('/api/v2/social/friends/requests', [
                'addressee_id' => $this->bob->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('friendship.requester_id', $this->alice->id);
        $response->assertJsonPath('friendship.addressee_id', $this->bob->id);
        $response->assertJsonPath('friendship.status', 'pending');

        $this->assertDatabaseHas('friendships', [
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'pending',
        ]);

        // Alice sees outgoing request
        $aliceReqs = $this->actingAs($this->alice)
            ->withHeaders($this->headers('req-a-'))
            ->getJson('/api/v2/social/friends/requests');
        $aliceReqs->assertOk();
        $this->assertCount(1, $aliceReqs->json('outgoing'));
        $this->assertCount(0, $aliceReqs->json('incoming'));

        // Bob sees incoming request
        $bobReqs = $this->actingAs($this->bob)
            ->withHeaders($this->headers('req-b-'))
            ->getJson('/api/v2/social/friends/requests');
        $bobReqs->assertOk();
        $this->assertCount(0, $bobReqs->json('outgoing'));
        $this->assertCount(1, $bobReqs->json('incoming'));
        $this->assertSame($this->alice->name, $bobReqs->json('incoming.0.requester.name'));
    }

    public function test_cannot_send_friendship_request_to_self(): void
    {
        $response = $this->actingAs($this->alice)
            ->withHeaders($this->headers('self-'))
            ->postJson('/api/v2/social/friends/requests', [
                'addressee_id' => $this->alice->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_send_duplicate_request_when_pending_exists_same_direction(): void
    {
        Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->alice)
            ->withHeaders($this->headers('dup1-'))
            ->postJson('/api/v2/social/friends/requests', [
                'addressee_id' => $this->bob->id,
            ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('already sent', $response->json('message'));
    }

    public function test_cannot_send_duplicate_request_when_pending_exists_reverse_direction(): void
    {
        // Bob sent request to Alice
        Friendship::create([
            'requester_id' => $this->bob->id,
            'addressee_id' => $this->alice->id,
            'status' => 'pending',
        ]);

        // Alice tries to send request to Bob
        $response = $this->actingAs($this->alice)
            ->withHeaders($this->headers('dup2-'))
            ->postJson('/api/v2/social/friends/requests', [
                'addressee_id' => $this->bob->id,
            ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('pending', $response->json('message'));
    }

    public function test_cannot_send_request_when_already_accepted_friends(): void
    {
        Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->alice)
            ->withHeaders($this->headers('dup3-'))
            ->postJson('/api/v2/social/friends/requests', [
                'addressee_id' => $this->bob->id,
            ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('Already friends', $response->json('message'));
    }

    public function test_addressee_can_accept_friendship_request_and_both_see_persisted_connection(): void
    {
        $friendship = Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->bob)
            ->withHeaders($this->headers('accept-'))
            ->postJson("/api/v2/social/friends/requests/{$friendship->id}/accept");

        $response->assertOk();
        $response->assertJsonPath('friendship.status', 'accepted');

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => 'accepted',
        ]);

        // Alice sees Bob in accepted friends
        $aliceFriends = $this->actingAs($this->alice)
            ->withHeaders($this->headers('list-a-'))
            ->getJson('/api/v2/social/friends');
        $aliceFriends->assertOk();
        $this->assertSame(1, $aliceFriends->json('total'));
        $this->assertSame($this->bob->id, $aliceFriends->json('friends.0.id'));
        $this->assertSame($this->bob->name, $aliceFriends->json('friends.0.name'));

        // Bob sees Alice in accepted friends
        $bobFriends = $this->actingAs($this->bob)
            ->withHeaders($this->headers('list-b-'))
            ->getJson('/api/v2/social/friends');
        $bobFriends->assertOk();
        $this->assertSame(1, $bobFriends->json('total'));
        $this->assertSame($this->alice->id, $bobFriends->json('friends.0.id'));
        $this->assertSame($this->alice->name, $bobFriends->json('friends.0.name'));
    }

    public function test_unauthorized_user_cannot_accept_friendship_request(): void
    {
        $friendship = Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'pending',
        ]);

        // Alice (sender) cannot accept her own request
        $senderAttempt = $this->actingAs($this->alice)
            ->withHeaders($this->headers('unauth1-'))
            ->postJson("/api/v2/social/friends/requests/{$friendship->id}/accept");
        $senderAttempt->assertStatus(403);

        // Eve (third party) cannot accept request meant for Bob
        $eveAttempt = $this->actingAs($this->eve)
            ->withHeaders($this->headers('unauth2-'))
            ->postJson("/api/v2/social/friends/requests/{$friendship->id}/accept");
        $eveAttempt->assertStatus(403);

        // Relationship remains pending
        $this->assertSame('pending', $friendship->fresh()->status);
    }

    public function test_addressee_can_decline_friendship_request(): void
    {
        $friendship = Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->bob)
            ->withHeaders($this->headers('decline-'))
            ->postJson("/api/v2/social/friends/requests/{$friendship->id}/decline");

        $response->assertOk();
        $response->assertJsonPath('friendship.status', 'declined');

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => 'declined',
        ]);

        // Neither sees each other as friends
        $aliceFriends = $this->actingAs($this->alice)->withHeaders($this->headers('f-a-'))->getJson('/api/v2/social/friends');
        $this->assertSame(0, $aliceFriends->json('total'));

        $bobFriends = $this->actingAs($this->bob)->withHeaders($this->headers('f-b-'))->getJson('/api/v2/social/friends');
        $this->assertSame(0, $bobFriends->json('total'));
    }

    public function test_unauthorized_user_cannot_decline_friendship_request(): void
    {
        $friendship = Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->eve)
            ->withHeaders($this->headers('unauth-dec-'))
            ->postJson("/api/v2/social/friends/requests/{$friendship->id}/decline");

        $response->assertStatus(403);
        $this->assertSame('pending', $friendship->fresh()->status);
    }

    public function test_either_friend_can_remove_accepted_friendship(): void
    {
        $friendship = Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'accepted',
        ]);

        // Bob deletes the friendship
        $response = $this->actingAs($this->bob)
            ->withHeaders($this->headers('del-'))
            ->deleteJson("/api/v2/social/friends/{$friendship->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);

        // Neither sees each other as friends anymore
        $aliceFriends = $this->actingAs($this->alice)->withHeaders($this->headers('f-a2-'))->getJson('/api/v2/social/friends');
        $this->assertSame(0, $aliceFriends->json('total'));
    }

    public function test_sender_can_cancel_pending_request(): void
    {
        $friendship = Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'pending',
        ]);

        // Alice cancels the request
        $response = $this->actingAs($this->alice)
            ->withHeaders($this->headers('cancel-'))
            ->deleteJson("/api/v2/social/friends/{$friendship->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
    }

    public function test_unauthorized_third_party_cannot_remove_or_cancel_friendship(): void
    {
        $friendship = Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->eve)
            ->withHeaders($this->headers('unauth-del-'))
            ->deleteJson("/api/v2/social/friends/{$friendship->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('friendships', ['id' => $friendship->id]);
    }

    public function test_listing_friends_filters_by_search_term(): void
    {
        // Alice is friends with Bob and Charlie
        Friendship::create(['requester_id' => $this->alice->id, 'addressee_id' => $this->bob->id, 'status' => 'accepted']);
        Friendship::create(['requester_id' => $this->alice->id, 'addressee_id' => $this->charlie->id, 'status' => 'accepted']);

        // Search for Bob by name
        $searchRes = $this->actingAs($this->alice)
            ->withHeaders($this->headers('search-1-'))
            ->getJson('/api/v2/social/friends?q=Bob');

        $searchRes->assertOk();
        $this->assertSame(1, $searchRes->json('total'));
        $this->assertSame($this->bob->name, $searchRes->json('friends.0.name'));

        // Search for Charlie by phone
        $searchPhone = $this->actingAs($this->alice)
            ->withHeaders($this->headers('search-2-'))
            ->getJson('/api/v2/social/friends?q=0903000003');

        $searchPhone->assertOk();
        $this->assertSame(1, $searchPhone->json('total'));
        $this->assertSame($this->charlie->name, $searchPhone->json('friends.0.name'));
    }

    public function test_declined_request_can_be_re_requested(): void
    {
        $friendship = Friendship::create([
            'requester_id' => $this->alice->id,
            'addressee_id' => $this->bob->id,
            'status' => 'declined',
        ]);

        $response = $this->actingAs($this->alice)
            ->withHeaders($this->headers('re-req-'))
            ->postJson('/api/v2/social/friends/requests', [
                'addressee_id' => $this->bob->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('friendship.status', 'pending');
        $this->assertSame('pending', $friendship->fresh()->status);
    }
}
