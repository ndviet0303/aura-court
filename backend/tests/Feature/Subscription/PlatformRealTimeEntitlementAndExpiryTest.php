<?php

namespace Tests\Feature\Subscription;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformRealTimeEntitlementAndExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_request_after_expiry_before_batch_job_is_rejected_and_transitions_subscription(): void
    {
        config(['partner.enforce_subscription_check' => true]);

        $owner = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $org = Organization::create([
            'name' => 'Realtime Expiry Club',
            'slug' => 'realtime-expiry-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Realtime Venue',
            'slug' => 'realtime-venue',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court 1',
            'slug' => 'court-1',
            'is_active' => true,
        ]);

        $targetSlot = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $targetSlot->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        $plan = PlatformPlan::create([
            'code' => 'EXPIRED_PLAN',
            'name' => 'Plan',
            'price' => 300000,
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        // Subscription had status 'active' but ends_at was 5 minutes ago!
        // The background cron job has NOT run yet!
        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active', // still says active in db!
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subMinutes(5), // Expired!
            'auto_renew' => false,
        ]);

        // Customer has wallet balance
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $wallet->increment('balance', 500000);

        Sanctum::actingAs($customer);

        // Attempting to book a court
        $bookingRes = $this->withHeader('Idempotency-Key', 'expired-subscription-booking')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$targetSlot->toIso8601String()],
            'expected_total' => 100000,
        ]);
        // Must be rejected in real time!
        $bookingRes->assertStatus(422)
            ->assertJsonValidationErrors(['subscription']);

        // And the subscription in DB is automatically and atomically transitioned to 'expired'!
        $this->assertEquals('expired', $sub->fresh()->status);

        // An outbox notification intent is recorded for the owner!
        $this->assertDatabaseHas('outbox_messages', [
            'organization_id' => $org->id,
            'event_type' => 'platform.subscription.expired',
        ]);
    }

    public function test_artisan_command_processes_batch_expired_subscriptions(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create([
            'code' => 'P',
            'name' => 'Plan',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subHour(),
        ]);

        $this->artisan('platform:process-expired-subscriptions')
            ->expectsOutput('Processed 1 expired subscription(s).')
            ->assertSuccessful();

        $this->assertEquals('expired', $sub->fresh()->status);
    }
}
