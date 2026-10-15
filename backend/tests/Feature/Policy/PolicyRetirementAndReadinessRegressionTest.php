<?php

namespace Tests\Feature\Policy;

use App\Models\Booking;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Models\VenueOperatingSchedule;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PolicyRetirementAndReadinessRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenant(): array
    {
        $org = Organization::create([
            'name' => 'Policy Org',
            'slug' => 'policy-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Policy Venue',
            'slug' => 'policy-venue-'.uniqid(),
            'address' => '123 Main Street, District 1',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Policy Court 1', 'is_active' => true]);

        return [$org, $venue, $court];
    }

    public function test_obsolete_organization_cancellation_policy_writes_are_rejected(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $owner = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        Sanctum::actingAs($owner);

        // 1. Direct field write rejection
        $res1 = $this->patchJson("/api/organizations/{$org->id}", [
            'name' => 'Updated Org Name',
            'cancellation_grace_hours' => 4,
        ]);
        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['cancellation_grace_hours']);

        // 2. Settings nested write rejection
        $res2 = $this->patchJson("/api/organizations/{$org->id}", [
            'name' => 'Updated Org Name',
            'settings' => [
                'cancellation_grace_hours' => 4,
            ],
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['cancellation_grace_hours']);
    }

    public function test_obsolete_membership_cancellation_waiver_writes_are_rejected(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        // 1. Membership plan store rejection (camelCase & snake_case)
        $planRes1 = $this->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/admin/memberships/plans', [
                'name' => 'VIP Plan 1',
                'price' => 200000,
                'cancellationFeeWaived' => true,
            ]);
        $planRes1->assertStatus(422);
        $planRes1->assertJsonValidationErrors(['cancellationFeeWaived']);

        $planRes2 = $this->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/admin/memberships/plans', [
                'name' => 'VIP Plan 2',
                'price' => 200000,
                'cancellation_fee_waived' => true,
            ]);
        $planRes2->assertStatus(422);
        $planRes2->assertJsonValidationErrors(['cancellation_fee_waived']);

        // 2. Membership plan update rejection
        $existingPlan = TenantMembershipPlan::create([
            'organization_id' => $org->id,
            'name' => 'Existing Plan',
            'version' => 1,
            'price' => 150000,
            'benefits' => [],
            'status' => 'published',
        ]);

        $updateRes = $this->withHeader('X-Organization-ID', (string) $org->id)
            ->patchJson("/api/admin/memberships/plans/{$existingPlan->id}", [
                'cancellationFeeWaived' => true,
            ]);
        $updateRes->assertStatus(422);
        $updateRes->assertJsonValidationErrors(['cancellationFeeWaived']);
    }

    public function test_venue_readiness_passes_without_cancellation_configuration(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $owner = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'PLAN_READY', 'name' => 'Plan', 'price' => 200000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
            ],
        ]);

        $hours = [];
        for ($day = 1; $day <= 7; $day++) {
            $hours[(string) $day] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '22:00']]];
        }
        VenueOperatingSchedule::create([
            'venue_id' => $venue->id,
            'scope_key' => 'venue',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'version' => 1,
            'weekly_hours' => $hours,
            'created_by' => $owner->id,
        ]);

        $receiver = new ReceivingConnection([
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'owner_user_id' => $owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'owner_attested_at' => now(),
            'verified_at' => now()->subMinute(),
            'effective_from' => now()->subMinute(),
        ]);
        $receiver->setAccountDetails('Venue Owner', '0123456789');
        $receiver->webhook_secret_ciphertext = Crypt::encryptString('test-webhook-secret');
        $receiver->save();
        $venue->forceFill(['current_receiving_connection_id' => $receiver->id])->save();

        Sanctum::actingAs($owner);

        $res = $this->getJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/readiness");
        $res->assertOk()
            ->assertJsonPath('data.is_ready', true)
            ->assertJsonCount(0, 'data.missing_items');

        // Verify checklist does not mention or require cancellation policy
        $checklist = $res->json('data.checklist');
        $this->assertArrayNotHasKey('cancellation_policy', $checklist);
        $this->assertArrayNotHasKey('cancellation_grace_hours', $checklist);
    }

    public function test_preserved_historical_snapshots_and_continued_wallet_checkout_and_checkin(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $staff = User::factory()->create(['role' => 'customer']);
        $customer = User::factory()->create(['role' => 'customer']);

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $staff->id, 'role' => 'staff', 'status' => 'active', 'permissions' => ['venue_ids' => [$venue->id]]]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $customer->id, 'role' => 'customer', 'status' => 'active']);

        // Fund customer wallet
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $walletService->credit($wallet, 500000, 'test_topup', 1, 'Top up');

        $baseDate = Carbon::parse('2026-10-05 14:00:00', config('app.timezone'));
        Carbon::setTestNow($baseDate);
        try {
            $date = $baseDate->copy()->setTime(20, 0);
            CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '20:00', 'ends_at' => '21:00', 'price' => 100000, 'is_active' => true]);
            // 1. Preserved historical booking with old snapshot
            $historicalBooking = Booking::create([
                'code' => 'BK-HIST-POLICY-01',
                'user_id' => $customer->id,
                'court_id' => $court->id,
                'total_amount' => 100000,
                'status' => 'confirmed',
                'policy_snapshot' => ['cancellation_grace_hours' => 24, 'version' => 'legacy'],
                'price_snapshot' => ['base_price' => 100000],
            ]);
            $historicalBooking->slots()->create([
                'court_id' => $court->id,
                'starts_at' => Carbon::now()->addDays(5)->setTime(10, 0),
                'ends_at' => Carbon::now()->addDays(5)->setTime(11, 0),
                'unit_price' => 100000,
                'status' => 'confirmed',
            ]);

            // Historical bookings are read through their mapped reservation.
            app(LegacyBookingBackfillService::class)->backfillBooking($historicalBooking);
            Sanctum::actingAs($customer);
            $this->getJson('/api/reservations/LEGACY-'.$historicalBooking->code)
                ->assertOk()
                ->assertJsonPath('reservation.legacy_mapping.0.legacy_code', $historicalBooking->code);
            $this->assertEquals(24, $historicalBooking->fresh()->policy_snapshot['cancellation_grace_hours']);

            // 2. Continued ordinary wallet checkout
            $checkoutRes = $this->actingAs($customer)->withHeader('Idempotency-Key', uniqid('idem-'))
                ->postJson('/api/reservations/checkout', [
                    'court_id' => $court->id,
                    'slots' => [$date->toIso8601String()],
                ])->assertCreated();

            $newBookingCode = $checkoutRes->json('reservation.code');
            $newReservation = Reservation::where('code', $newBookingCode)->firstOrFail();
            $this->assertSame('confirmed', $newReservation->status);
            $this->assertSame(400000, (int) $wallet->fresh()->balance);

            // New snapshot does not have cancellation_grace_hours
            $this->assertArrayNotHasKey('cancellation_grace_hours', $newReservation->metadata['booking_policy_snapshot'] ?? []);
            // 3. Continued check-in behavior (during arrival window)
            Carbon::setTestNow($date);
            Sanctum::actingAs($staff);
            $checkInRes = $this->withHeaders([
                'X-Organization-ID' => (string) $org->id,
                'Idempotency-Key' => uniqid('idem-'),
            ])->postJson('/api/attendance/check-in', [
                'code' => $newBookingCode,
            ]);
            $checkInRes->assertCreated();
            $this->assertSame('checked_in', $checkInRes->json('attendance.status'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
