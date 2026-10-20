<?php

namespace Tests\Feature\Venue;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DraftVenueCourtSetupTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Organization} */
    private function ownerWithOrganization(string $slug): array
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Club '.$slug,
            'slug' => $slug,
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);
        $plan = PlatformPlan::create([
            'code' => 'PLAN-'.$slug,
            'name' => 'Plan '.$slug,
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'max_venues' => 5,
            'max_courts' => 10,
            'terms_version' => '1',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        return [$owner, $org];
    }

    private function venueWithStatus(Organization $org, string $status): Venue
    {
        return Venue::create([
            'organization_id' => $org->id,
            'name' => 'Venue '.$status,
            'slug' => $org->slug.'-'.$status,
            'address' => '1 Tran Thai Tong, Cau Giay',
            'status' => $status,
            'is_active' => in_array($status, ['published', 'active'], true),
        ]);
    }

    public function test_owner_sets_up_the_first_court_of_a_draft_venue_through_the_api(): void
    {
        [$owner, $org] = $this->ownerWithOrganization('draft-court-club');
        Sanctum::actingAs($owner);
        $base = "/api/partner/organizations/{$org->id}/venues";

        $venueId = $this->postJson($base, [
            'name' => 'Sân Nháp Cầu Giấy',
            'address' => '12 Tran Thai Tong, Cau Giay, Ha Noi',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        $weeklyHours = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $weeklyHours[(string) $weekday] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '22:00']]];
        }
        $this->putJson("{$base}/{$venueId}/operating-schedule", [
            'timezone' => 'Asia/Ho_Chi_Minh',
            'weekly_hours' => $weeklyHours,
        ])->assertOk();

        $courtId = $this->postJson('/api/admin/courts', [
            'name' => 'Sân 1',
            'venue_id' => $venueId,
            'sport_type' => 'badminton',
        ])->assertCreated()->assertJsonPath('venue_id', $venueId)->json('id');

        $this->postJson("/api/admin/courts/{$courtId}/prices", [
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
            'price' => 100000,
            'is_active' => true,
        ])->assertCreated();

        // The owner can keep editing the court while the venue is still a draft.
        $this->patchJson("/api/admin/courts/{$courtId}", ['name' => 'Sân 1A'])->assertOk();

        $codes = array_column($this->getJson("{$base}/{$venueId}/readiness")->assertOk()->json('data.missing_items'), 'code');
        $this->assertNotContains('NO_ACTIVE_COURTS', $codes);
        $this->assertNotContains('COURT_PRICES_MISSING', $codes);
        $this->assertNotContains('OPERATING_CALENDAR_REQUIRED', $codes);
        $this->assertSame('draft', Venue::query()->findOrFail($venueId)->status);
    }

    public static function venueStatuses(): array
    {
        return [
            'draft' => ['draft', 201],
            'pending publication' => ['pending_publication', 201],
            'revision required' => ['revision_required', 201],
            'published' => ['published', 201],
            'temporarily closed' => ['temporarily_closed', 201],
            'suspended' => ['suspended', 201],
            'closed' => ['closed', 422],
            'archived' => ['archived', 422],
        ];
    }

    #[DataProvider('venueStatuses')]
    public function test_court_creation_follows_the_venue_status(string $status, int $expected): void
    {
        [$owner, $org] = $this->ownerWithOrganization('status-club');
        $venue = $this->venueWithStatus($org, $status);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/admin/courts', [
            'name' => 'Sân '.$status,
            'venue_id' => $venue->id,
            'sport_type' => 'pickleball',
        ])->assertStatus($expected);

        if ($expected === 422) {
            $response->assertJsonValidationErrors('venue_id');
            $this->assertDatabaseMissing('courts', ['venue_id' => $venue->id]);

            return;
        }
        // A suspended or paused venue's owner must still be able to fix its active courts.
        $this->patchJson("/api/admin/courts/{$response->json('id')}", ['name' => 'Sân sửa', 'is_active' => true])->assertOk();
    }

    public function test_owner_cannot_add_a_court_to_another_organizations_draft_venue(): void
    {
        [$owner] = $this->ownerWithOrganization('own-club');
        [, $otherOrg] = $this->ownerWithOrganization('other-club');
        $foreignVenue = $this->venueWithStatus($otherOrg, 'draft');
        Sanctum::actingAs($owner);

        $this->postJson('/api/admin/courts', [
            'name' => 'Sân lấn',
            'venue_id' => $foreignVenue->id,
            'sport_type' => 'badminton',
        ])->assertForbidden();

        $this->assertDatabaseMissing('courts', ['venue_id' => $foreignVenue->id]);
    }

    public function test_customers_cannot_price_a_recurring_series_on_a_draft_venue_court(): void
    {
        [$owner, $org] = $this->ownerWithOrganization('hidden-club');
        $draft = $this->venueWithStatus($org, 'draft');
        $start = now('Asia/Ho_Chi_Minh')->addWeek()->startOfWeek()->setTime(6, 0);
        Sanctum::actingAs($owner);
        $courtId = $this->postJson('/api/admin/courts', ['name' => 'Sân ẩn', 'venue_id' => $draft->id, 'sport_type' => 'badminton'])
            ->assertCreated()->json('id');
        $this->postJson("/api/admin/courts/{$courtId}/prices", [
            'weekday' => $start->isoWeekday(),
            'starts_at' => '06:00',
            'ends_at' => '07:00',
            'price' => 123456,
            'is_active' => true,
        ])->assertCreated();
        config(['features.flags.direct_owner_payments' => true, 'features.pilot_organizations' => [(string) $org->id]]);

        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]));
        $series = ['court_id' => $courtId, 'frequency' => 'weekly', 'weekdays' => [$start->isoWeekday()], 'starts_at' => $start->toIso8601String(), 'occurrences' => 2];
        $this->postJson('/api/recurring-series/preview', $series)->assertNotFound()->assertDontSee('123456');
        // A guessed accepted price must not act as a price oracle either.
        $this->withHeader('Idempotency-Key', 'draft-series-guess')->postJson('/api/recurring-series', $series + [
            'accepted' => ['total' => 0, 'occurrences' => [['starts_at' => $start->toIso8601String(), 'price' => 0]]],
        ])->assertNotFound();
        $this->assertDatabaseMissing('recurring_series', ['court_id' => $courtId]);
    }

    public function test_owner_moves_an_active_court_into_a_draft_venue_but_not_a_closed_one(): void
    {
        [$owner, $org] = $this->ownerWithOrganization('move-club');
        $published = $this->venueWithStatus($org, 'published');
        $draft = $this->venueWithStatus($org, 'draft');
        $closed = $this->venueWithStatus($org, 'closed');
        $court = Court::create(['venue_id' => $published->id, 'name' => 'Sân di chuyển', 'is_active' => true]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/admin/courts/{$court->id}", ['venue_id' => $closed->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('venue_id');
        $this->assertSame($published->id, $court->fresh()->venue_id);

        $this->patchJson("/api/admin/courts/{$court->id}", ['venue_id' => $draft->id])
            ->assertOk()
            ->assertJsonPath('venue_id', $draft->id);
        $this->assertTrue($court->fresh()->is_active);
    }
}
