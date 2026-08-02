<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSandboxTest extends TestCase
{
    use RefreshDatabase;

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
            'description' => 'Không được lưu',
            'is_active' => true,
        ])->assertCreated()->assertHeader('X-Demo-Simulated', 'true');

        $this->assertDatabaseMissing('courts', ['name' => 'Sân mô phỏng']);
    }
}
