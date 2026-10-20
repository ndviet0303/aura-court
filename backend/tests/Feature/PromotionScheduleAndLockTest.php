<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionScheduleAndLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_offset_times_are_stored_as_app_wall_clock_and_survive_a_resave(): void
    {
        $headers = $this->ownerHeaders();
        $startsAt = Carbon::now('UTC')->addDays(10)->setTime(3, 0);
        $endsAt = $startsAt->copy()->addDay();
        $local = $startsAt->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');

        $created = $this->withHeaders($headers)->postJson('/api/admin/coupons', [
            'code' => 'zone10', 'discount_type' => 'fixed', 'discount_value' => 10000,
            'starts_at' => $startsAt->toIso8601ZuluString(), 'ends_at' => $endsAt->toIso8601ZuluString(),
        ])->assertCreated();
        $coupon = Coupon::with('campaign')->findOrFail($created->json('coupon.id'));
        $this->assertSame($local, $coupon->getRawOriginal('starts_at'));
        $this->assertSame($local, $coupon->campaign->getRawOriginal('starts_at'));
        $this->assertTrue($coupon->starts_at->eq($startsAt));

        // The edit screen sends back the serialized times it was given along with the changed field.
        $this->withHeaders($headers)->patchJson("/api/admin/coupons/{$coupon->id}", [
            'campaign_name' => 'Renamed',
            'starts_at' => $created->json('coupon.starts_at'),
            'ends_at' => $created->json('coupon.ends_at'),
            'campaign_starts_at' => $created->json('coupon.campaign.starts_at'),
        ])->assertOk();
        $this->assertSame($local, $coupon->fresh()->getRawOriginal('starts_at'));
        $this->assertSame($local, $coupon->campaign->fresh()->getRawOriginal('starts_at'));
    }

    public function test_end_must_be_after_start(): void
    {
        $headers = $this->ownerHeaders();
        $at = now()->addDays(3)->toIso8601String();

        $this->withHeaders($headers)->postJson('/api/admin/coupons', [
            'code' => 'SAME', 'discount_type' => 'fixed', 'discount_value' => 10000, 'starts_at' => $at, 'ends_at' => $at,
        ])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);
        $this->assertSame(0, Coupon::count());
    }

    public function test_code_and_discount_type_lock_once_the_coupon_is_issued(): void
    {
        $headers = $this->ownerHeaders();
        $id = $this->withHeaders($headers)->postJson('/api/admin/coupons', ['code' => 'DRAFT1', 'discount_type' => 'fixed', 'discount_value' => 10000])
            ->assertCreated()->json('coupon.id');
        $campaignId = Coupon::findOrFail($id)->promotion_campaign_id;

        $this->withHeaders($headers)->patchJson("/api/admin/coupons/{$id}", ['code' => 'draft2', 'discount_type' => 'percent', 'discount_value' => 10])
            ->assertOk()->assertJsonPath('coupon.code', 'DRAFT2')->assertJsonPath('coupon.discount_type', 'percent');

        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/activate")->assertOk();
        $this->withHeaders($headers)->postJson("/api/admin/coupons/{$id}/activate")->assertOk();
        foreach (['active', 'suspended'] as $status) {
            if ($status === 'suspended') {
                $this->withHeaders($headers)->postJson("/api/admin/coupons/{$id}/suspend")->assertOk();
            }
            $this->withHeaders($headers)->patchJson("/api/admin/coupons/{$id}", ['code' => 'OTHER'])
                ->assertUnprocessable()->assertJsonValidationErrors(['code']);
            $this->withHeaders($headers)->patchJson("/api/admin/coupons/{$id}", ['discount_type' => 'fixed'])
                ->assertUnprocessable()->assertJsonValidationErrors(['discount_type']);
            $this->withHeaders($headers)->patchJson("/api/admin/coupons/{$id}", ['code' => ' draft2 ', 'discount_type' => 'percent', 'discount_value' => 12])
                ->assertOk()->assertJsonPath('coupon.discount_value', 12);
        }
        $this->assertSame(['DRAFT2', 'percent'], array_values(Coupon::findOrFail($id)->only(['code', 'discount_type'])));
    }

    public function test_a_redeemed_draft_keeps_its_code(): void
    {
        $headers = $this->ownerHeaders();
        $id = $this->withHeaders($headers)->postJson('/api/admin/coupons', ['code' => 'USED1', 'discount_type' => 'fixed', 'discount_value' => 10000])
            ->assertCreated()->json('coupon.id');
        Coupon::whereKey($id)->update(['usage_count' => 1]);

        $this->withHeaders($headers)->patchJson("/api/admin/coupons/{$id}", ['code' => 'USED2'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    /** @return array<string, string> */
    private function ownerHeaders(): array
    {
        $organization = Organization::create(['name' => 'Promo Org', 'slug' => 'promo-org-'.Str::lower(Str::random(8)), 'status' => 'active', 'partner_status' => 'approved']);
        $plan = PlatformPlan::create(['code' => 'PROMO-'.Str::upper(Str::random(6)), 'name' => 'Promotion Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create(['organization_id' => $organization->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        Sanctum::actingAs($owner);

        return ['X-Organization-ID' => (string) $organization->id];
    }
}
