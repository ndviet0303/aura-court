<?php

namespace Tests\Feature;

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

        $this->actingAs($admin)->patchJson('/api/v1/profile', ['name' => 'Tên mới'])
            ->assertOk();

        $this->assertSame('Tên mới', $admin->fresh()->name);

        $this->actingAs($admin)->postJson('/api/v1/admin/courts', [
            'name' => 'Sân mô phỏng',
            'venue_id' => Venue::firstOrFail()->id,
            'description' => 'Không được lưu',
            'is_active' => true,
        ])->assertCreated()->assertHeader('X-Demo-Simulated', 'true');

        $this->assertDatabaseMissing('courts', ['name' => 'Sân mô phỏng']);
    }
}
