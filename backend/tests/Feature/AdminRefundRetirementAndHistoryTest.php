<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Refund;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRefundRetirementAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenant(): array
    {
        $org = Organization::create([
            'name' => 'Admin Test Org',
            'slug' => 'admin-test-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Admin Test Venue',
            'slug' => 'admin-test-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Court 1', 'is_active' => true]);

        return [$org, $venue, $court];
    }

    public function test_admin_cannot_mutate_refunds_approval_and_rejection_return_404_with_no_side_effects(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $customer = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $customer->id, 'role' => 'customer', 'status' => 'active']);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $walletService->credit($wallet, 500000, 'test_topup', 1, 'Top up');

        $booking = Booking::create([
            'code' => 'BK-TEST-REFUND-01',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 100000,
            'status' => 'confirmed',
        ]);
        $booking->slots()->create([
            'court_id' => $court->id,
            'starts_at' => Carbon::now()->addDays(2)->setTime(10, 0),
            'ends_at' => Carbon::now()->addDays(2)->setTime(11, 0),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);

        // Historical pending refund row
        $pendingRefund = Refund::create([
            'booking_id' => $booking->id,
            'amount' => 100000,
            'status' => 'pending',
            'note' => 'Historical pending refund',
            'processed_by' => null,
            'processed_at' => null,
        ]);

        // Historical completed refund row on another booking
        $booking2 = Booking::create([
            'code' => 'BK-TEST-REFUND-02',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 50000,
            'status' => 'refunded',
        ]);
        $completedRefund = Refund::create([
            'booking_id' => $booking2->id,
            'amount' => 50000,
            'status' => 'completed',
            'note' => 'Historical completed refund',
            'processed_by' => null,
            'processed_at' => now()->subDay(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $walletBalanceBefore = (int) $wallet->fresh()->balance;

        // 1. Attempt to approve pending refund -> retired route returns 404
        $approveRes = $this->patchJson("/api/v1/admin/refunds/{$pendingRefund->id}", [
            'status' => 'approved',
            'note' => 'Approve refund',
        ]);
        $approveRes->assertNotFound();

        // 2. Attempt to reject pending refund -> retired route returns 404
        $rejectRes = $this->patchJson("/api/v1/admin/refunds/{$pendingRefund->id}", [
            'status' => 'rejected',
            'note' => 'Reject refund',
        ]);
        $rejectRes->assertNotFound();

        // 3. Attempt to reject completed refund -> returns 404
        $rejectCompletedRes = $this->patchJson("/api/v1/admin/refunds/{$completedRefund->id}", [
            'status' => 'rejected',
            'note' => 'Reject completed',
        ]);
        $rejectCompletedRes->assertNotFound();

        // 4. Invariants: Database rows and financial states remain completely untouched
        $pendingRefund->refresh();
        $this->assertSame('pending', $pendingRefund->status);
        $this->assertNull($pendingRefund->processed_by);
        $this->assertNull($pendingRefund->processed_at);

        $completedRefund->refresh();
        $this->assertSame('completed', $completedRefund->status);

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('confirmed', $booking->slots()->first()->status);
        $this->assertSame($walletBalanceBefore, (int) $wallet->fresh()->balance);

        $this->assertDatabaseMissing('journals', [
            'organization_id' => $org->id,
            'event_type' => 'booking.wallet_refund',
            'reference' => $booking->code,
        ]);
    }

    public function test_authorized_admin_can_access_historical_refunds_listing(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $customer = User::factory()->create(['role' => 'customer']);

        $booking = Booking::create([
            'code' => 'BK-HIST-LIST-01',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 100000,
            'status' => 'confirmed',
        ]);

        Refund::create([
            'booking_id' => $booking->id,
            'amount' => 100000,
            'status' => 'pending',
            'note' => 'Pending historical',
        ]);

        Refund::create([
            'booking_id' => $booking->id,
            'amount' => 50000,
            'status' => 'completed',
            'note' => 'Completed historical',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/refunds');
        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertGreaterThanOrEqual(2, $response->json('total'));
    }

    public function test_unauthorized_and_non_admin_users_cannot_access_admin_refunds(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $customer = User::factory()->create(['role' => 'customer']);

        $booking = Booking::create([
            'code' => 'BK-UNAUTH-01',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 100000,
            'status' => 'confirmed',
        ]);

        $refund = Refund::create([
            'booking_id' => $booking->id,
            'amount' => 100000,
            'status' => 'pending',
        ]);

        // 1. Unauthenticated request -> 401
        $this->getJson('/api/v1/admin/refunds')->assertUnauthorized();

        // 2. Customer authenticated -> 403 Forbidden
        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/admin/refunds')->assertForbidden();

        // 3. Customer PATCH -> 404 (route is retired)
        $this->patchJson("/api/v1/admin/refunds/{$refund->id}", [
            'status' => 'approved',
        ])->assertNotFound();
    }
}
