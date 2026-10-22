<?php

namespace App\Domain\Package\Actions;

use App\Contracts\Repositories\PackagePaymentRequestRepositoryInterface;
use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Domain\Package\DTOs\CreatePackagePaymentRequestData;
use App\Domain\Package\Enums\PackageStatus;
use App\Exceptions\PackageRuleException;
use App\Models\PackagePaymentRequest;
use App\Services\Package\PackagePriceCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * D4: a venue has at most one unexpired pending request; asking again for its term returns it unchanged
 * (wasRecentlyCreated false). The kind follows the package at now, and term, price and amount are fixed here.
 */
class CreatePackagePaymentRequest
{
    public const PAYMENT_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly VenuePackageRepositoryInterface $packages,
        private readonly PackagePaymentRequestRepositoryInterface $requests,
        private readonly PackagePriceCatalog $catalog,
    ) {}

    public function execute(CreatePackagePaymentRequestData $data): PackagePaymentRequest
    {
        // Without AuraCourt's receiving account a transfer has nowhere to go, so nothing is written.
        if (blank(config('services.sepay.account'))) {
            throw new PackageRuleException(503, 'PACKAGE_PAYMENT_UNAVAILABLE', 'Chưa thể nhận phí gói lúc này. Vui lòng thử lại sau.');
        }

        return DB::transaction(function () use ($data): PackagePaymentRequest {
            $now = now()->utc();
            $venue = $this->packages->lockVenueForPackage($data->venueId);
            abort_if($venue === null || (int) $venue->organization_id !== $data->organizationId, 404, 'Cơ sở thể thao không tồn tại.');
            if ($venue->isClosed()) {
                throw new PackageRuleException(422, 'VENUE_CLOSED', 'Cơ sở đã đóng vĩnh viễn nên không đăng ký gói được.');
            }

            $this->requests->expireOverdueForVenue($venue->id, $now);
            $pending = $this->requests->findPendingForVenue($venue->id);
            if ($pending !== null) {
                if ($pending->term_days === $data->term) {
                    return $pending;
                }

                throw new PackageRuleException(409, 'PACKAGE_REQUEST_PENDING', 'Cơ sở đang có yêu cầu thanh toán gói khác chưa hết hạn.', $pending);
            }

            $current = $this->packages->findCurrentForVenue($venue->id);
            $renewal = $current !== null && $current->statusAt($now) === PackageStatus::Active;
            if ($renewal && $current->term_days !== $data->term) {
                throw new PackageRuleException(409, 'PACKAGE_TERM_LOCKED', 'Gia hạn phải cùng kỳ hạn với gói đang dùng.');
            }

            $price = $this->catalog->applicableAt($data->term, $now);
            if ($price === null) {
                throw new PackageRuleException(422, 'PACKAGE_PRICE_UNAVAILABLE', 'Kỳ hạn này chưa có giá áp dụng.');
            }

            [$requestCode, $paymentCode] = $this->uniqueCodes();

            return $this->requests->create([
                'request_code' => $requestCode,
                'payment_code' => $paymentCode,
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->id,
                'kind' => $renewal ? PackagePaymentRequest::KIND_RENEWAL : PackagePaymentRequest::KIND_PURCHASE,
                'term_days' => $data->term,
                'amount' => $price->amount,
                'package_price_id' => $price->id,
                'expires_at' => $now->copy()->addMinutes(self::PAYMENT_WINDOW_MINUTES),
                'status' => PackagePaymentRequest::STATUS_PENDING,
                'created_by' => $data->createdBy,
            ]);
        });
    }

    /** @return array{string, string} */
    private function uniqueCodes(): array
    {
        do {
            $codes = ['PKR'.strtoupper(Str::random(8)), 'PKG'.strtoupper(Str::random(8))];
        } while ($this->requests->codesTaken(...$codes));

        return $codes;
    }
}
