<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
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
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $plan = PlatformPlan::create([
            'code' => 'ACTIVE_PLAN',
            'name' => 'Active Plan',
            'price' => 300000,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);
        PlatformSubscription::create([
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
        $existing = Reservation::findOrFail(app(LegacyBookingBackfillService::class)->backfillBooking($existingBooking)->reservation_id);

        // Now partner is SUSPENDED!
        $org->update([
            'partner_status' => 'suspended',
            'partner_status_reason' => 'Đang tạm dừng để kiểm tra bảo trì.',
        ]);

        // 1. Customer attempts to create a NEW booking -> Must be refused with 422
        Sanctum::actingAs($customer);
        $newBookingRes = $this->withHeader('Idempotency-Key', 'suspended-new-booking')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$targetSlot->toIso8601String()],
            'expected_total' => 100000,
        ]);
        $newBookingRes->assertStatus(422)
            ->assertJsonValidationErrors(['partner']);

        // 2. Customer can still view their existing booking
        $tenant = ['X-Organization-ID' => (string) $org->id];
        $showRes = $this->withHeaders($tenant)->getJson("/api/reservations/{$existing->code}");
        $showRes->assertStatus(200)
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_code', 'BK-EXISTING-01');

        // 3. Organization owner can view the existing booking
        Sanctum::actingAs($owner);
        $ownerShowRes = $this->withHeaders($tenant)->getJson("/api/reservations/{$existing->code}");
        $ownerShowRes->assertStatus(200)
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_code', 'BK-EXISTING-01');

        // 4. Cross-tenant user CANNOT view the booking (403)
        Sanctum::actingAs($crossTenantUser);
        $crossTenantRes = $this->withHeaders($tenant)->getJson("/api/reservations/{$existing->code}");
        $crossTenantRes->assertStatus(403);

        // 5. Create a legacy unpaid hold
        $legacyHold = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'HOLD-LEGACY-01',
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 100000,
            'hold_expires_at' => now()->addMinutes(15),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        // 6. Retired commands are rejected (404) across customer, staff, and admin actors
        // Customer actor
        Sanctum::actingAs($customer);
        $this->postJson("/api/reservations/{$legacyHold->code}/cancel")->assertNotFound();

        // Staff / Owner actor
        Sanctum::actingAs($owner);
        $this->postJson("/api/reservations/{$legacyHold->code}/cancel")->assertNotFound();

        // Admin actor
        Sanctum::actingAs($admin);
        $this->postJson("/api/reservations/{$legacyHold->code}/cancel")->assertNotFound();

        // 7. Verify invariants: states, wallet balances, and inventory remain untouched
        $this->assertSame('confirmed', $existingBooking->fresh()->status);
        $this->assertSame('confirmed', $existingBooking->slots()->first()->status);
        $this->assertSame('confirmed', $existing->fresh()->status);
        $this->assertSame('held', $legacyHold->fresh()->status);
        $this->assertSame(500000, (int) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('refunds', ['booking_id' => $existingBooking->id]);
    }
}
