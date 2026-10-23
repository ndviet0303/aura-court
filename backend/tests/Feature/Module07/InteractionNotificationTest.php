<?php

namespace Tests\Feature\Module07;

use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\ServesCustomers;
use Tests\TestCase;

/** FR-119, BR-161: a new message tells the other side in the app only, never by email. */
class InteractionNotificationTest extends TestCase
{
    use RefreshDatabase;
    use ServesCustomers;

    public function test_fr_119_br_161_a_customer_message_reaches_the_owner_and_assigned_staff_in_app_only(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $staff = $this->staffOf($court->venue);
        $unassigned = $this->staffOf($this->openVenue(['organization_id' => $court->venue->organization_id]));
        $customer = User::factory()->create();
        $id = $this->actingAs($customer)->postJson('/api/conversations', ['type' => 'customer_venue', 'venue_id' => $court->venue_id])->json('conversation.id');

        $this->postJson("/api/conversations/{$id}/messages", ['body' => 'Xin chào'])->assertCreated();

        $notified = OutboxMessage::where('event_type', 'chat.message')->get();
        $this->assertEqualsCanonicalizing([$owner->id, $staff->id], $notified->pluck('payload.user_id')->all());
        $this->assertNotContains($unassigned->id, $notified->pluck('payload.user_id')->all());
        $this->assertSame([['database']], $notified->pluck('payload.channels')->unique()->values()->all());
        $this->assertSame("/messages?conversation={$id}", $notified->first()->payload['action_url']);

        $this->actingAs($staff)->postJson("/api/conversations/{$id}/messages", ['body' => 'Chào bạn'])->assertCreated();
        $this->assertSame(1, OutboxMessage::where('event_type', 'chat.message')->where('payload->user_id', $customer->id)->count());
    }
}
