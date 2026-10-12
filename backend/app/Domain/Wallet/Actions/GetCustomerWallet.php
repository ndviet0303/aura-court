<?php

namespace App\Domain\Wallet\Actions;

use App\Services\Booking\DiscountService;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\Payment\WalletPaymentService;

class GetCustomerWallet
{
    public function __construct(
        private readonly WalletPaymentService $wallets,
        private readonly LoyaltyPointService $points,
        private readonly DiscountService $discounts,
    ) {}

    public function execute(int $userId, ?int $organizationId): array
    {
        $wallet = $this->wallets->getOrCreateWallet($userId, $organizationId);
        $walletOrganizationId = (int) $wallet->organization_id;
        // The resolver checkout uses, so the tier shown is the one discounts apply from.
        $membership = $this->discounts->activeMembership($userId, $walletOrganizationId);

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
