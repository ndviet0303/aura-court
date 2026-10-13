<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosOpenShiftAndCanonicalReservationValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenantA;

    private Organization $tenantB;

    private User $cashierA;

    private User $customer;

    private User $unaffiliatedUser;

    private PosProduct $productA;

    private Venue $venueA;

    private Court $courtA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Organization::create([
            'name' => 'Aura Court Tenant A',
            'slug' => 'aura-a-'.uniqid(),
            'status' => 'active',
        ]);

        $this->tenantB = Organization::create([
            'name' => 'Competitor Tenant B',
            'slug' => 'comp-b-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [
                (string) $this->tenantA->id,
                (string) $this->tenantB->id,
            ],
        ]);

        // Cashier for Tenant A
        $this->cashierA = User::factory()->create(['name' => 'Cashier A']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->cashierA->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        // Customer
        $this->customer = User::factory()->create(['name' => 'Customer User']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->customer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        // Unaffiliated user
        $this->unaffiliatedUser = User::factory()->create(['name' => 'Stranger User']);

        // Venue & Court for Tenant A
        $this->venueA = Venue::create([
            'organization_id' => $this->tenantA->id,
            'name' => 'Venue A',
            'slug' => 'venue-a-'.uniqid(),
            'is_active' => true,
        ]);
        $this->courtA = Court::create([
            'venue_id' => $this->venueA->id,
            'name' => 'Court A1',
            'is_active' => true,
        ]);

        // Product in Tenant A
        $this->productA = PosProduct::create([
            'organization_id' => $this->tenantA->id,
            'sku' => 'GRIP-A',
            'name' => 'Cán cuốn vợt',
            'price' => 30000,
            'cost' => 15000,
            'stock_quantity' => 20,
            'is_active' => true,
            'metadata' => ['tax_rate' => 0],
        ]);
    }

    public function test_sale_completion_requires_an_active_open_shift(): void
    {
        $headers = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'shift-req-'.uniqid(),
        ];

        $payload = [
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ];

        // 1. Without any shift: fails with 409
        $responseNoShift = $this->actingAs($this->cashierA)->postJson('/api/pos/orders', $payload, $headers);
        $responseNoShift->assertStatus(409)
            ->assertJsonFragment(['message' => 'Cashier does not have an active open shift.']);

        // 2. With a closed shift: fails with 409
        $closedShift = CashShift::create([
            'organization_id' => $this->tenantA->id,
            'cashier_id' => $this->cashierA->id,
            'opening_cash' => 200000,
            'status' => 'closed',
            'opened_at' => now()->subHours(5),
            'closed_at' => now()->subHour(),
        ]);

        $responseClosed = $this->actingAs($this->cashierA)->postJson('/api/pos/orders', $payload, array_merge($headers, [
            'Idempotency-Key' => 'shift-closed-'.uniqid(),
        ]));
        $responseClosed->assertStatus(409)
            ->assertJsonFragment(['message' => 'Cashier does not have an active open shift.']);

        // 3. Open shift for Cashier A: succeeds with 201
        $openShift = CashShift::create([
            'organization_id' => $this->tenantA->id,
            'cashier_id' => $this->cashierA->id,
            'opening_cash' => 200000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $responseSuccess = $this->actingAs($this->cashierA)->postJson('/api/pos/orders', $payload, array_merge($headers, [
            'Idempotency-Key' => 'shift-open-'.uniqid(),
        ]));
        $responseSuccess->assertCreated()
            ->assertJsonPath('order.total_amount', 30000);
    }

    public function test_sale_completion_with_same_tenant_canonical_reservation_succeeds(): void
    {
        // Open shift for cashier
        CashShift::create([
            'organization_id' => $this->tenantA->id,
            'cashier_id' => $this->cashierA->id,
            'opening_cash' => 100000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $reservation = Reservation::create([
            'organization_id' => $this->tenantA->id,
            'code' => 'RES-A-'.strtoupper(uniqid()),
            'user_id' => $this->customer->id,
            'court_id' => $this->courtA->id,
            'status' => 'confirmed',
            'total_amount' => 150000,
        ]);

        $headers = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'res-attach-'.uniqid(),
        ];

        $response = $this->actingAs($this->cashierA)->postJson('/api/pos/orders', [
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
            'reservation_id' => $reservation->id,
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ], $headers);

        $response->assertCreated()
            ->assertJsonPath('order.reservation_id', $reservation->id)
            ->assertJsonPath('receipt.court_info', "Sân #{$reservation->court_id}");
    }

    public function test_sale_completion_rejects_cross_tenant_reservation_with_422(): void
    {
        CashShift::create([
            'organization_id' => $this->tenantA->id,
            'cashier_id' => $this->cashierA->id,
            'opening_cash' => 100000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // Reservation in Tenant B
        $venueB = Venue::create(['organization_id' => $this->tenantB->id, 'name' => 'Venue B', 'slug' => 'venue-b-'.uniqid(), 'is_active' => true]);
        $courtB = Court::create(['venue_id' => $venueB->id, 'name' => 'Court B1', 'is_active' => true]);
        $reservationB = Reservation::create([
            'organization_id' => $this->tenantB->id,
            'code' => 'RES-B-'.strtoupper(uniqid()),
            'user_id' => $this->customer->id,
            'court_id' => $courtB->id,
            'status' => 'confirmed',
            'total_amount' => 200000,
        ]);

        $headers = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'cross-tenant-res-'.uniqid(),
        ];

        // Cashier in Tenant A attempts to attach reservation from Tenant B
        $response = $this->actingAs($this->cashierA)->postJson('/api/pos/orders', [
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
            'reservation_id' => $reservationB->id,
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => "Legacy booking {$reservationB->id} has no canonical reservation mapping."]);
    }

    public function test_sale_completion_rejects_unmapped_legacy_booking_with_422(): void
    {
        CashShift::create([
            'organization_id' => $this->tenantA->id,
            'cashier_id' => $this->cashierA->id,
            'opening_cash' => 100000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $headers = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'unmapped-res-'.uniqid(),
        ];

        // Legacy ID 999888 does not exist
        $response = $this->actingAs($this->cashierA)->postJson('/api/pos/orders', [
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
            'reservation_id' => 999888,
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Legacy booking 999888 has no canonical reservation mapping.']);
    }

    public function test_sale_completion_resolves_explicit_legacy_mapping_to_canonical_reservation(): void
    {
        CashShift::create([
            'organization_id' => $this->tenantA->id,
            'cashier_id' => $this->cashierA->id,
            'opening_cash' => 100000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $canonicalReservation = Reservation::create([
            'organization_id' => $this->tenantA->id,
            'code' => 'RES-CANONICAL-'.strtoupper(uniqid()),
            'user_id' => $this->customer->id,
            'court_id' => $this->courtA->id,
            'status' => 'confirmed',
            'total_amount' => 200000,
        ]);

        $legacyBookingId = 445566;
        LegacyBookingMapping::create([
            'organization_id' => $this->tenantA->id,
            'legacy_booking_id' => $legacyBookingId,
            'legacy_code' => 'BK-LEGACY-445566',
            'legacy_status' => 'confirmed',
            'legacy_total_amount' => 200000,
            'legacy_slot_count' => 1,
            'reservation_id' => $canonicalReservation->id,
            'source_checksum' => 'test-sum-123',
            'migrated_at' => now(),
        ]);

        $headers = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'mapped-legacy-'.uniqid(),
        ];

        $response = $this->actingAs($this->cashierA)->postJson('/api/pos/orders', [
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
            'reservation_id' => $legacyBookingId,
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ], $headers);

        $response->assertCreated()
            ->assertJsonPath('order.reservation_id', $canonicalReservation->id);
    }

    public function test_unauthorized_roles_are_forbidden_from_pos_orders_and_quotes(): void
    {
        $headers = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'unauth-pos-'.uniqid(),
        ];

        $payload = [
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ];

        // 1. Customer role is rejected with 403 Forbidden
        $responseCustomer = $this->actingAs($this->customer)->postJson('/api/pos/orders', $payload, $headers);
        $responseCustomer->assertStatus(403);

        $responseCustomerQuote = $this->actingAs($this->customer)->postJson('/api/pos/quote', $payload, $headers);
        $responseCustomerQuote->assertStatus(403);

        // 2. Unaffiliated user is rejected with 403 Forbidden
        $responseStranger = $this->actingAs($this->unaffiliatedUser)->postJson('/api/pos/orders', $payload, $headers);
        $responseStranger->assertStatus(403);
    }
}
