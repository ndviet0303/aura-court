<?php

namespace Tests\Feature\Module02;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-25, FR-29, BR-25 (reads). UC-2.1, UC-2.2, UC-2.6, UC-2.7. */
class VenueReadAccessTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    private function venuesUrl(int $organizationId, string $suffix = ''): string
    {
        return "/api/partner/organizations/{$organizationId}/venues{$suffix}";
    }

    public function test_fr_25_owner_lists_every_venue_and_staff_only_the_assigned_ones(): void
    {
        $assigned = $this->openVenue();
        $other = $this->openVenue(['organization_id' => $assigned->organization_id]);
        $owner = $this->ownerOf($assigned->organization);
        $staff = $this->staffOf($assigned);

        $ids = fn (User $user) => collect($this->actingAs($user)->getJson($this->venuesUrl($assigned->organization_id))->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$assigned->id, $other->id], $ids($owner));
        $this->assertSame([$assigned->id], $ids($staff));

        $this->actingAs($owner)->getJson($this->venuesUrl($assigned->organization_id, "/{$other->id}"))->assertOk()->assertJsonPath('data.id', $other->id);
        $this->actingAs($staff)->getJson($this->venuesUrl($assigned->organization_id, "/{$assigned->id}"))->assertOk();
        $this->actingAs($staff)->getJson($this->venuesUrl($assigned->organization_id, "/{$other->id}"))->assertForbidden();
    }

    public function test_br_25_outsiders_customers_and_platform_admins_cannot_read(): void
    {
        $venue = $this->openVenue();
        $outsider = $this->ownerOf($this->openVenue()->organization);

        foreach ([$outsider, User::factory()->create(), $this->platformAdmin()] as $user) {
            $this->actingAs($user)->getJson($this->venuesUrl($venue->organization_id))->assertForbidden();
            $this->actingAs($user)->getJson($this->venuesUrl($venue->organization_id, "/{$venue->id}"))->assertForbidden();
        }
        // The venue is read only through its own organization.
        $this->actingAs($outsider)->getJson("/api/partner/organizations/{$outsider->organizationMembers()->value('organization_id')}/venues/{$venue->id}")->assertNotFound();
    }

    public function test_fr_29_court_detail_shows_code_sport_description_images_schedule_and_status(): void
    {
        $court = $this->bookableCourt();
        $court->forceFill(['description' => ['vi' => 'Sân thảm mới', 'en' => 'Sân thảm mới'], 'images' => ['/courts/a.jpg']])->save();
        $venue = $court->venue;
        $url = $this->venuesUrl($venue->organization_id, "/{$venue->id}/courts/{$court->id}");

        $this->actingAs($this->staffOf($venue))->getJson($url)->assertOk()
            ->assertJsonPath('data.code', $court->code)
            ->assertJsonPath('data.sport_type', 'badminton')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.images', ['/courts/a.jpg'])
            ->assertJsonPath('data.service_schedule_inherited', true)
            ->assertJsonStructure(['data' => ['description', 'service_schedule' => ['weekly_hours']]]);

        $foreign = $this->activeCourt($this->openVenue(['organization_id' => $venue->organization_id]));
        $this->actingAs($this->ownerOf($venue->organization))->getJson($this->venuesUrl($venue->organization_id, "/{$venue->id}/courts/{$foreign->id}"))->assertNotFound();
        $this->actingAs($this->staffOf($foreign->venue))->getJson($url)->assertForbidden();
    }

    public function test_fr_29_court_list_is_scoped_to_the_assigned_venues(): void
    {
        $court = $this->bookableCourt();
        $sibling = $this->activeCourt($this->openVenue(['organization_id' => $court->venue->organization_id]));
        $staff = $this->staffOf($court->venue);

        $ids = collect($this->actingAs($staff)->getJson('/api/admin/courts')->assertOk()->json())->pluck('id')->all();
        $this->assertSame([$court->id], $ids);
        $this->actingAs($staff)->getJson("/api/admin/courts?venue_id={$sibling->venue_id}")->assertForbidden();
        $owned = collect($this->actingAs($this->ownerOf($court->venue->organization))->getJson('/api/admin/courts')->assertOk()->json())->pluck('id')->sort()->values()->all();
        $this->assertSame([$court->id, $sibling->id], $owned);
    }

    public function test_br_25_venue_realtime_channel_follows_the_read_scope(): void
    {
        $venue = $this->openVenue();
        $other = $this->openVenue(['organization_id' => $venue->organization_id]);
        $authorize = Broadcast::driver()->getChannels()['venue.{venueId}'];
        $staff = $this->staffOf($venue);

        $this->assertTrue($authorize($this->ownerOf($venue->organization), $other->id));
        $this->assertTrue($authorize($staff, $venue->id));
        $this->assertFalse($authorize($staff, $other->id));
        $this->assertFalse($authorize($this->platformAdmin(), $venue->id));
        $staff->forceFill(['status' => 'locked'])->save();
        $this->assertFalse($authorize($staff->fresh(), $venue->id));
    }
}
