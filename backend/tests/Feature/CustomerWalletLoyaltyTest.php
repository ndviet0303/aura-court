<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PointLedger;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\Payment\WalletPaymentService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWalletLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_returns_ledger_points_and_active_tier_of_the_requested_organization(): void
    {
        $user = User::factory()->create();
        $orgA = $this->organization('A');
        $orgB = $this->organization('B');

        $wallet = WalletAccount::create(['organization_id' => $orgA->id, 'user_id' => $user->id, 'currency' => 'VND', 'balance' => 4488000, 'status' => 'active']);
        $this->walletCredit($wallet, 4000000, 1);
        $this->walletCredit($wallet, 488000, 2);

        // Available = 3000 - 136; the expired 400-point lot is excluded, unlike a naive sum (3264) or balance / 1000 (4488).
        $this->ledger($orgA, $user, 3000, 1);
        $this->ledger($orgA, $user, -136, 2);
        $this->ledger($orgA, $user, 400, 3, now()->subDay());
        $this->ledger($orgB, $user, 500, 4);

        $this->membership($orgA, $user, 'Gold Elite All-Access', now()->subDay(), now()->addMonth());
        $this->membership($orgA, $user, 'Diamond VIP Club', now()->subMonths(2), now()->subDay());
        $this->membership($orgB, $user, 'Silver Standard', now()->subDay(), now()->addMonth());

        $this->actingAs($user)->getJson("/api/wallet?organization_id={$orgA->id}")
            ->assertOk()
            ->assertJsonPath('id', "wallet-org-{$orgA->id}")
            ->assertJsonPath('organization_id', $orgA->id)
            ->assertJsonPath('balance', 4488000)
            ->assertJsonPath('points', 2864)
            ->assertJsonPath('membership_tier', 'Gold Elite All-Access')
            ->assertJsonCount(2, 'transactions')
            ->assertJsonPath('transactions.0.amount', 488000);

        $this->actingAs($user)->getJson("/api/wallet?organization_id={$orgB->id}")
            ->assertOk()
            ->assertJsonPath('balance', 0)
            ->assertJsonPath('points', 500)
            ->assertJsonPath('membership_tier', 'Silver Standard');
    }

    public function test_default_wallet_without_points_or_active_membership_has_no_tier(): void
    {
        $user = User::factory()->create();
        $defaultOrgId = app(WalletPaymentService::class)->getOrCreateWallet($user->id)->organization_id;
        $this->membership(Organization::findOrFail($defaultOrgId), $user, 'Gold Elite All-Access', now()->subMonths(2), now()->subDay());

        $this->actingAs($user)->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('id', 'wallet-main')
            ->assertJsonPath('organization_id', $defaultOrgId)
            ->assertJsonPath('points', 0)
            ->assertJsonPath('membership_tier', null);
    }

    public function test_wallet_rejects_an_invalid_or_unknown_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/wallet?organization_id=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('organization_id');
        $this->actingAs($user)->getJson('/api/wallet?organization_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('organization_id');
    }

    private function organization(string $name): Organization
    {
        return Organization::create(['name' => "Wallet Org {$name}", 'slug' => 'wallet-org-'.uniqid()]);
    }

    private function walletCredit(WalletAccount $wallet, int $amount, int $sourceId): void
    {
        WalletTransaction::create([
            'organization_id' => $wallet->organization_id,
            'wallet_account_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $amount,
            'source_type' => 'test_credit',
            'source_id' => $sourceId,
        ]);
    }

    private function ledger(Organization $org, User $user, int $delta, int $sourceId, ?CarbonInterface $expiresAt = null): void
    {
        PointLedger::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'delta' => $delta,
            'balance_after' => 0,
            'source_type' => 'test_points',
            'source_id' => $sourceId,
            'expires_at' => $expiresAt,
        ]);
    }

    private function membership(Organization $org, User $user, string $planName, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        $plan = TenantMembershipPlan::create([
            'organization_id' => $org->id,
            'name' => $planName,
            'version' => 1,
            'price' => 299000,
            'benefits' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);
        TenantMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
