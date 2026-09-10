<?php

namespace Tests\Feature\Auth;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_completes_personal_onboarding(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'onboarded' => false,
        ]);

        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/auth/onboarding', [
            'account_type' => 'customer',
            'phone' => '0987654321',
            'play_level' => 'intermediate',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('account_type', 'customer')
            ->assertJsonPath('next_step', 'explore_venues')
            ->assertJsonPath('user.onboarded', true);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->onboarded);
        $this->assertEquals('customer', $fresh->role);
        $this->assertEquals('0987654321', $fresh->phone);
        $this->assertEquals('intermediate', $fresh->play_level);

        $this->assertEquals(0, Organization::where('name', 'Should Not Be Created Directly')->count());
    }

    public function test_court_owner_intent_does_not_grant_admin_or_create_active_inventory(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'onboarded' => false,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/auth/onboarding', [
            'account_type' => 'court_owner',
            'phone' => '0912345678',
            'organization_name' => 'Should Not Be Created Directly',
            'venue_address' => '123 Fake Street',
            'courts_count' => 4,
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('account_type', 'court_owner')
            ->assertJsonPath('next_step', 'partner_registration')
            ->assertJsonPath('partner_registration_url', '/partner/register');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->onboarded);
        // User role remains customer (NOT admin!)
        $this->assertEquals('customer', $fresh->role);

        // ZERO organizations, venues, courts, or prices created!
        $this->assertEquals(0, Organization::where('name', 'Should Not Be Created Directly')->count());
    }

    public function test_cannot_repeat_onboarding_if_already_onboarded(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'onboarded' => true,
        ]);

        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/auth/onboarding', [
            'account_type' => 'customer',
        ]);

        $res->assertStatus(400);
    }

    public function test_onboarded_customer_can_independently_start_partner_registration(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'onboarded' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($customer);

        // Can start partner application without repeating personal onboarding!
        $startRes = $this->postJson('/api/v1/partner/application/start', []);
        $startRes->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $this->assertEquals(1, HostKyc::where('user_id', $customer->id)->count());
    }
}
