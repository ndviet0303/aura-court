<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformSubscription;
use Illuminate\Support\Collection;

class EloquentPlatformSubscriptionRepository implements PlatformSubscriptionRepositoryInterface
{
    public function findActiveForOrganization(int $organizationId): ?PlatformSubscription
    {
        return PlatformSubscription::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['active', 'trial'])
            ->with(['plan', 'organization'])
            ->latest()
            ->first();
    }

    public function findActiveForOrganizationWithLock(int $organizationId): ?PlatformSubscription
    {
        return PlatformSubscription::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['active', 'trial'])
            ->lockForUpdate()
            ->with(['plan', 'organization'])
            ->latest()
            ->first();
    }

    public function findLatestPaymentAttemptForOrganization(int $organizationId): ?PlatformPaymentAttempt
    {
        return PlatformPaymentAttempt::query()
            ->where('organization_id', $organizationId)
            ->latest('id')
            ->first();
    }

    public function getExpiredActiveSubscriptions(\DateTimeInterface $now): Collection
    {
        return PlatformSubscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->with(['organization.members'])
            ->lockForUpdate()
            ->get();
    }

    public function hasUsedTrial(int $organizationId): bool
    {
        return PlatformSubscription::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('trial_ends_at')
            ->exists();
    }

    public function findById(int $id): ?PlatformSubscription
    {
        return PlatformSubscription::query()
            ->with(['plan', 'organization', 'paymentAttempts'])
            ->find($id);
    }

    public function findByIdWithLock(int $id): ?PlatformSubscription
    {
        return PlatformSubscription::query()
            ->lockForUpdate()
            ->with(['plan', 'organization', 'paymentAttempts'])
            ->find($id);
    }

    public function create(array $attributes): PlatformSubscription
    {
        return PlatformSubscription::create($attributes);
    }

    public function update(PlatformSubscription $subscription, array $attributes): PlatformSubscription
    {
        $subscription->update($attributes);

        return $subscription->fresh(['plan', 'organization', 'paymentAttempts']);
    }

    public function createPaymentAttempt(array $attributes): PlatformPaymentAttempt
    {
        return PlatformPaymentAttempt::create($attributes);
    }

    public function findPaymentAttemptByReference(string $referenceCode): ?PlatformPaymentAttempt
    {
        return PlatformPaymentAttempt::query()
            ->where('reference_code', $referenceCode)
            ->with(['subscription.plan', 'organization', 'receipt'])
            ->first();
    }

    public function findPaymentAttemptByReferenceWithLock(string $referenceCode): ?PlatformPaymentAttempt
    {
        return PlatformPaymentAttempt::query()
            ->where('reference_code', $referenceCode)
            ->lockForUpdate()
            ->with(['subscription.plan', 'organization', 'receipt'])
            ->first();
    }

    public function updatePaymentAttempt(PlatformPaymentAttempt $attempt, array $attributes): PlatformPaymentAttempt
    {
        $attempt->update($attributes);

        return $attempt->fresh(['subscription.plan', 'organization', 'receipt']);
    }

    public function recordReceipt(array $attributes): PlatformProviderReceipt
    {
        return PlatformProviderReceipt::create($attributes);
    }

    public function findReceiptByTransactionId(string $provider, string $transactionId): ?PlatformProviderReceipt
    {
        return PlatformProviderReceipt::query()
            ->where('provider', $provider)
            ->where('provider_transaction_id', $transactionId)
            ->with('paymentAttempt')
            ->first();
    }
}
