<?php

namespace Tests\Feature\Module02;

use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-33 (coverage gate), FR-34, FR-35, FR-43, BR-32, BR-33, BR-41 (resume checks); design D1, D3, D4. UC-2.11, UC-2.12, UC-2.13, UC-2.23. */
class OpeningConditionsTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    private function venueCommand(Venue $venue, string $command, array $body = []): TestResponse
    {
        return $this->actingAs($this->ownerOf($venue->organization))
            ->postJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/{$command}", $body);
    }

    private function courtCommand(Court $court, string $command, array $body = []): TestResponse
    {
        return $this->actingAs($this->ownerOf($court->venue->organization))->postJson("/api/admin/courts/{$court->id}/{$command}", $body);
    }

    /** @return list<string> */
    private function missing(TestResponse $response): array
    {
        return collect($response->assertStatus(422)->json('missing_items'))->pluck('code')->unique()->values()->all();
    }

    private function inactiveVenue(): Venue
    {
        return Venue::factory()->create(['status' => Venue::STATUS_INACTIVE, 'name' => 'Sân Gò Vấp', 'address' => '45 Quang Trung, Gò Vấp', 'phone' => '0281234567', 'email' => 'govap@example.test']);
    }

    public function test_fr_34_open_venue_lists_what_is_missing(): void
    {
        $venue = $this->inactiveVenue();
        $venue->forceFill(['email' => null])->save();

        $response = $this->venueCommand($venue, 'open');
        $this->assertSame(['venue_information', 'operating_hours'], $this->missing($response));
        $this->assertSame('email', collect($response->json('missing_items'))->firstWhere('code', 'venue_information')['field']);
        $this->assertSame(Venue::STATUS_INACTIVE, $venue->fresh()->status);
    }

    public function test_br_33_opening_a_venue_does_not_open_its_courts(): void
    {
        $venue = $this->inactiveVenue();
        $this->weeklyHours($venue);
        $court = $this->activeCourt($venue, ['status' => Court::STATUS_INACTIVE]);

        $this->venueCommand($venue, 'open')->assertOk();

        $this->assertSame([Venue::STATUS_ACTIVE, Court::STATUS_INACTIVE], [$venue->fresh()->status, $court->fresh()->status]);
        $this->venueCommand($venue, 'open')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->actingAs($this->staffOf($venue))->postJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/pause", ['reason' => 'Nghỉ lễ dài ngày'])->assertForbidden();
    }

    public function test_fr_34_a_suspended_venue_cannot_open(): void
    {
        $venue = $this->inactiveVenue();
        $this->weeklyHours($venue);
        $this->actingAs($this->platformAdmin())->postJson("/api/admin/venues/{$venue->id}/suspensions", ['reason' => 'Vi phạm quy định an toàn'])->assertCreated();

        $this->assertSame(['venue_suspended'], $this->missing($this->venueCommand($venue, 'open')));
    }

    public function test_fr_35_open_court_needs_an_active_venue_service_time_and_full_price_coverage(): void
    {
        $venue = $this->inactiveVenue();
        $this->weeklyHours($venue);
        $court = $this->activeCourt($venue, ['status' => Court::STATUS_INACTIVE]);

        $this->assertSame(['venue_not_active', 'price_coverage_gap'], $this->missing($this->courtCommand($court, 'open')));
        $this->venueCommand($venue, 'open')->assertOk();
        $table = $this->priceCourtWith($court, [['06:00', '12:00', 80000]]);
        $gaps = collect($this->courtCommand($court, 'open')->assertStatus(422)->json('missing_items'));
        $this->assertSame(['price_coverage_gap'], $gaps->pluck('code')->unique()->values()->all());
        $this->assertSame('12:00', $gaps->first()['start']);

        $this->publishPriceVersion($table, [['06:00', '12:00', 80000], ['12:00', '22:00', 90000]], now());
        $this->courtCommand($court, 'open')->assertOk();
        $this->assertSame(Court::STATUS_ACTIVE, $court->fresh()->status);
        $this->actingAs($this->staffOf($venue))->postJson("/api/admin/courts/{$court->id}/open")->assertForbidden();
    }

    public function test_fr_35_open_is_not_a_resume(): void
    {
        $court = $this->bookableCourt();
        $court->forceFill(['status' => Court::STATUS_PAUSED])->save();

        $this->courtCommand($court, 'open')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame(Court::STATUS_PAUSED, $court->fresh()->status);
    }

    public function test_fr_43_resume_venue_checks_the_suspension_and_keeps_court_statuses(): void
    {
        $court = $this->bookableCourt();
        $venue = $court->venue;
        $venue->forceFill(['phone' => '0281234567', 'email' => 'san@example.test'])->save();
        $paused = $this->activeCourt($venue, ['status' => Court::STATUS_PAUSED]);
        $this->venueCommand($venue, 'pause', ['reason' => 'Tạm nghỉ sửa mái che'])->assertOk();
        $this->assertSame(Venue::STATUS_PAUSED, $venue->fresh()->status);
        $this->assertSame([Court::STATUS_ACTIVE, Court::STATUS_PAUSED], [$court->fresh()->status, $paused->fresh()->status]);

        $this->actingAs($this->platformAdmin())->postJson("/api/admin/venues/{$venue->id}/suspensions", ['reason' => 'Vi phạm quy định an toàn'])->assertCreated();
        $this->assertSame(['venue_suspended'], $this->missing($this->venueCommand($venue, 'resume')));
        $this->assertSame(Venue::STATUS_PAUSED, $venue->fresh()->status);
    }

    public function test_br_41_resume_venue_takes_effect_at_confirmation_and_restores_nothing_else(): void
    {
        $court = $this->bookableCourt();
        $venue = $court->venue;
        $venue->forceFill(['phone' => '0281234567', 'email' => 'san@example.test'])->save();
        $paused = $this->activeCourt($venue, ['status' => Court::STATUS_PAUSED]);
        $this->venueCommand($venue, 'pause', ['reason' => 'Tạm nghỉ sửa mái che', 'expected_end_at' => now()->addDays(3)->toIso8601String()])->assertOk();
        $this->travel(1)->hours();

        $this->venueCommand($venue, 'resume')->assertOk();

        $this->assertSame(Venue::STATUS_ACTIVE, $venue->fresh()->status);
        $this->assertSame([Court::STATUS_ACTIVE, Court::STATUS_PAUSED], [$court->fresh()->status, $paused->fresh()->status]);
        $stop = OperationalStop::query()->where('venue_id', $venue->id)->sole();
        $this->assertTrue($stop->ended_at->between(now()->subMinute(), now()), 'the stop ends when the owner confirms, not at the expected end');
        $this->getJson('/api/courts')->assertJsonFragment(['id' => $court->id])->assertJsonMissing(['id' => $paused->id]);
    }
}
