<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\MatchInvitation;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OutboxMessage;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Infrastructure\OutboxDispatcher;
use App\Services\Infrastructure\OutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SocialNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private User $alice;

    private User $bob;

    private User $host;

    private User $invitee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Notifications Arena',
            'slug' => 'aura-notif-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->alice = User::factory()->create(['name' => 'Alice Sender', 'email' => 'alice@notif.vn']);
        $this->bob = User::factory()->create(['name' => 'Bob Recipient', 'email' => 'bob@notif.vn']);
        $this->host = User::factory()->create(['name' => 'Host User', 'email' => 'host@notif.vn']);
        $this->invitee = User::factory()->create(['name' => 'Invitee Player', 'email' => 'invitee@notif.vn']);

        foreach ([$this->alice, $this->bob, $this->host, $this->invitee] as $u) {
            OrganizationMember::create([
                'organization_id' => $this->tenant->id,
                'user_id' => $u->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
        }
    }

    private function headers(string $prefix = 'notif-'): array
    {
        return [
            'X-Organization-Id' => (string) $this->tenant->id,
            'Idempotency-Key' => uniqid($prefix),
        ];
    }

    private function processPendingOutboxMessages(): void
    {
        $messages = OutboxMessage::where('status', 'pending')->get();
        $dispatcher = app(OutboxDispatcher::class);
        $service = app(OutboxService::class);

        foreach ($messages as $msg) {
            $claimed = $service->claim($msg);
            if ($claimed) {
                $dispatcher->dispatch($claimed);
                $service->sent($claimed);
            }
        }
    }

    public function test_friendship_request_produces_recipient_scoped_notification(): void
    {
        // Alice sends request to Bob
        $res = $this->actingAs($this->alice)
            ->withHeaders($this->headers('req-'))
            ->postJson('/api/social/friends/requests', [
                'addressee_id' => $this->bob->id,
            ]);
        $res->assertCreated();

        // Check Outbox message was enqueued for Bob (recipient)
        $outbox = OutboxMessage::where('channel', 'notification')
            ->where('event_type', 'social.friendship_request')
            ->first();

        $this->assertNotNull($outbox);
        $this->assertSame($this->bob->id, $outbox->payload['user_id']);

        // Process outbox delivery
        $this->processPendingOutboxMessages();

        // Bob has 1 notification, Alice has 0 (strictly recipient-scoped)
        $this->assertSame(1, $this->bob->notifications()->count());
        $this->assertSame(0, $this->alice->notifications()->count());

        $bobNotif = $this->bob->notifications()->first();
        $this->assertSame('Lời mời kết bạn mới', $bobNotif->data['title']);
        $this->assertStringContainsString('Alice Sender', $bobNotif->data['body']);
    }

    public function test_duplicate_delivery_attempts_do_not_duplicate_logical_notification(): void
    {
        // Alice sends request to Bob
        $this->actingAs($this->alice)
            ->withHeaders($this->headers('dup-req-'))
            ->postJson('/api/social/friends/requests', [
                'addressee_id' => $this->bob->id,
            ]);

        $this->processPendingOutboxMessages();
        $this->assertSame(1, $this->bob->notifications()->count());

        // Simulate retrying the same outbox enqueue with the same message_key
        $friendship = Friendship::where('requester_id', $this->alice->id)->where('addressee_id', $this->bob->id)->firstOrFail();
        $key = "friendship_request:{$friendship->id}";

        $outboxService = app(OutboxService::class);
        $retryMsg = $outboxService->enqueue([
            'message_key' => $key,
            'channel' => 'notification',
            'event_type' => 'social.friendship_request',
        ], [
            'user_id' => $this->bob->id,
            'notification' => ['title' => 'Dup test'],
        ]);

        // OutboxService reused the existing message and did not create a new one
        $this->assertSame(1, OutboxMessage::where('message_key', $key)->count());

        // Dispatch again
        $this->processPendingOutboxMessages();

        // Bob still has exactly 1 notification
        $this->assertSame(1, $this->bob->notifications()->count());
    }

    public function test_transaction_rollback_prevents_notification_delivery(): void
    {
        // Enqueue within a rolled-back transaction
        try {
            DB::transaction(function () {
                $friendship = Friendship::create([
                    'requester_id' => $this->alice->id,
                    'addressee_id' => $this->bob->id,
                    'status' => 'pending',
                ]);

                DB::afterCommit(function () use ($friendship) {
                    app(OutboxService::class)->enqueue([
                        'message_key' => "rollback_test:{$friendship->id}",
                        'channel' => 'notification',
                        'event_type' => 'social.test',
                    ], [
                        'user_id' => $this->bob->id,
                        'notification' => ['title' => 'Should never be sent'],
                    ]);
                });

                throw new \RuntimeException('Simulated transaction rollback!');
            });
        } catch (\RuntimeException $e) {
            // Transaction rolled back as expected
        }

        // Outbox message was NEVER enqueued
        $this->assertSame(0, OutboxMessage::where('message_key', 'like', 'rollback_test:%')->count());
        $this->assertSame(0, $this->bob->notifications()->count());
    }

    public function test_match_invitation_persisted_notification_and_reload(): void
    {
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->host->id,
            'title' => 'Trận đấu cầu lông 2v2',
            'visibility' => 'invite_only',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        // Host invites player
        $inviteRes = $this->actingAs($this->host)
            ->withHeaders($this->headers('inv-'))
            ->postJson("/api/social/matches/{$match->id}/invite", [
                'invitee_id' => $this->invitee->id,
            ]);
        $inviteRes->assertCreated();

        // Process notification delivery
        $this->processPendingOutboxMessages();

        // Scenario: Notification reload -> Invitee reloads after committed match invitation
        $notifListRes = $this->actingAs($this->invitee)->getJson('/api/notifications');
        $notifListRes->assertOk();
        $this->assertSame(1, $notifListRes->json('unread'));
        $this->assertSame(1, $notifListRes->json('notifications.total'));

        $notifItem = $notifListRes->json('notifications.data.0');
        $this->assertSame('Lời mời tham gia trận đấu', $notifItem['data']['title']);
        $this->assertStringContainsString('Trận đấu cầu lông 2v2', $notifItem['data']['body']);
        $this->assertSame('match_invitation', $notifItem['data']['type']);

        // Invitee marks notification as read
        $readRes = $this->actingAs($this->invitee)->postJson("/api/notifications/{$notifItem['id']}/read");
        $readRes->assertOk();

        // Unread count becomes 0
        $reloadedList = $this->actingAs($this->invitee)->getJson('/api/notifications');
        $this->assertSame(0, $reloadedList->json('unread'));
    }

    public function test_match_invitation_response_notifies_match_creator(): void
    {
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->host->id,
            'title' => 'Trận giao lưu',
            'visibility' => 'invite_only',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        $invitation = MatchInvitation::create([
            'organization_id' => $this->tenant->id,
            'match_post_id' => $match->id,
            'invited_by' => $this->host->id,
            'invitee_id' => $this->invitee->id,
            'status' => 'pending',
        ]);

        // Invitee accepts
        $respRes = $this->actingAs($this->invitee)
            ->withHeaders($this->headers('resp-'))
            ->postJson("/api/social/invitations/{$invitation->id}/respond", [
                'status' => 'accepted',
            ]);
        $respRes->assertOk();

        $this->processPendingOutboxMessages();

        // Host received notification about Invitee's response
        $this->assertSame(1, $this->host->notifications()->count());
        $hostNotif = $this->host->notifications()->first();
        $this->assertSame('Phản hồi lời mời trận đấu', $hostNotif->data['title']);
        $this->assertStringContainsString('chấp nhận', $hostNotif->data['body']);
    }

    public function test_tournament_participant_registration_enqueues_notification(): void
    {
        $manager = User::factory()->create(['name' => 'Manager Tour']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $manager->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $tournament = Tournament::create([
            'organization_id' => $this->tenant->id,
            'created_by' => $manager->id,
            'name' => 'Giải Cầu Lông Mở Rộng 2026',
            'format' => 'single_elimination',
            'status' => 'open',
        ]);

        // Manager adds Alice as participant
        $addRes = $this->actingAs($manager)
            ->withHeaders($this->headers('tour-p-'))
            ->postJson("/api/tournaments/{$tournament->id}/participants", [
                'user_id' => $this->alice->id,
            ]);
        $addRes->assertCreated();

        $this->processPendingOutboxMessages();

        // Alice received notification about tournament registration
        $this->assertSame(1, $this->alice->notifications()->count());
        $aliceNotif = $this->alice->notifications()->first();
        $this->assertSame('Đăng ký giải đấu thành công', $aliceNotif->data['title']);
        $this->assertStringContainsString('Giải Cầu Lông Mở Rộng 2026', $aliceNotif->data['body']);
    }
}
