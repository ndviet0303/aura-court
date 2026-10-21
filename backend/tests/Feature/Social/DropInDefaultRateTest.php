<?php

namespace Tests\Feature\Social;

use App\Models\Court;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/** Design D24: a drop-in session suggests the court price of its price table and charges ⌊minutes × price / 60⌋. */
class DropInDefaultRateTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private Organization $organization;

    private Court $court;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create(['name' => 'Sân Giao Lưu', 'slug' => 'san-giao-luu-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [(string) $this->organization->id],
        ]);
        $venue = Venue::create(['organization_id' => $this->organization->id, 'name' => 'Cơ sở 1', 'slug' => 'co-so-1-'.uniqid(), 'address' => '1 Đường Cầu Lông', 'is_active' => true, 'status' => 'active']);
        $this->court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân 1', 'slug' => 'san-1-'.uniqid(), 'is_active' => true]);
        $this->owner = User::factory()->create();
        OrganizationMember::create(['organization_id' => $this->organization->id, 'user_id' => $this->owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
    }

    public function test_a_session_at_17_suggests_the_price_in_effect_from_16(): void
    {
        $this->priceCourtWith($this->court, [['10:00', '16:00', 100000], ['16:00', '22:00', 140000]]);
        $startsAt = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addDays(2)->setTime(17, 0);

        $this->rates($this->dropIn($startsAt, 'open'))
            ->assertOk()
            ->assertExactJson(['rates' => ['court_hourly_rate' => 140000, 'shuttlecock_unit_price' => null]]);
    }

    public function test_an_unpriced_session_start_suggests_no_court_price(): void
    {
        $this->priceCourtWith($this->court, [['10:00', '16:00', 100000]]);
        $startsAt = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addDays(2)->setTime(17, 0);

        $this->rates($this->dropIn($startsAt, 'open'))
            ->assertOk()
            ->assertExactJson(['rates' => ['court_hourly_rate' => null, 'shuttlecock_unit_price' => null]]);
    }

    public function test_court_subtotal_drops_the_fraction_of_a_vnd(): void
    {
        $post = $this->dropIn(CarbonImmutable::now()->subHours(2), 'ended');

        // 80 × 100.000 / 60 = 133.333,33
        $this->actingAs($this->owner, 'sanctum')
            ->withHeaders(['X-Organization-Id' => (string) $this->organization->id, 'Idempotency-Key' => 'idem-'.uniqid()])
            ->postJson("/api/social/matches/{$post->id}/bill/draft", [
                'actual_headcount' => 1,
                'court_duration_minutes' => 80,
                'court_hourly_rate' => 100000,
                'shuttlecock_count' => 0,
                'shuttlecock_unit_price' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('bill.court_subtotal', 133333)
            ->assertJsonPath('bill.total_amount', 133333);
    }

    private function dropIn(CarbonImmutable $startsAt, string $status): MatchPost
    {
        return MatchPost::create([
            'organization_id' => $this->organization->id,
            'creator_id' => $this->owner->id,
            'court_id' => $this->court->id,
            'title' => 'Giao lưu buổi chiều',
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'required_participants' => 4,
            'joined_count' => 0,
            'status' => $status,
        ]);
    }

    private function rates(MatchPost $post)
    {
        return $this->actingAs($this->owner, 'sanctum')
            ->withHeaders(['X-Organization-Id' => (string) $this->organization->id])
            ->getJson("/api/social/matches/{$post->id}/rates");
    }
}
