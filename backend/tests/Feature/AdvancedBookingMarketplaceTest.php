<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Models\Venue;
use App\Services\MarketplaceService;
use App\Services\RecurringSeriesService;
use App\Services\SplitPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdvancedBookingMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_shares_must_sum_exactly_to_the_reservation_amount(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $reservation = $this->reservation($organization, $user, $court->id);

        $this->expectException(ValidationException::class);
        app(SplitPaymentService::class)->create($reservation, $user, [['amount' => 1], ['amount' => 2]]);
    }

    public function test_recurring_series_reserves_the_full_horizon_and_creates_monthly_invoices(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $startsAt = now(config('app.timezone'))->addDays(10)->setTime(8, 0);
        $result = app(RecurringSeriesService::class)->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $startsAt->toIso8601String(),
            'occurrences' => 3,
        ]);

        $this->assertSame('confirmed', $result->status);
        $this->assertCount(3, $result->occurrences);
        $this->assertNotEmpty($result->invoices);
    }

    public function test_marketplace_refund_failure_compensates_and_relists_without_transfer(): void
    {
        [$organization, $seller, $court] = $this->tenant();
        $buyer = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $buyer->id, 'role' => 'customer', 'status' => 'active']);
        $reservation = $this->reservation($organization, $seller, $court->id, true);
        $entitlement = $reservation->entitlements()->firstOrFail();
        $listing = app(MarketplaceService::class)->list($entitlement, $seller, $entitlement->amount);

        $transfer = app(MarketplaceService::class)->purchase($listing, $buyer, 'marketplace-failure-1', 'buyer-payment-1', null, false);

        $this->assertSame('compensated', $transfer->status);
        $this->assertSame('listed', $listing->fresh()->status);
        $this->assertDatabaseCount('reservation_entitlements', 1);
    }

    /** @return array{0:Organization,1:User,2:\App\Models\Court} */
    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Advanced Tenant', 'slug' => 'advanced-'.uniqid()]);
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'organization_owner', 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Advanced Venue', 'slug' => 'advanced-venue-'.uniqid(), 'is_active' => true]);
        $court = $venue->courts()->create(['name' => 'Advanced Court', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);
        config(['features.pilot_organizations' => [(string) $organization->id], 'features.flags.marketplace_split_payments' => true, 'features.flags.marketplace_slot_resale' => true]);
        return [$organization, $user, $court];
    }

    private function reservation(Organization $organization, User $user, int $courtId, bool $future = false): \App\Models\Reservation
    {
        $date = now(config('app.timezone'))->addDays($future ? 4 : 2)->setTime(8, 0);
        $response = $this->actingAs($user)->withHeaders(['X-Organization-ID' => (string) $organization->id, 'Idempotency-Key' => uniqid('reservation-')])->postJson('/api/v2/reservations', ['court_id' => $courtId, 'slots' => [$date->toIso8601String()]]);
        $response->assertCreated();
        $reservation = \App\Models\Reservation::where('code', $response->json('reservation.code'))->firstOrFail();
        $this->actingAs($user)->withHeaders(['X-Organization-ID' => (string) $organization->id, 'Idempotency-Key' => uniqid('confirm-')])->postJson('/api/v2/reservations/'.$reservation->code.'/confirm')->assertOk();
        return $reservation->fresh('entitlements');
    }
}
