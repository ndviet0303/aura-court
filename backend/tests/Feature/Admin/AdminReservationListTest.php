<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Court;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReservationListTest extends TestCase
{
    use RefreshDatabase;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = $this->createMultiOrgVenueFixture();
        $customer = User::factory()->create();
        $this->reservation($customer, $this->fixture['org1']['court1'], 'RSV-ADM-V1', 'confirmed', 1);
        $this->reservation($customer, $this->fixture['org1']['court2'], 'RSV-ADM-V2', 'cancelled', 2);
        $this->reservation($customer, $this->fixture['org2']['court'], 'RSV-ADM-ORG2', 'held', 3);
        $booking = Booking::create(['code' => 'BK-ADM-LEGACY', 'user_id' => $customer->id, 'court_id' => $this->fixture['org1']['court1']->id, 'total_amount' => 80000, 'status' => 'completed']);
        $booking->slots()->create(['court_id' => $booking->court_id, 'starts_at' => now()->subDays(4), 'ends_at' => now()->subDays(4)->addHour(), 'unit_price' => 80000, 'status' => 'confirmed']);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);
    }

    public function test_platform_admin_sees_every_organization_and_legacy_mirrors_carry_their_code(): void
    {
        $admin = User::factory()->create(['role' => 'platform_admin']);

        $response = $this->actingAs($admin)->getJson('/api/admin/reservations')
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('per_page', 30);

        $legacy = collect($response->json('data'))->firstWhere('code', 'LEGACY-BK-ADM-LEGACY');
        $this->assertSame('legacy', $legacy['status']);
        $this->assertSame('BK-ADM-LEGACY', $legacy['legacy_mapping'][0]['legacy_code']);
        $this->assertSame('completed', $legacy['legacy_mapping'][0]['legacy_status']);
        $this->assertNotNull($legacy['user']['id']);
        $this->assertSame($this->fixture['org1']['court1']->id, $legacy['court']['id']);
        $this->assertCount(1, $legacy['slots']);
    }

    public function test_owner_sees_only_own_venues_and_cannot_request_a_foreign_venue(): void
    {
        $owner = $this->fixture['org1']['owner'];

        $this->assertCodes($this->actingAs($owner)->getJson('/api/admin/reservations'), ['RSV-ADM-V1', 'RSV-ADM-V2', 'LEGACY-BK-ADM-LEGACY']);
        $this->assertCodes($this->actingAs($owner)->getJson('/api/admin/reservations?venue_id='.$this->fixture['org1']['venue2']->id), ['RSV-ADM-V2']);
        $this->actingAs($owner)->getJson('/api/admin/reservations?venue_id='.$this->fixture['org2']['venue']->id)
            ->assertForbidden()->assertJsonPath('message', 'Unauthorized venue.');
    }

    public function test_venue_staff_is_limited_to_the_assigned_venue(): void
    {
        $staff = $this->member(OrganizationMember::ROLE_STAFF, ['venue_ids' => [$this->fixture['org1']['venue2']->id]]);

        $this->assertCodes($this->actingAs($staff)->getJson('/api/admin/reservations'), ['RSV-ADM-V2']);
        $this->actingAs($staff)->getJson('/api/admin/reservations?venue_id='.$this->fixture['org1']['venue1']->id)
            ->assertForbidden();
    }

    public function test_member_without_venue_scope_gets_an_empty_page_not_every_reservation(): void
    {
        // A cashier passes venue_admin but has no authorized venues.
        $cashier = $this->member(OrganizationMember::ROLE_CASHIER);

        $this->actingAs($cashier)->getJson('/api/admin/reservations')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('data', []);
    }

    public function test_status_and_date_filters_and_thirty_per_page(): void
    {
        $admin = User::factory()->create(['role' => 'platform_admin']);

        $this->assertCodes($this->actingAs($admin)->getJson('/api/admin/reservations?status=cancelled'), ['RSV-ADM-V2']);
        $this->assertCodes($this->actingAs($admin)->getJson('/api/admin/reservations?status=legacy'), ['LEGACY-BK-ADM-LEGACY']);
        $this->assertCodes($this->actingAs($admin)->getJson('/api/admin/reservations?date='.now()->addDays(3)->toDateString()), ['RSV-ADM-ORG2']);
        $this->actingAs($admin)->getJson('/api/admin/reservations?status=pending_payment')->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $customer = User::factory()->create();
        foreach (range(1, 27) as $i) {
            Reservation::create(['organization_id' => $this->fixture['org2']['org']->id, 'user_id' => $customer->id, 'court_id' => $this->fixture['org2']['court']->id, 'code' => "RSV-ADM-BULK-{$i}", 'status' => 'expired', 'currency' => 'VND', 'total_amount' => 0, 'payment_model' => 'legacy_wallet_v1']);
        }
        $this->actingAs($admin)->getJson('/api/admin/reservations')->assertJsonPath('total', 31)->assertJsonCount(30, 'data')->assertJsonPath('last_page', 2);
        $this->actingAs($admin)->getJson('/api/admin/reservations?page=2')->assertJsonCount(1, 'data');
    }

    private function assertCodes($response, array $codes): void
    {
        $response->assertOk();
        $this->assertEqualsCanonicalizing($codes, collect($response->json('data'))->pluck('code')->all());
    }

    private function member(string $role, ?array $permissions = null): User
    {
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $this->fixture['org1']['org']->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active', 'permissions' => $permissions]);

        return $user;
    }

    private function reservation(User $user, Court $court, string $code, string $status, int $daysAhead): void
    {
        $organizationId = $court->venue->organization_id;
        $reservation = Reservation::create(['organization_id' => $organizationId, 'user_id' => $user->id, 'court_id' => $court->id, 'code' => $code, 'status' => $status, 'currency' => 'VND', 'total_amount' => 100000, 'payment_model' => 'legacy_wallet_v1']);
        $start = now()->addDays($daysAhead)->setTime(12, 0);
        $reservation->slots()->create(['organization_id' => $organizationId, 'court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'unit_price' => 100000, 'status' => $status === 'cancelled' ? 'cancelled' : 'confirmed']);
    }
}
