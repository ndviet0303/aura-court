<?php

namespace App\Contracts\Repositories;

use App\Models\PackagePaymentRequest;
use Carbon\CarbonInterface;

interface PackagePaymentRequestRepositoryInterface
{
    /** D4/D7: writes expired on pending requests whose deadline is at or before now, of one venue whose lock the caller holds, or of every venue. */
    public function expireOverdue(CarbonInterface $now, ?int $venueId = null): int;

    /** The venue's pending request; the partial unique index allows at most one. */
    public function findPendingForVenue(int $venueId): ?PackagePaymentRequest;

    public function findByCodeForOrganization(string $requestCode, int $organizationId): ?PackagePaymentRequest;

    public function findByPaymentCode(string $paymentCode): ?PackagePaymentRequest;

    /** D6: re-reads the request under a row lock, after the caller has locked its venue. */
    public function findForUpdate(int $id): PackagePaymentRequest;

    public function codesTaken(string $requestCode, string $paymentCode): bool;

    public function create(array $attributes): PackagePaymentRequest;

    public function update(PackagePaymentRequest $request, array $attributes): PackagePaymentRequest;
}
