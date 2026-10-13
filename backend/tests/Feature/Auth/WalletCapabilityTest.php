<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create(['name' => 'Wallet Org', 'slug' => 'wallet-org-'.uniqid(), 'status' => 'active']);
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_wallet_is_disabled_when_every_active_organization_is_direct_and_balance_is_zero(): void
    {
        config(['features.flags.direct_owner_payments' => true, 'features.pilot_organizations' => ['*']]);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('capabilities.wallet_enabled', false);
    }

    public function test_wallet_stays_enabled_for_a_remaining_balance(): void
    {
        config(['features.flags.direct_owner_payments' => true, 'features.pilot_organizations' => ['*']]);
        WalletAccount::create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'balance' => 50000, 'currency' => 'VND', 'status' => 'active']);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('capabilities.wallet_enabled', true);
    }

    public function test_wallet_stays_enabled_while_an_active_organization_uses_the_wallet_model(): void
    {
        $direct = Organization::create(['name' => 'Direct Org', 'slug' => 'direct-org-'.uniqid(), 'status' => 'active']);
        config(['features.flags.direct_owner_payments' => true, 'features.pilot_organizations' => [(string) $direct->id]]);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('capabilities.wallet_enabled', true);
    }
}
