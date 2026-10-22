<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PackagePaymentReceiptRepositoryInterface;
use App\Models\PackagePaymentReceipt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentPackagePaymentReceiptRepository implements PackagePaymentReceiptRepositoryInterface
{
    public function createUnlessRecorded(array $attributes): ?PackagePaymentReceipt
    {
        try {
            // A savepoint, so a duplicate key does not abort the caller's PostgreSQL transaction.
            return DB::transaction(fn () => PackagePaymentReceipt::query()->create($attributes));
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'provider_transaction_id')) {
                throw $exception;
            }

            return null;
        }
    }

    public function update(PackagePaymentReceipt $receipt, array $attributes): PackagePaymentReceipt
    {
        $receipt->update($attributes);

        return $receipt;
    }
}
