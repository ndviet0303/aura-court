<?php

namespace App\Contracts\Repositories;

use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformSubscription;
use Illuminate\Support\Collection;

interface PlatformSubscriptionRepositoryInterface
{
    public function findActiveForOrganization(int $organizationId): ?PlatformSubscription;

    public function findActiveForOrganizationWithLock(int $organizationId): ?PlatformSubscription;

    public function hasUsedTrial(int $organizationId): bool;

    public function findLatestPaymentAttemptForOrganization(int $organizationId): ?PlatformPaymentAttempt;

    public function getExpiredActiveSubscriptions(\DateTimeInterface $now): Collection;

    public function findById(int $id): ?PlatformSubscription;

    public function findByIdWithLock(int $id): ?PlatformSubscription;

    public function create(array $attributes): PlatformSubscription;

    public function update(PlatformSubscription $subscription, array $attributes): PlatformSubscription;

    public function createPaymentAttempt(array $attributes): PlatformPaymentAttempt;

    public function findPaymentAttemptByReference(string $referenceCode): ?PlatformPaymentAttempt;

    public function findPaymentAttemptByReferenceWithLock(string $referenceCode): ?PlatformPaymentAttempt;

    public function updatePaymentAttempt(PlatformPaymentAttempt $attempt, array $attributes): PlatformPaymentAttempt;

    public function recordReceipt(array $attributes): PlatformProviderReceipt;

    public function findReceiptByTransactionId(string $provider, string $transactionId): ?PlatformProviderReceipt;
}
