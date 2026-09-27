<?php

namespace Tests\Feature\Venue;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Models\VenueOperatingSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VenueReadinessChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function addBookingPolicy(Venue $venue, int $ownerId): void
    {
        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $ownerId,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'none', 'fee_value' => 0, 'refund_due_hours' => 48],
                'change' => ['deadline_hours' => 12, 'fee_type' => 'none', 'fee_value' => 0],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true],
                'ancillary_credit_policy' => 'refund',
            ],
        ]);
    }

    private function addOperatingSchedule(Venue $venue, int $ownerId): void
    {
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
            'created_by' => $ownerId,
        ]);
    }

    public function test_incomplete_venue_returns_actionable_missing_items(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Incomplete Club',
            'slug' => 'incomplete-club',
            'status' => 'active',
            'partner_status' => 'pending', // Not approved yet!
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sân Cầu Lông',
            'slug' => 'san-cau-long',
            'address' => '123 Đường Số 1',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $receiver = new ReceivingConnection([
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'owner_user_id' => $owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_PENDING_VERIFICATION,
            'credential_version' => 1,
            'owner_attested_at' => now(),
        ]);
        $receiver->setAccountDetails('Venue Owner', '0123456789');
        $receiver->webhook_secret_ciphertext = Crypt::encryptString('test-webhook-secret');
        $receiver->save();
        $venue->forceFill(['current_receiving_connection_id' => $receiver->id])->save();

        Sanctum::actingAs($owner);

        // Check readiness: No subscription, partner pending, no courts
        $res = $this->getJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/readiness");

        $res->assertStatus(200)
            ->assertJsonPath('data.is_ready', false)
            ->assertJsonPath('data.checklist.receiving_connection_selected', false)
            ->assertJsonStructure(['data' => ['missing_items', 'checklist']]);

        $missingCodes = collect($res->json('data.missing_items'))->pluck('code')->all();

        $this->assertContains('ORG_NOT_APPROVED_PARTNER', $missingCodes);
        $this->assertContains('SUBSCRIPTION_REQUIRED', $missingCodes);
        $this->assertContains('NO_ACTIVE_COURTS', $missingCodes);
        $this->assertContains('BOOKING_POLICY_REQUIRED', $missingCodes);
        $this->assertContains('RECEIVING_CONNECTION_REQUIRED', $missingCodes);

        $this->postJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/receiving-connection/{$receiver->id}/select")
            ->assertConflict();

        // A stale or manually corrupted verified flag cannot replace the owner's recorded authorization.
        $receiver->forceFill([
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'owner_attested_at' => null,
            'verified_at' => now(),
            'effective_from' => now(),
        ])->save();
        $this->getJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/readiness")
            ->assertOk()
            ->assertJsonPath('data.checklist.receiving_connection_selected', false);
    }

    public function test_missing_court_prices_blocks_readiness(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Price Check Club',
            'slug' => 'price-check-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'P', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sân Hà Nội',
            'slug' => 'san-ha-noi',
            'address' => '99 Tran Duy Hung',
            'status' => 'draft',
            'is_active' => false,
        ]);

        // Create active court without price slots!
        Court::create([
            'venue_id' => $venue->id,
            'name' => 'Sân 1',
            'slug' => 'san-1',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        $res = $this->getJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/readiness");
        $res->assertStatus(200)
            ->assertJsonPath('data.is_ready', false);

        $missingCodes = collect($res->json('data.missing_items'))->pluck('code')->all();
        $this->assertContains('COURT_PRICES_MISSING', $missingCodes);
        $this->assertContains('BOOKING_POLICY_REQUIRED', $missingCodes);
    }

    public function test_complete_venue_passes_readiness_and_optional_staff_does_not_block(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Ready Club',
            'slug' => 'ready-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'P_READY', 'name' => 'Plan', 'price' => 200000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sân Cầu Lông Đạt Chuẩn',
            'slug' => 'san-dat-chuan',
            'address' => '100 Hoang Quoc Viet, Cau Giay, Ha Noi',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Sân Thi Đấu 1',
            'slug' => 'san-thi-dau-1',
            'is_active' => true,
        ]);

        // Add active price slot
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 120000,
            'is_active' => true,
        ]);
        $this->addBookingPolicy($venue, $owner->id);
        $this->addOperatingSchedule($venue, $owner->id);
        $this->selectVerifiedReceiver($venue, $owner);

        // Note: Organization has 0 staff and 0 POS products.
        Sanctum::actingAs($owner);

        $res = $this->getJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/readiness");

        $res->assertStatus(200)
            ->assertJsonPath('data.is_ready', true)
            ->assertJsonCount(0, 'data.missing_items')
            ->assertJsonPath('data.checklist.partner_approved', true)
            ->assertJsonPath('data.checklist.subscription_entitled', true)
            ->assertJsonPath('data.checklist.active_courts', true)
            ->assertJsonPath('data.checklist.court_pricing', true)
            ->assertJsonPath('data.checklist.receiving_connection_selected', true);
    }

    private function selectVerifiedReceiver(Venue $venue, User $owner): void
    {
        Sanctum::actingAs($owner);
        $baseUrl = "/api/v1/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection";
        $created = $this->postJson($baseUrl, [
            'bank_code' => 'MBBank',
            'account_name' => 'Venue Owner',
            'account_number' => '0123456789',
            'legal_owner_confirmed' => true,
        ])->assertCreated();
        $connectionId = (int) $created->json('data.id');
        $connection = ReceivingConnection::findOrFail($connectionId);
        $secret = 'whsec_'.Str::random(48);
        $this->postJson($baseUrl."/{$connectionId}/credential", ['hmac_secret' => $secret])->assertOk();

        $payload = [
            'id' => 'readiness-'.Str::uuid(),
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '0123456789',
            'transferType' => 'in',
            'transferAmount' => 1000,
            'referenceCode' => 'VERIFY',
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $this->call('POST', '/api/v1/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret),
        ], $rawBody)->assertOk();

        $this->postJson($baseUrl."/{$connectionId}/select")
            ->assertOk()
            ->assertJsonPath('data.is_selected', true);
    }
}
