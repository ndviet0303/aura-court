<?php

namespace Tests\Feature;

use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_demo_admin_creates_a_verified_admin_account(): void
    {
        $this->artisan('demo:issue', [
            '--admin' => true,
            '--name' => 'Quản trị kiểm thử',
            '--email' => 'demo-command@demo.auracourt.vn',
        ])->assertSuccessful();

        $user = User::where('email', 'demo-command@demo.auracourt.vn')->firstOrFail();

        $this->assertSame('Quản trị kiểm thử', $user->name);
        $this->assertSame('admin', $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_demo_admin_mutations_are_rolled_back(): void
    {
        $admin = User::factory()->create([
            'email' => 'demo-sandbox@demo.auracourt.vn',
            'role' => 'admin',
            'name' => 'Admin demo',
        ]);
        OrganizationMember::create(['organization_id' => Venue::firstOrFail()->organization_id, 'user_id' => $admin->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);

        $this->actingAs($admin)->patchJson('/api/profile', ['name' => 'Tên mới'])
            ->assertOk();

        $this->assertSame('Tên mới', $admin->fresh()->name);

        $this->actingAs($admin)->postJson('/api/admin/courts', [
            'name' => 'Sân mô phỏng',
            'venue_id' => Venue::firstOrFail()->id,
            'sport_type' => 'badminton',
            'description' => 'Không được lưu',
            'image_url' => 'https://example.com/demo-simulated.jpg',
            'is_active' => true,
        ])->assertCreated()->assertHeader('X-Demo-Simulated', 'true');

        $this->assertDatabaseMissing('courts', ['image_url' => 'https://example.com/demo-simulated.jpg']);
    }
}
