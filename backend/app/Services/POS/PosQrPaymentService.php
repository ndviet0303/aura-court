<?php

namespace App\Services\POS;

use App\Contracts\Repositories\VenueStockRepositoryInterface;
use App\Models\CashShift;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\PosOrder;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\FinancialPostingService;
use App\Services\Finance\VenueCreditService;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\ReservationPaymentService;
use Carbon\CarbonInterface;

/** POS QR tender pays the venue owner's verified receiving connection through a `pos_order` owner-payment attempt. */
class PosQrPaymentService
{
    public const UNAVAILABLE = 'Cơ sở chưa có tài khoản nhận tiền đã xác minh nên không thể thu bằng QR. Chủ sân cần kết nối và xác minh tài khoản nhận tiền SePay cho cơ sở này, hoặc thu tiền mặt.';

    public function __construct(
        private readonly OwnerPaymentAttemptService $attempts,
        private readonly ReservationPaymentService $payments,
        private readonly FinancialPostingService $financials,
        private readonly LoyaltyPointService $loyaltyPoints,
        private readonly VenueStockRepositoryInterface $stock,
        private readonly VenueCreditService $credits,
    ) {}

    /** The given venue, else the caller's only operable venue (BR-20: a staff member never falls back to the whole organization). */
    public function venueIdFor(User $cashier, int $organizationId, ?int $venueId): ?int
    {
        if ($venueId) {
            return $venueId;
        }
        $venueIds = $cashier->getAuthorizedVenueIds($organizationId);

        return count($venueIds) === 1 ? $venueIds[0] : null;
    }

    /** BR-20/21: a sale or shift belongs to a venue the caller operates, owners included. */
    public function assertOperableVenue(User $cashier, int $organizationId, ?int $venueId): void
    {
        abort_if($venueId === null, 422, 'Vui lòng chọn cơ sở bạn được giao.');
        $venue = Venue::query()->where('organization_id', $organizationId)->find($venueId);
        abort_unless($venue && $cashier->canOperateVenue($venue), 403, 'Bạn không được giao cơ sở này.');
    }

    /** D2: whose stock a read shows: the asked venue (403 outside the caller's), else the caller's open shift's, else their only one. */
    public function stockVenueId(User $user, int $organizationId, ?int $venueId): ?int
    {
        if ($venueId) {
            $this->assertOperableVenue($user, $organizationId, $venueId);

            return $venueId;
        }
        $shiftVenueId = CashShift::query()->where('organization_id', $organizationId)->where('cashier_id', $user->id)->where('status', 'open')->value('venue_id');

        return $shiftVenueId ? (int) $shiftVenueId : $this->venueIdFor($user, $organizationId, null);
    }

    public function connectionFor(int $organizationId, ?int $venueId): ?ReceivingConnection
    {
        $venue = $venueId ? Venue::query()->where('organization_id', $organizationId)->find($venueId) : null;
        $connection = $venue?->currentReceivingConnection;

        return $connection
            && (int) $connection->venue_id === (int) $venue->id
            && $connection->status === ReceivingConnection::STATUS_VERIFIED
            && $connection->webhook_secret_ciphertext !== null
            && $connection->verified_at !== null
            && ($connection->effective_until === null || $connection->effective_until->isFuture())
            ? $connection : null;
    }

    public function open(PosOrder $order, ReceivingConnection $connection, int $amount, CarbonInterface $expiresAt): OwnerPaymentAttempt
    {
        return $this->attempts->createAttempt(
            $connection, null, 'pos_order', (string) $order->id, $order->payment_reference,
            $amount, 'VND', $expiresAt, 'pos_order:'.$order->id,
        );
    }

    public function instructionsFor(PosOrder $order): ?array
    {
        $attempt = $this->attempts->latestForPurpose('pos_order', (string) $order->id);

        return $attempt ? $this->payments->instructions($attempt) : null;
    }

    public function expire(PosOrder $order): void
    {
        $this->attempts->expirePendingForPurpose('pos_order', (string) $order->id);
    }

    /** Called by receipt processing after it locked the attempt; locks the order in the same order. */
    public function hasConfirmableOrder(OwnerPaymentAttempt $attempt): bool
    {
        $order = $this->lockOrder((int) $attempt->purpose_id);

        if ($order === null || (int) $order->organization_id !== (int) $attempt->organization_id
            || $order->status !== 'pending_payment' || $order->payment_reference !== $attempt->reference) {
            return false;
        }
        // ponytail: read without a lock, since the account is locked after the stock rows below; a spend in between fails the receipt (D6).
        $credit = $this->pendingCredit($order);

        return $credit === 0 || $this->credits->balance((int) $order->organization_id, (int) $order->customer_id) >= $credit;
    }

    /** Completes the order once: stock, credit, QR payment row, loyalty. Revenue for the QR portion is the owner receipt journal. */
    public function confirmPaidAttempt(OwnerPaymentAllocation $allocation, CarbonInterface $paidAt): void
    {
        $order = $this->lockOrder((int) $allocation->purpose_id);
        $order->load(['items', 'payments', 'shift']);

        // The sale leaves the order's venue (D3), rows locked in product id order (D4).
        foreach ($order->items->load('product')->sortBy('product_id') as $item) {
            if (! $item->product->isTracked()) {
                continue;
            }
            abort_if($order->venue_id === null, 422, 'Đơn chưa gắn cơ sở nên không trừ kho được.');
            $stock = $this->stock->lock($order->organization_id, (int) $order->venue_id, $item->product_id);
            $this->stock->apply($stock, -$item->quantity, 'sale', $order->cashier_id, PosOrder::class, $order->id);
        }

        $credit = $this->pendingCredit($order);
        if ($credit > 0) {
            $this->credits->spend((int) $order->organization_id, (int) $order->customer_id, $credit, 'pos_order', $order->id, $order->cashier_id, $order->payment_reference);
        }
        $order->payments()->whereIn('method', ['qr', 'credit'])->where('status', 'pending')->update(['status' => 'paid']);

        $meta = $order->metadata ?? [];
        if (isset($meta['receipt'])) {
            $meta['receipt']['status'] = 'completed';
            $meta['receipt']['is_paid'] = true;
            $meta['receipt']['verified_at'] = $paidAt->toIso8601String();
        }
        // A payment that lands after the shift closed is tagged; the closed shift's figures stay as reconciled.
        if ($order->shift?->status === 'closed') {
            $meta['late_payment_after_shift_close'] = true;
            $meta['original_closed_shift_id'] = $order->shift->id;
        }
        $meta['owner_payment_allocation_id'] = $allocation->id;
        $order->update(['status' => 'completed', 'completed_at' => now(), 'metadata' => $meta]);

        $otherTenders = (int) $order->total_amount - (int) $allocation->amount - $credit;
        if ($otherTenders > 0) {
            $this->financials->postPosSale($order->organization_id, $order->id, $otherTenders, $order->payment_method, $order->payment_reference);
        }
        if ($order->customer_id && $this->loyaltyPoints->getActiveBaseEarnRule($order->organization_id) !== null) {
            app(PosService::class)->awardPoints($order->organization_id, $order->customer_id, $order->total_amount, PosOrder::class, $order->id);
        }
    }

    private function pendingCredit(PosOrder $order): int
    {
        return (int) $order->payments()->where('method', 'credit')->where('status', 'pending')->sum('amount');
    }

    private function lockOrder(int $orderId): ?PosOrder
    {
        return PosOrder::query()->withoutGlobalScope('tenant_isolation')->lockForUpdate()->find($orderId);
    }
}
