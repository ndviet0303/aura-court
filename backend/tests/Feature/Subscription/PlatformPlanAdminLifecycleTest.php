<?php

namespace Tests\Feature\Subscription;

use App\Models\PlatformPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPlanAdminLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_revise_publish_and_retire_versioned_plans(): void
    {
        $admin = User::factory()->create(['role' => 'platform_admin']);
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/v1/admin/platform-plans', $this->planPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.is_active', false);
        $versionOneId = (int) $created->json('data.id');

        $this->getJson('/api/v1/platform/plans')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/admin/platform-plans/{$versionOneId}/publish", [
            'expected_version' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'published');

        $this->getJson('/api/v1/platform/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.version', 1);

        $revision = $this->patchJson("/api/v1/admin/platform-plans/{$versionOneId}", [
            'expected_version' => 1,
            'name' => 'Pro 2027',
            'price' => 700000,
            'terms_version' => '2027.1',
            'terms_text' => 'Approved Pro terms for 2027.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.price', 700000);
        $versionTwoId = (int) $revision->json('data.id');

        $this->assertDatabaseHas('platform_plans', [
            'id' => $versionOneId,
            'status' => 'published',
            'is_active' => true,
        ]);
        $this->getJson('/api/v1/platform/plans')->assertJsonPath('data.0.version', 1);

        $this->postJson("/api/v1/admin/platform-plans/{$versionTwoId}/publish", [
            'expected_version' => 2,
        ])->assertOk()->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('platform_plans', [
            'id' => $versionOneId,
            'status' => 'retired',
            'is_active' => false,
        ]);
        $this->getJson('/api/v1/platform/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $versionTwoId)
            ->assertJsonPath('data.0.version', 2);

        $this->postJson("/api/v1/admin/platform-plans/{$versionTwoId}/retire", [
            'expected_version' => 2,
        ])->assertOk()->assertJsonPath('data.status', 'retired');
        $this->getJson('/api/v1/platform/plans')->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(2, PlatformPlan::query()->count());
        $this->assertSame(0, DB::table('tenant_membership_plans')->count());
    }

    public function test_only_platform_admin_can_manage_plans_and_incomplete_plans_cannot_publish(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/platform-plans')->assertForbidden();
        $this->postJson('/api/v1/admin/platform-plans', $this->planPayload())->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $payload = $this->planPayload();
        $payload['price'] = 0;
        $payload['trial_enabled'] = false;
        $planId = $this->postJson('/api/v1/admin/platform-plans', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/admin/platform-plans/{$planId}/publish", [
            'expected_version' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['price']);

        $this->assertDatabaseHas('platform_plans', [
            'id' => $planId,
            'status' => 'draft',
            'is_active' => false,
        ]);
    }

    private function planPayload(): array
    {
        return [
            'code' => 'PRO',
            'name' => 'Pro',
            'price' => 500000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'trial_days' => 14,
            'trial_enabled' => true,
            'max_venues' => 2,
            'max_courts' => 10,
            'max_staff' => 5,
            'features' => ['online_booking', 'pos'],
            'terms_version' => '2026.1',
            'terms_text' => 'Approved Pro terms for 2026.',
        ];
    }
}
