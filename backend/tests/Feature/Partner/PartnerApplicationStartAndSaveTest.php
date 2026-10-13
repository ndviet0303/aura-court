<?php

namespace Tests\Feature\Partner;

use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerApplicationStartAndSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_cannot_start_partner_application(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'phone' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/partner/application/start', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    }

    public function test_restricted_or_suspended_user_is_forbidden(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 'suspended',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/partner/application/start', []);

        $response->assertStatus(403);
    }

    public function test_existing_owner_is_directed_to_organization_and_blocked_from_new_draft(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $org = Organization::create([
            'name' => 'Existing Club',
            'slug' => 'existing-club',
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/partner/application/start', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    }

    public function test_eligible_user_starts_draft_and_resumes_on_duplicate_call(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'phone' => '0912345678',
        ]);

        Sanctum::actingAs($user);

        // Initial start creates draft
        $response1 = $this->postJson('/api/partner/application/start', []);

        $response1->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.is_current', true);

        $appId = $response1->json('data.id');

        // Calling start again resumes the existing draft without duplicate
        $response2 = $this->postJson('/api/partner/application/start', []);

        $response2->assertStatus(201)
            ->assertJsonPath('data.id', $appId)
            ->assertJsonPath('data.status', 'draft');

        $this->assertEquals(1, HostKyc::where('user_id', $user->id)->count());
    }

    public function test_can_save_draft_with_business_info_and_masking(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $startRes = $this->postJson('/api/partner/application/start', []);
        $appId = $startRes->json('data.id');

        $payload = [
            'business_type' => 'company',
            'business_name' => 'CLB Cầu Lông Hà Đông',
            'legal_name' => 'Công Ty TNHH Thể Thao Hà Đông',
            'tax_code' => '0109998888',
            'representative_name' => 'Phạm Văn D',
            'id_card_number' => '001099887766',
            'bank_name' => 'Vietcombank',
            'bank_code' => 'VCB',
            'bank_account_number' => '001100223344',
            'bank_account_holder' => 'CONG TY THE THAO HA DONG',
            'contact_name' => 'Phạm Văn D',
            'contact_phone' => '0988776655',
            'contact_email' => 'hadong@badminton.vn',
            'address_line' => 'Số 10 Phố Quang Trung',
            'ward' => 'Quang Trung',
            'district' => 'Hà Đông',
            'city' => 'Hà Nội',
            'sports' => ['badminton', 'pickleball'],
            'court_scale' => 6,
            'expected_version' => 1,
        ];

        $saveRes = $this->putJson("/api/partner/application/{$appId}", $payload);

        $saveRes->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.business_name', 'CLB Cầu Lông Hà Đông')
            ->assertJsonPath('data.id_card_number_masked', '001*****7766')
            ->assertJsonPath('data.bank_account_number_masked', '********3344');
    }

    public function test_optimistic_lock_rejects_stale_expected_version(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $startRes = $this->postJson('/api/partner/application/start', []);
        $appId = $startRes->json('data.id');

        // First save bumps version to 2
        $this->putJson("/api/partner/application/{$appId}", [
            'business_name' => 'Name 1',
            'expected_version' => 1,
        ])->assertStatus(200);

        // Second save still using expected_version 1 must fail with 409
        $staleRes = $this->putJson("/api/partner/application/{$appId}", [
            'business_name' => 'Name 2',
            'expected_version' => 1,
        ]);

        $staleRes->assertStatus(409);
    }

    public function test_cannot_save_another_users_application(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        Sanctum::actingAs($owner);
        $startRes = $this->postJson('/api/partner/application/start', []);
        $appId = $startRes->json('data.id');

        Sanctum::actingAs($stranger);
        $res = $this->putJson("/api/partner/application/{$appId}", [
            'business_name' => 'Hacked',
        ]);

        $res->assertStatus(403);
    }
}
