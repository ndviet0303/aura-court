<?php

namespace App\Domain\Wallet\Actions;

use App\Services\Loyalty\CustomerMembershipService;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\Payment\WalletPaymentService;

class GetCustomerWallet
{
    public function __construct(
        private readonly WalletPaymentService $wallets,
        private readonly LoyaltyPointService $points,
        private readonly CustomerMembershipService $memberships,
    ) {}

    public function execute(int $userId, ?int $organizationId): array
    {
        $wallet = $this->wallets->getOrCreateWallet($userId, $organizationId);
        $walletOrganizationId = (int) $wallet->organization_id;
        $membership = $this->memberships->active($userId, $walletOrganizationId);

        return [
            'id' => $organizationId ? "wallet-org-{$walletOrganizationId}" : 'wallet-main',
            'organization_id' => $wallet->organization_id,
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'points' => $this->points->availableBalance($walletOrganizationId, $userId),
            'membership_tier' => $membership?->plan->name,
            'transactions' => $this->wallets->recentTransactions($wallet),
        ];
    }
}
