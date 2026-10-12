<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilitiesWithoutV2ReservationsFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_capabilities_no_longer_expose_the_v2_reservations_flag(): void
    {
        $org = Organization::create(['name' => 'Caps Org', 'slug' => 'caps-'.uniqid()]);
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);

        $capabilities = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/capabilities')
            ->assertOk()
            ->json('capabilities');

        $this->assertArrayHasKey('organization_tenancy_enforced', $capabilities);
        $this->assertArrayNotHasKey('v2_reservations_enabled', $capabilities);
    }
}
