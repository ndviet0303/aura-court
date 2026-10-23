<?php

namespace Tests\Feature\Module07;

use App\Models\Conversation;
use App\Models\Court;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Module07\Concerns\ServesCustomers;
use Tests\TestCase;

/** FR-118, BR-145, BR-158, BR-159 (UC-7.13–UC-7.15): customer, venue and partner-support conversations. */
class ChatTest extends TestCase
{
    use RefreshDatabase;
    use ServesCustomers;

    private Court $court;

    private User $owner;

    private User $staff;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->court = $this->bookableCourt();
        $this->owner = $this->ownerOf($this->court->venue->organization);
        $this->staff = $this->staffOf($this->court->venue);
        $this->customer = User::factory()->create(['name' => 'Khách A']);
    }

    public function test_fr_118_br_158_staff_reply_under_the_venue_name_and_only_the_venue_side_sees_who_sent_it(): void
    {
        $venue = $this->court->venue;
        $reservations = Reservation::count();
        $id = $this->open($this->customer, ['type' => 'customer_venue', 'venue_id' => $venue->id])
            ->assertOk()->assertJsonPath('conversation.title', $venue->name)->json('conversation.id');
        $this->send($this->customer, $id, ['body' => 'Sân còn trống tối nay không?'])->assertCreated();

        $this->actingAs($this->owner)->getJson('/api/conversations')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.title', 'Khách A')->assertJsonPath('data.0.side', 'venue');
        $this->send($this->staff, $id, ['body' => 'Còn sân 3 lúc 19h.'])
            ->assertCreated()->assertJsonPath('message.sender_name', $venue->name)->assertJsonPath('message.sent_by', $this->staff->name);
        $this->assertDatabaseHas('conversation_messages', ['conversation_id' => $id, 'sender_user_id' => $this->staff->id, 'sender_side' => 'venue']);

        $this->actingAs($this->customer)->getJson("/api/conversations/{$id}/messages")
            ->assertOk()
            ->assertJsonPath('data.1.sender_name', $venue->name)
            ->assertJsonPath('data.1.sent_by', null)
            ->assertJsonPath('data.0.mine', true);
        $this->actingAs($this->owner)->getJson("/api/conversations/{$id}/messages")->assertJsonPath('data.1.sent_by', $this->staff->name);
        $first = $this->actingAs($this->customer)->getJson("/api/conversations/{$id}/messages")->json('data.0.id');
        $this->getJson("/api/conversations/{$id}/messages?after={$first}")->assertJsonCount(1, 'data');
        // BR-159: talking changes no booking.
        $this->assertSame($reservations, Reservation::count());
    }

    public function test_br_158_unassigned_staff_and_staff_whose_assignment_is_withdrawn_lose_access_on_the_next_request(): void
    {
        $id = $this->open($this->customer, ['type' => 'customer_venue', 'venue_id' => $this->court->venue_id])->json('conversation.id');
        $elsewhere = $this->staffOf($this->openVenue(['organization_id' => $this->court->venue->organization_id]));

        $this->actingAs($elsewhere)->getJson("/api/conversations/{$id}/messages")
            ->assertForbidden()->assertJsonPath('code', 'CONVERSATION_FORBIDDEN');
        $this->actingAs($this->staff)->getJson("/api/conversations/{$id}/messages")->assertOk();

        OrganizationMember::where('user_id', $this->staff->id)->update(['permissions' => ['venue_ids' => []]]);
        $this->getJson("/api/conversations/{$id}/messages")->assertForbidden();
        $this->send($this->staff, $id, ['body' => 'còn không?'])->assertForbidden();
    }

    public function test_fr_118_br_145_messages_are_text_up_to_2000_characters_or_one_image_up_to_5_mb(): void
    {
        Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        $id = $this->open($this->customer, ['type' => 'customer_venue', 'venue_id' => $this->court->venue_id])->json('conversation.id');

        $this->send($this->customer, $id, ['body' => str_repeat('a', 2000)])->assertCreated();
        $this->send($this->customer, $id, ['body' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->send($this->customer, $id, [])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->send($this->customer, $id, ['image' => UploadedFile::fake()->image('a.webp')])
            ->assertCreated()->assertJsonPath('message.body', null);
        $this->assertNotNull($this->getJson("/api/conversations/{$id}/messages")->json('data.1.image_url'));
        $this->send($this->customer, $id, ['image' => UploadedFile::fake()->image('a.gif')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->send($this->customer, $id, ['image' => UploadedFile::fake()->image('a.png')->size(5121)])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_uc_7_13_customers_talk_to_each_other_and_no_one_else_reads_it(): void
    {
        $friend = User::factory()->create(['name' => 'Khách B']);
        $this->open($this->customer, ['type' => 'customer_customer', 'user_id' => $this->customer->id])
            ->assertUnprocessable()->assertJsonPath('code', 'CHAT_WITH_SELF');
        $id = $this->open($this->customer, ['type' => 'customer_customer', 'user_id' => $friend->id])
            ->assertJsonPath('conversation.title', 'Khách B')->json('conversation.id');
        $this->assertSame($id, $this->open($friend, ['type' => 'customer_customer', 'user_id' => $this->customer->id])->json('conversation.id'));
        $this->send($friend, $id, ['body' => 'Chiều nay đánh không?'])->assertCreated()->assertJsonPath('message.sender_name', 'Khách B');

        $this->actingAs($this->owner)->getJson("/api/conversations/{$id}/messages")->assertForbidden();
        $this->actingAs($this->platformAdmin())->getJson("/api/conversations/{$id}/messages")->assertForbidden();
    }

    public function test_uc_7_15_owners_talk_to_platform_admins_under_the_organization_name(): void
    {
        $organization = $this->court->venue->organization;
        $admin = $this->platformAdmin();
        $this->open($this->staff, ['type' => 'owner_admin', 'organization_id' => $organization->id])->assertForbidden();
        $id = $this->open($this->owner, ['type' => 'owner_admin', 'organization_id' => $organization->id])
            ->assertJsonPath('conversation.title', 'Quản trị viên')->json('conversation.id');
        $this->send($this->owner, $id, ['body' => 'Cần hỗ trợ đổi gói.'])->assertCreated();

        $this->actingAs($admin)->getJson('/api/conversations')->assertJsonPath('data.0.title', $organization->name);
        $this->send($admin, $id, ['body' => 'Đã nhận.'])->assertCreated()->assertJsonPath('message.sender_name', 'Quản trị viên');
        $this->actingAs($this->ownerOf($this->openVenue()->organization))->getJson("/api/conversations/{$id}/messages")->assertForbidden();
        $this->assertSame(Conversation::TYPE_SUPPORT, Conversation::find($id)->type);
    }

    /** @param  array<string, mixed>  $data */
    private function open(User $user, array $data): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/conversations', $data);
    }

    /** @param  array<string, mixed>  $data */
    private function send(User $user, int $id, array $data): TestResponse
    {
        return $this->actingAs($user)->post("/api/conversations/{$id}/messages", $data, ['Accept' => 'application/json']);
    }
}
