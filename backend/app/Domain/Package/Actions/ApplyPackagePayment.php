<?php

namespace App\Domain\Package\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\PackagePaymentReceiptRepositoryInterface;
use App\Contracts\Repositories\PackagePaymentRequestRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Domain\Package\Enums\PackageStatus;
use App\Models\PackagePaymentReceipt;
use App\Models\PackagePaymentRequest;
use App\Models\Venue;
use App\Models\VenuePackage;
use App\Services\Finance\FinancialPostingService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * D6: every PKG transaction is stored once with its outcome; only an exact, on-time payment of an unpaid request
 * activates or renews the venue's package. It never touches the venue's status or suspensions.
 */
class ApplyPackagePayment
{
    public function __construct(
        private readonly PackagePaymentReceiptRepositoryInterface $receipts,
        private readonly PackagePaymentRequestRepositoryInterface $requests,
        private readonly VenuePackageRepositoryInterface $packages,
        private readonly UserRepositoryInterface $users,
        private readonly FinancialPostingService $financials,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    /** Returns null for a repeated notification of a transaction already stored. */
    public function execute(string $paymentCode, array $data, string $raw): ?PackagePaymentReceipt
    {
        return DB::transaction(function () use ($paymentCode, $data, $raw): ?PackagePaymentReceipt {
            $now = now()->utc();
            $paidAt = $this->transactionTime($data['transactionDate'] ?? null, $now);
            // Unlinked until the locks below: linking now would lock the request row before the venue row.
            $receipt = $this->receipts->createUnlessRecorded([
                'provider' => PackagePaymentReceipt::PROVIDER_SEPAY,
                'provider_transaction_id' => (string) $data['id'],
                'payment_code' => $paymentCode,
                'amount' => (int) ($data['transferAmount'] ?? $data['amount'] ?? 0),
                'content' => (string) ($data['content'] ?? ''),
                'transferred_at' => $paidAt,
                'received_at' => $now,
                'outcome' => PackagePaymentReceipt::OUTCOME_UNKNOWN_CODE,
                'raw' => json_decode($raw, true) ?? $data,
            ]);
            if ($receipt === null) {
                return null;
            }

            $request = $this->requests->findByPaymentCode($paymentCode);
            if ($request === null) {
                return $receipt;
            }
            $venue = $this->packages->lockVenueForPackage($request->venue_id);
            $request = $this->requests->findForUpdate($request->id);
            $current = $this->packages->findCurrentForVenue($request->venue_id);

            $outcome = $this->outcome($request, $receipt->amount, $paidAt, $current);
            if ($outcome === PackagePaymentReceipt::OUTCOME_LATE && $request->status === PackagePaymentRequest::STATUS_PENDING) {
                $this->requests->update($request, ['status' => PackagePaymentRequest::STATUS_EXPIRED]);
            }
            $receipt = $this->receipts->update($receipt, ['package_payment_request_id' => $request->id, 'outcome' => $outcome]);
            if ($outcome !== PackagePaymentReceipt::OUTCOME_APPLIED) {
                return $receipt;
            }

            $this->activate($request, $current, $paidAt);
            $this->financials->postPackageFeeReceipt($receipt);
            DB::afterCommit(fn () => $this->notifyOwners($request, $venue));

            return $receipt;
        });
    }

    /** D6.4: the first rule that holds wins; activity and term are judged at the payment time, not on arrival. */
    private function outcome(PackagePaymentRequest $request, int $amount, CarbonInterface $paidAt, ?VenuePackage $current): string
    {
        return match (true) {
            $request->status === PackagePaymentRequest::STATUS_PAID => PackagePaymentReceipt::OUTCOME_ALREADY_PAID,
            $paidAt->gte($request->expires_at) => PackagePaymentReceipt::OUTCOME_LATE,
            $amount !== $request->amount => PackagePaymentReceipt::OUTCOME_AMOUNT_MISMATCH,
            $this->activeForPayment($current, $paidAt) && $current?->term_days !== $request->term_days => PackagePaymentReceipt::OUTCOME_TERM_CONFLICT,
            default => PackagePaymentReceipt::OUTCOME_APPLIED,
        };
    }

    /** D6.5: an active package of the same term moves its own end; otherwise a new package starts at the payment time. */
    private function activate(PackagePaymentRequest $request, ?VenuePackage $current, CarbonInterface $paidAt): void
    {
        $term = $request->term_days;
        if ($current !== null && $this->activeForPayment($current, $paidAt)) {
            $periodStartsAt = $current->ends_at;
            $package = $this->packages->update($current, ['ends_at' => $term->endsAt($periodStartsAt)]);
        } else {
            $periodStartsAt = $paidAt;
            $package = $this->packages->create([
                'organization_id' => $request->organization_id,
                'venue_id' => $request->venue_id,
                'term_days' => $term,
                'starts_at' => $paidAt,
                'ends_at' => $term->endsAt($paidAt),
            ]);
        }

        $this->requests->update($request, [
            'status' => PackagePaymentRequest::STATUS_PAID,
            'paid_at' => $paidAt,
            'venue_package_id' => $package->id,
            'period_starts_at' => $periodStartsAt,
            'period_ends_at' => $package->ends_at,
        ]);
    }

    /** A package that started after the payment time (a newer purchase applied first) still counts, so no row starts before the current one. */
    private function activeForPayment(?VenuePackage $current, CarbonInterface $paidAt): bool
    {
        return $current !== null && $current->statusAt($paidAt->max($current->starts_at)) === PackageStatus::Active;
    }

    /** D6.3: SePay sends the bank's Vietnam wall time, parsed as module 04 does; a missing or unreadable value means now. */
    private function transactionTime(mixed $value, CarbonInterface $now): CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return $now;
        }

        try {
            return Carbon::parse($value, 'Asia/Ho_Chi_Minh')->utc();
        } catch (\Throwable) {
            return $now;
        }
    }

    /** In-app only (D6); the key makes a rerun a no-op. */
    private function notifyOwners(PackagePaymentRequest $request, ?Venue $venue): void
    {
        $days = $request->term_days->value;
        $endsAt = $request->period_ends_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y');
        $venueName = $venue->name ?? "#{$request->venue_id}";

        foreach ($this->users->activeOwnerIds($request->organization_id) as $userId) {
            try {
                $this->notif->recordOutboxNotification(
                    key: "package.request.{$request->id}.paid.{$userId}",
                    userId: $userId,
                    eventType: 'package.request.paid',
                    payload: [
                        'title' => "Đã nhận phí gói {$days} ngày",
                        'body' => "Gói {$days} ngày của cơ sở {$venueName} đã được thanh toán, có hiệu lực đến {$endsAt}.",
                        'action_url' => '/partner/subscription',
                        'venue_id' => $request->venue_id,
                        'request_code' => $request->request_code,
                        'term_days' => $days,
                        'amount' => $request->amount,
                        'period_ends_at' => $request->period_ends_at->toISOString(),
                        'aggregate_type' => PackagePaymentRequest::class,
                        'aggregate_id' => $request->id,
                    ],
                    orgId: $request->organization_id,
                );
            } catch (\Throwable $e) {
                // The outbox row is already pending, so scheduled recovery resends it; keep notifying the other owners.
                Log::warning("Package payment notice failed immediately for request {$request->id}, user {$userId}: {$e->getMessage()}", [
                    'package_payment_request_id' => $request->id,
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
        }
    }
}
