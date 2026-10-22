<?php

namespace Tests\Feature\Module02;

use App\Models\Court;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Feature\Module02\Concerns\SeedsBookings;
use Tests\TestCase;

/** FR-30, FR-31, BR-27, BR-28, BR-30 (new court); design D3, D9, D10. UC-2.8, UC-2.9. */
class CourtSetupTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;
    use SeedsBookings;

    private function store(Venue $venue, array $body = [])
    {
        return $this->actingAs($this->ownerOf($venue->organization))
            ->postJson('/api/admin/courts', $body + ['venue_id' => $venue->id, 'sport_type' => 'pickleball', 'code' => 'P1']);
    }

    public function test_fr_30_new_court_gets_its_own_id_and_starts_inactive(): void
    {
        $venue = $this->openVenue();

        $id = $this->store($venue, ['description' => 'Sân trong nhà'])->assertCreated()->assertJsonPath('status', Court::STATUS_INACTIVE)->json('id');
        $other = $this->store($venue, ['code' => 'P2'])->assertCreated()->json('id');

        $this->assertNotSame($id, $other);
        $this->assertSame(['pickleball', Court::STATUS_INACTIVE], [Court::find($id)->sport_type, Court::find($id)->status]);
        $inactiveVenue = $this->openVenue(['status' => Venue::STATUS_INACTIVE]);
        $this->store($inactiveVenue)->assertCreated();
        $closedVenue = $this->openVenue(['status' => Venue::STATUS_CLOSED]);
        $this->store($closedVenue)->assertStatus(422)->assertJsonValidationErrors('venue_id');
    }

    public function test_br_27_code_is_1_to_50_characters_and_unique_in_the_venue_ignoring_case_and_spaces(): void
    {
        $venue = $this->openVenue();
        $this->store($venue, ['code' => 'Sân A'])->assertCreated();

        $this->store($venue, ['code' => '  sân a  '])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->store($venue, ['code' => '   '])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->store($venue, ['code' => str_repeat('a', 51)])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->store($venue, ['code' => 'B', 'description' => str_repeat('a', 2001)])->assertStatus(422)->assertJsonValidationErrors('description');
        $this->store($venue, ['code' => str_repeat('a', 50)])->assertCreated();
        $this->store($this->openVenue(['organization_id' => $venue->organization_id]), ['code' => 'Sân A'])->assertCreated();
        $this->assertSame('Sân A', Court::query()->where('venue_id', $venue->id)->orderBy('id')->value('code'));
    }

    public function test_br_25_staff_and_outsiders_cannot_create_or_edit_courts(): void
    {
        $court = $this->bookableCourt();
        $venue = $court->venue;

        $this->actingAs($this->staffOf($venue))->postJson('/api/admin/courts', ['venue_id' => $venue->id, 'sport_type' => 'badminton', 'code' => 'X'])->assertForbidden();
        $this->actingAs($this->staffOf($venue))->putJson("/api/admin/courts/{$court->id}", ['code' => 'X'])->assertForbidden();
        $this->actingAs($this->ownerOf($this->openVenue()->organization))->putJson("/api/admin/courts/{$court->id}", ['code' => 'X'])->assertForbidden();
        $this->assertNotSame('X', $court->fresh()->code);
    }

    public function test_br_28_update_changes_code_description_images_only(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $sibling = $this->activeCourt($court->venue, ['code' => 'S9']);
        $url = "/api/admin/courts/{$court->id}";

        $this->actingAs($owner)->putJson($url, ['venue_id' => $this->openVenue(['organization_id' => $court->venue->organization_id])->id])->assertStatus(422)->assertJsonValidationErrors('venue_id');
        $this->actingAs($owner)->putJson($url, ['sport_type' => 'tennis'])->assertStatus(422)->assertJsonValidationErrors('sport_type');
        $this->actingAs($owner)->putJson($url, ['code' => 's9'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->actingAs($owner)->putJson($url, ['venue_id' => $court->venue_id, 'sport_type' => 'badminton', 'code' => $court->code])->assertOk();
        $this->assertSame([$court->venue_id, 'badminton'], [$court->fresh()->venue_id, $court->fresh()->sport_type]);
        $this->assertSame('S9', $sibling->fresh()->code);
    }

    public function test_fr_31_a_new_code_shows_on_existing_bookings_with_everything_else_kept(): void
    {
        $court = $this->bookableCourt();
        $booking = $this->booking($court, $this->tomorrowAt(10));
        $before = $booking->only(['id', 'court_id', 'total_amount', 'status']);
        $slot = $booking->slots()->sole()->only(['court_id', 'starts_at', 'ends_at']);

        $this->actingAs($this->ownerOf($court->venue->organization))->putJson("/api/admin/courts/{$court->id}", ['code' => 'Sân VIP'])->assertOk()->assertJsonPath('id', $court->id);

        $this->assertSame($before, $booking->fresh()->only(['id', 'court_id', 'total_amount', 'status']));
        $this->assertEquals($slot, $booking->slots()->sole()->only(['court_id', 'starts_at', 'ends_at']));
        $this->actingAs($booking->user)->getJson('/api/me/reservations')->assertOk()->assertJsonFragment(['code' => 'Sân VIP']);
    }

    public function test_fr_31_courts_are_never_deleted(): void
    {
        $court = $this->bookableCourt();

        $this->actingAs($this->ownerOf($court->venue->organization))->deleteJson("/api/admin/courts/{$court->id}")->assertNotFound();
        $this->assertNotNull($court->fresh());
    }
}
