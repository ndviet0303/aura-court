<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\TenantCustomer;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAssistedWalkInCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenantA;

    private Organization $tenantB;

    private User $staffAlice;

    private User $cashierBob;

    private User $customerCharlie;

    private User $customerOtherTenant;

    private Venue $venueA;

    private Court $courtA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Organization::create([
            'name' => 'Aura Court Tenant A',
            'slug' => 'aura-a-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        $this->tenantB = Organization::create([
            'name' => 'Competitor Tenant B',
            'slug' => 'comp-b-'.uniqid(),
            'status' => 'active',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        $this->staffAlice = User::factory()->create(['name' => 'Staff Alice']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->staffAlice->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $this->cashierBob = User::factory()->create(['name' => 'Cashier Bob']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->cashierBob->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $this->customerCharlie = User::factory()->create(['name' => 'Customer Charlie']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->customerCharlie->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);
        TenantCustomer::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->customerCharlie->id,
            'name' => $this->customerCharlie->name,
            'status' => 'active',
        ]);

        $this->customerOtherTenant = User::factory()->create(['name' => 'Other Tenant Customer']);
        OrganizationMember::create([
            'organization_id' => $this->tenantB->id,
            'user_id' => $this->customerOtherTenant->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $this->venueA = Venue::create([
            'organization_id' => $this->tenantA->id,
            'name' => 'Aura Complex District 1',
            'slug' => 'aura-d1-'.uniqid(),
            'is_active' => true,
        ]);
        $this->assignStaffVenues($this->tenantA, $this->venueA);

        $this->courtA = Court::create([
            'venue_id' => $this->venueA->id,
            'name' => 'Center Badminton Court 1',
            'is_active' => true,
        ]);

        // Base price rule
        PriceRule::create([
            'organization_id' => $this->tenantA->id,
            'court_id' => $this->courtA->id,
            'type' => 'base',
            'price' => 150000,
            'is_active' => true,
        ]);
    }

    private function fundWallet(User $user, int $organizationId, int $amount = 500000): WalletAccount
    {
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Fund wallet for walk-in test');

        return $wallet->fresh();
    }

    public function test_staff_can_checkout_walk_in_booking_for_registered_customer_with_audited_staff_identity(): void
    {
        $this->fundWallet($this->customerCharlie, $this->tenantA->id, 500000);

        $tz = config('app.timezone');
        $slotTime = Carbon::now($tz)->addDays(1)->setTime(14, 0)->startOfHour();
        $res = $this->actingAs($this->staffAlice)
            ->withHeaders([
                'X-Organization-ID' => (string) $this->tenantA->id,
                'Idempotency-Key' => uniqid('walkin-'),
            ])
            ->postJson('/api/reservations/checkout', [
                'court_id' => $this->courtA->id,
                'slots' => [$slotTime->toIso8601String()],
                'customer_id' => $this->customerCharlie->id,
            ]);

        $res->assertCreated();
        $res->assertJsonPath('reservation.status', 'confirmed');
        $res->assertJsonPath('reservation.user_id', $this->customerCharlie->id);
        $res->assertJsonPath('reservation.total_amount', 150000);

        // Verify audit actor identity recorded in metadata
        $res->assertJsonPath('reservation.metadata.walk_in', true);
        $res->assertJsonPath('reservation.metadata.booked_by_staff_id', $this->staffAlice->id);
        $res->assertJsonPath('reservation.metadata.booked_by_staff_name', $this->staffAlice->name);
        $res->assertJsonPath('reservation.metadata.staff_actor_role', OrganizationMember::ROLE_STAFF);

        // Verify customer wallet was debited
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($this->customerCharlie->id, $this->tenantA->id);
        $this->assertSame(350000, (int) $wallet->balance);

        // Verify balanced financial posting recorded
        $this->assertDatabaseHas('journals', [
            'organization_id' => $this->tenantA->id,
            'event_type' => 'reservation.wallet_payment',
            'total_debit' => 150000,
            'total_credit' => 150000,
        ]);
    }

    public function test_walk_in_conflict_fails_atomically_without_second_booking_or_debit(): void
    {
        $this->fundWallet($this->customerCharlie, $this->tenantA->id, 500000);

        $tz = config('app.timezone');
        $slotTime = Carbon::now($tz)->addDays(2)->setTime(10, 0)->startOfHour();

        // 1. Initial confirmed customer booking for this slot
        $initialRes = $this->actingAs($this->customerCharlie)
            ->withHeaders([
                'X-Organization-ID' => (string) $this->tenantA->id,
                'Idempotency-Key' => uniqid('cust-book-'),
            ])
            ->postJson('/api/reservations/checkout', [
                'court_id' => $this->courtA->id,
                'slots' => [$slotTime->toIso8601String()],
            ]);
        $initialRes->assertCreated();

        // Charlie's balance after first booking is 350,000
        $balanceBefore = (int) app(WalletPaymentService::class)->getOrCreateWallet($this->customerCharlie->id, $this->tenantA->id)->balance;
        $this->assertSame(350000, $balanceBefore);

        $reservationCountBefore = Reservation::where('court_id', $this->courtA->id)->count();

        // 2. Staff attempts walk-in checkout for the already booked slot
        $conflictRes = $this->actingAs($this->cashierBob)
            ->withHeaders([
                'X-Organization-ID' => (string) $this->tenantA->id,
                'Idempotency-Key' => uniqid('walkin-conflict-'),
            ])
            ->postJson('/api/reservations/checkout', [
                'court_id' => $this->courtA->id,
                'slots' => [$slotTime->toIso8601String()],
                'customer_id' => $this->customerCharlie->id,
            ]);

        // Must fail with 409 conflict
        $conflictRes->assertStatus(409);

        // Charlie's balance must remain 350,000 (NO debit on conflict)
        $balanceAfter = (int) app(WalletPaymentService::class)->getOrCreateWallet($this->customerCharlie->id, $this->tenantA->id)->balance;
        $this->assertSame(350000, $balanceAfter);

        // No second reservation created
        $this->assertSame($reservationCountBefore, Reservation::where('court_id', $this->courtA->id)->count());
    }

    public function test_non_staff_cannot_create_walk_in_for_another_customer(): void
    {
        $otherUser = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $otherUser->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $tz = config('app.timezone');
        $slotTime = Carbon::now($tz)->addDays(1)->setTime(16, 0)->startOfHour();

        $res = $this->actingAs($this->customerCharlie)
            ->withHeaders([
                'X-Organization-ID' => (string) $this->tenantA->id,
                'Idempotency-Key' => uniqid('unauth-walkin-'),
            ])
            ->postJson('/api/reservations/checkout', [
                'court_id' => $this->courtA->id,
                'slots' => [$slotTime->toIso8601String()],
                'customer_id' => $otherUser->id,
            ]);

        $res->assertForbidden();
    }

    public function test_walk_in_for_customer_of_another_tenant_is_rejected(): void
    {
        $tz = config('app.timezone');
        $slotTime = Carbon::now($tz)->addDays(1)->setTime(18, 0)->startOfHour();

        $res = $this->actingAs($this->staffAlice)
            ->withHeaders([
                'X-Organization-ID' => (string) $this->tenantA->id,
                'Idempotency-Key' => uniqid('cross-tenant-walkin-'),
            ])
            ->postJson('/api/reservations/checkout', [
                'court_id' => $this->courtA->id,
                'slots' => [$slotTime->toIso8601String()],
                'customer_id' => $this->customerOtherTenant->id,
            ]);

        $res->assertStatus(422);
        $res->assertJsonPath('message', 'Customer does not belong to this organization.');
    }

    public function test_walk_in_with_insufficient_customer_balance_fails_atomically(): void
    {
        // Charlie has 0 balance
        $tz = config('app.timezone');
        $slotTime = Carbon::now($tz)->addDays(1)->setTime(20, 0)->startOfHour();

        $res = $this->actingAs($this->staffAlice)
            ->withHeaders([
                'X-Organization-ID' => (string) $this->tenantA->id,
                'Idempotency-Key' => uniqid('nobalance-walkin-'),
            ])
            ->postJson('/api/reservations/checkout', [
                'court_id' => $this->courtA->id,
                'slots' => [$slotTime->toIso8601String()],
                'customer_id' => $this->customerCharlie->id,
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Số dư ví không đủ', $res->json('message'));

        // No reservation created
        $this->assertSame(0, Reservation::where('user_id', $this->customerCharlie->id)->count());
    }
}
