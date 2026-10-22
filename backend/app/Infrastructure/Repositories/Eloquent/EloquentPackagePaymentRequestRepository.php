<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PackagePaymentRequestRepositoryInterface;
use App\Models\PackagePaymentRequest;
use Carbon\CarbonInterface;

class EloquentPackagePaymentRequestRepository implements PackagePaymentRequestRepositoryInterface
{
    public function expireOverdueForVenue(int $venueId, CarbonInterface $now): int
    {
        // The column holds UTC wall time and the grammar binds a Carbon without converting it.
        return PackagePaymentRequest::query()
            ->where('venue_id', $venueId)
            ->where('status', PackagePaymentRequest::STATUS_PENDING)
            ->where('expires_at', '<=', $now->copy()->utc())
            ->update(['status' => PackagePaymentRequest::STATUS_EXPIRED]);
    }

    public function findPendingForVenue(int $venueId): ?PackagePaymentRequest
    {
        return PackagePaymentRequest::query()
            ->where('venue_id', $venueId)
            ->where('status', PackagePaymentRequest::STATUS_PENDING)
            ->first();
    }

    public function findByCodeForOrganization(string $requestCode, int $organizationId): ?PackagePaymentRequest
    {
        return PackagePaymentRequest::query()
            ->where('organization_id', $organizationId)
            ->where('request_code', $requestCode)
            ->first();
    }

    public function findByPaymentCode(string $paymentCode): ?PackagePaymentRequest
    {
        return PackagePaymentRequest::query()->where('payment_code', $paymentCode)->first();
    }

    public function findForUpdate(int $id): PackagePaymentRequest
    {
        return PackagePaymentRequest::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function codesTaken(string $requestCode, string $paymentCode): bool
    {
        return PackagePaymentRequest::query()
            ->where('request_code', $requestCode)
            ->orWhere('payment_code', $paymentCode)
            ->exists();
    }

    public function create(array $attributes): PackagePaymentRequest
    {
        return PackagePaymentRequest::query()->create($attributes);
    }

    public function update(PackagePaymentRequest $request, array $attributes): PackagePaymentRequest
    {
        $request->update($attributes);

        return $request;
    }
}
