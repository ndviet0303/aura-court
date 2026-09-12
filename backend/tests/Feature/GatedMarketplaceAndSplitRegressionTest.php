<?php

namespace Tests\Feature;

use App\Models\EntitlementTransfer;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\SplitPaymentService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Social\MarketplaceService;
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
        ])->postJson('/api/v2/reservations', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $reservationResponse->assertCreated();

        $confirmResponse = $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => uniqid('conf-'),
        ])->postJson('/api/v2/reservations/'.$reservationResponse->json('reservation.code').'/confirm');
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
        ])->postJson("/api/v2/reservations/{$reservation->id}/split", [
            'shares' => [['amount' => 100000], ['amount' => 100000]],
        ])->assertStatus(404);

        // 2. Marketplace listings route returns 404
        $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
        ])->getJson('/api/v2/marketplace/listings')->assertStatus(404);

        $entitlement = $reservation->entitlements()->firstOrFail();
        $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
        ])->postJson("/api/v2/marketplace/entitlements/{$entitlement->id}/list", [
            'asking_price' => 200000,
        ])->assertStatus(404);
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

        // Seller lists entitlement for resale
        $listing = app(MarketplaceService::class)->list($entitlement, $seller, $entitlement->amount);
        $this->assertSame('listed', $listing->status);

        // Buyer purchases entitlement with successful seller refund
        $transfer = app(MarketplaceService::class)->purchase(
            $listing,
            $buyer,
            'idemp-transfer-success-'.uniqid(),
            'pay-ref-buyer-'.uniqid(),
            null,
            true // seller refund accepted
        );

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

    public function test_marketplace_transfer_compensates_and_relists_when_seller_refund_fails(): void
    {
        [$organization, $seller, $court, $reservation] = $this->setupTenantAndReservation(enablePilot: false);

        $buyer = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $buyer->id, 'role' => 'customer', 'status' => 'active']);

        $entitlement = $reservation->entitlements()->firstOrFail();
        $listing = app(MarketplaceService::class)->list($entitlement, $seller, $entitlement->amount);

        // Buyer purchases but seller refund is rejected
        $transfer = app(MarketplaceService::class)->purchase(
            $listing,
            $buyer,
            'idemp-transfer-fail-'.uniqid(),
            'pay-ref-buyer-fail-'.uniqid(),
            null,
            false // seller refund rejected
        );

        $this->assertSame('compensated', $transfer->status);
        $this->assertSame('listed', $listing->fresh()->status);
        $this->assertSame('active', $entitlement->fresh()->status); // seller retains entitlement

        // FinanceException is logged for manual review
        $this->assertDatabaseHas('finance_exceptions', [
            'type' => 'marketplace_transfer_failed',
            'reference' => (string) $transfer->id,
            'status' => 'open',
        ]);
    }
}
