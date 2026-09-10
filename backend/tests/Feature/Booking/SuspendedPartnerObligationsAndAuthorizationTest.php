<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuspendedPartnerObligationsAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_partner_stops_new_bookings_while_preserving_existing_obligations(): void
    {
        $owner = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $crossTenantUser = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);

        $org = Organization::create([
            'name' => 'Suspension Arena',
            'slug' => 'suspension-arena',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        $plan = \App\Models\PlatformPlan::create([
            'code' => 'ACTIVE_PLAN',
            'name' => 'Active Plan',
            'price' => 300000,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);
        \App\Models\PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
            'auto_renew' => true,
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Suspension Venue',
            'slug' => 'suspension-venue',
            'status' => 'published',
            'is_active' => true,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court 1',
            'slug' => 'court-1',
            'is_active' => true,
        ]);

        // Create price slot
        $targetSlot = Carbon::tomorrow()->setHour(14)->setMinute(0)->setSecond(0);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $targetSlot->dayOfWeekIso,
            'starts_at' => '14:00',
            'ends_at' => '15:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        // Customer has wallet balance
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $wallet->increment('balance', 500000);

        // Pre-existing booking made before suspension
        $existingBooking = Booking::create([
            'code' => 'BK-EXISTING-01',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 100000,
            'status' => 'confirmed',
        ]);
        $existingBooking->slots()->create([
            'court_id' => $court->id,
            'starts_at' => $targetSlot->copy()->addDays(2),
            'ends_at' => $targetSlot->copy()->addDays(2)->addHour(),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);

        // Now partner is SUSPENDED!
        $org->update([
            'partner_status' => 'suspended',
            'partner_status_reason' => 'Đang tạm dừng để kiểm tra bảo trì.',
        ]);

        // 1. Customer attempts to create a NEW booking -> Must be refused with 422
        Sanctum::actingAs($customer);
        $newBookingRes = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$targetSlot->toIso8601String()],
        ]);
        $newBookingRes->assertStatus(422)
            ->assertJsonValidationErrors(['partner']);

        // 2. Customer can still view their existing booking
        $showRes = $this->getJson("/api/v1/bookings/{$existingBooking->code}");
        $showRes->assertStatus(200)
            ->assertJsonPath('code', 'BK-EXISTING-01');

        // 3. Organization owner can view the existing booking
        Sanctum::actingAs($owner);
        $ownerShowRes = $this->getJson("/api/v1/bookings/{$existingBooking->code}");
        $ownerShowRes->assertStatus(200)
            ->assertJsonPath('code', 'BK-EXISTING-01');

        // 4. Cross-tenant user CANNOT view the booking (403)
        Sanctum::actingAs($crossTenantUser);
        $crossTenantRes = $this->getJson("/api/v1/bookings/{$existingBooking->code}");
        $crossTenantRes->assertStatus(403);

        // 5. Customer can cancel their existing booking per cancellation policy
        Sanctum::actingAs($customer);
        $cancelRes = $this->postJson("/api/v1/bookings/{$existingBooking->code}/cancel", [
            'reason' => 'Thay đổi lịch cá nhân',
        ]);
        $cancelRes->assertStatus(200);
        $this->assertEquals('refunded', $existingBooking->fresh()->status);
    }
}
