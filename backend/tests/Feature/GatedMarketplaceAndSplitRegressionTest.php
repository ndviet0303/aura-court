<?php

namespace Tests\Feature;

use App\Models\EntitlementTransfer;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\SplitPaymentService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Social\MarketplaceService;
use App\Services\Social\PeerToPeerResaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GatedMarketplaceAndSplitRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenantAndReservation(bool $enablePilot = false): array
    {
        $organization = Organization::create([
            'name' => 'Org Gated',
            'slug' => 'org-gated-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $customer->id, 'role' => 'customer', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Venue Gated',
            'slug' => 'venue-gated-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Court Gated', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $organization->id);
        $walletService->credit($wallet, 1000000, 'test_topup', 1, 'Top up');

        if ($enablePilot) {
            config([
                'features.pilot_organizations' => [(string) $organization->id],
                'features.flags.marketplace_split_payments' => true,
                'features.flags.marketplace_slot_resale' => true,
            ]);
        } else {
            config([
                'features.pilot_organizations' => [],
                'features.flags.marketplace_split_payments' => false,
                'features.flags.marketplace_slot_resale' => false,
            ]);
        }

        // Create a confirmed reservation with entitlement
        $date = Carbon::now(config('app.timezone'))->addDays(3)->setTime(9, 0)->startOfHour();
        $reservationResponse = $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => uniqid('res-'),
        ])->postJson('/api/reservations', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $reservationResponse->assertCreated();

        $confirmResponse = $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => uniqid('conf-'),
        ])->postJson('/api/reservations/'.$reservationResponse->json('reservation.code').'/confirm');
        $confirmResponse->assertOk();

        $reservation = Reservation::where('code', $reservationResponse->json('reservation.code'))->firstOrFail();

        return [$organization, $customer, $court, $reservation];
    }

    public function test_gated_endpoints_return_404_when_features_not_enabled(): void
    {
        [$organization, $customer, $court, $reservation] = $this->setupTenantAndReservation(enablePilot: false);

        // 1. Split payment route returns 404
        $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
        ])->postJson("/api/reservations/{$reservation->id}/split", [
            'shares' => [['amount' => 100000], ['amount' => 100000]],
        ])->assertStatus(404);

        // 2. Marketplace listings route returns 404
        $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
        ])->getJson('/api/marketplace/listings')->assertStatus(404);

        $entitlement = $reservation->entitlements()->firstOrFail();
        $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
        ])->postJson("/api/marketplace/entitlements/{$entitlement->id}/list", [
            'asking_price' => 200000,
        ])->assertStatus(404);
    }

    public function test_listings_fall_back_to_a_public_organization_when_tenant_resolution_fails(): void
    {
        config(['features.flags.marketplace_slot_resale' => true]);
        // With no active organization, organization() aborts and listings() takes its public fallback.
        Organization::query()->update(['status' => 'suspended']);
        $suspended = Organization::create([
            'name' => 'Org Suspended',
            'slug' => 'org-suspended-'.uniqid(),
            'status' => 'suspended',
            'partner_status' => 'approved',
        ]);
        $browser = User::factory()->create();

        $this->actingAs($browser)
            ->getJson('/api/marketplace/listings?organization_id='.$suspended->id)
            ->assertOk()
            ->assertJsonPath('data', []);
        $this->actingAs($browser)
            ->getJson('/api/marketplace/listings')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_split_payment_service_regression_shares_validation(): void
    {
        [$organization, $customer, $court, $reservation] = $this->setupTenantAndReservation(enablePilot: false);

        // Shares do not sum to total amount -> throws ValidationException
        $this->expectException(ValidationException::class);
        app(SplitPaymentService::class)->create($reservation, $customer, [
            ['amount' => 50000],
            ['amount' => 50000],
        ]);
    }

    public function test_marketplace_resale_and_successful_entitlement_transfer(): void
    {
        [$organization, $seller, $court, $reservation] = $this->setupTenantAndReservation(enablePilot: false);

        $buyer = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $buyer->id, 'role' => 'customer', 'status' => 'active']);

        $entitlement = $reservation->entitlements()->firstOrFail();
        $this->assertSame('active', $entitlement->status);

        // Seller lists entitlement for resale with their own receiving account
        $listing = app(MarketplaceService::class)->list($entitlement, $seller, $entitlement->amount, ['bank_code' => 'TPB', 'account_number' => '0123456789', 'account_name' => 'SELLER']);
        $this->assertSame('listed', $listing->status);

        // Buyer locks, pays the seller directly, and the seller confirms receipt
        $resale = app(PeerToPeerResaleService::class);
        $resale->lock($listing, $buyer);
        $resale->confirmReceipt($listing, $seller);

        $transfer = EntitlementTransfer::where('marketplace_listing_id', $listing->id)->sole();
        $this->assertSame('completed', $transfer->status);
        $this->assertSame('sold', $listing->fresh()->status);
        $this->assertSame('transferred', $entitlement->fresh()->status);
        // Buyer receives newly created active entitlement
        $buyerEntitlement = $transfer->buyerEntitlement;
        $this->assertNotNull($buyerEntitlement);
        $this->assertSame('active', $buyerEntitlement->status);
        $this->assertSame($buyer->id, $buyerEntitlement->user_id);
        $this->assertSame($entitlement->amount, $buyerEntitlement->amount);
    }
}
