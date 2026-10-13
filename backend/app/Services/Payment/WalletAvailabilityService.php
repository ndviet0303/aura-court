<?php

namespace App\Services\Payment;

use App\Models\Organization;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Infrastructure\FeatureFlagService;

/** Direct-owner cohorts pay the venue owner, so the pooled wallet takes no new deposits or tenders there. */
class WalletAvailabilityService
{
    public function __construct(private readonly FeatureFlagService $features) {}

    public function assertAvailable(int $organizationId, bool $usesWallet = true): void
    {
        if ($usesWallet && $this->features->enabled('direct_owner_payments', $organizationId)) {
            abort(response()->json([
                'message' => 'Ví Aura không dùng được ở cơ sở thanh toán trực tiếp cho chủ sân.',
                'code' => 'DIRECT_OWNER_PAYMENTS_ENABLED',
                'organization_id' => $organizationId,
            ], 409));
        }
    }

    /** Customer wallet surfaces stay while an active organization is still on the wallet model or the user holds a balance. */
    public function enabledFor(User $user): bool
    {
        return WalletAccount::query()->where('user_id', $user->id)->where('balance', '!=', 0)->exists()
            || Organization::query()->where('status', 'active')->pluck('id')
                ->contains(fn ($id): bool => ! $this->features->enabled('direct_owner_payments', (int) $id));
    }
}
