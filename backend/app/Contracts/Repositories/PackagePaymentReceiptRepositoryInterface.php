<?php

namespace App\Contracts\Repositories;

use App\Models\PackagePaymentReceipt;

interface PackagePaymentReceiptRepositoryInterface
{
    /** D6.1: null when the provider transaction is already stored; the caller's transaction stays usable. */
    public function createUnlessRecorded(array $attributes): ?PackagePaymentReceipt;

    public function update(PackagePaymentReceipt $receipt, array $attributes): PackagePaymentReceipt;
}
