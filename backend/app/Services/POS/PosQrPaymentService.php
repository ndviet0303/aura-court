<?php

namespace App\Services\POS;

use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\ReceivingConnection;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\FinancialPostingService;
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
    ) {}

    /** The order's own venue, else the cashier's only venue in the organization; null when ambiguous. */
    public function venueIdFor(User $cashier, int $organizationId, ?int $venueId): ?int
    {
        if ($venueId) {
            return $venueId;
        }
        $member = OrganizationMember::query()->where('organization_id', $organizationId)
            ->where('user_id', $cashier->id)->where('status', 'active')->first();
        $venueIds = $member?->venue_ids ?: Venue::query()->where('organization_id', $organizationId)->pluck('id')->all();

        return count($venueIds) === 1 ? (int) $venueIds[0] : null;
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

        return $order !== null
            && (int) $order->organization_id === (int) $attempt->organization_id
            && $order->status === 'pending_payment'
            && $order->payment_reference === $attempt->reference;
    }

    /** Completes the order once: stock, QR payment row, coupon, loyalty. Revenue for the QR portion is the owner receipt journal. */
    public function confirmPaidAttempt(OwnerPaymentAllocation $allocation, CarbonInterface $paidAt): void
    {
        $order = $this->lockOrder((int) $allocation->purpose_id);
        $order->load(['items', 'payments', 'shift']);

        foreach ($order->items as $item) {
            $product = PosProduct::query()->where('organization_id', $order->organization_id)
                ->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            if (! $product->isTracked()) {
                continue;
            }
            abort_unless($product->stock_quantity >= $item->quantity, 409, "Insufficient stock for {$product->sku}.");
            $product->decrement('stock_quantity', $item->quantity);
            StockMovement::create([
                'organization_id' => $order->organization_id,
                'product_id' => $product->id,
                'type' => 'sale',
                'quantity_delta' => -$item->quantity,
                'unit_cost' => $product->cost,
                'reference_type' => PosOrder::class,
                'reference_id' => $order->id,
                'created_by' => $order->cashier_id,
            ]);
        }

        $order->payments()->where('method', 'qr')->where('status', 'pending')->update(['status' => 'paid']);

        $meta = $order->metadata ?? [];
        if (isset($meta['receipt'])) {
            $meta['receipt']['status'] = 'completed';
            $meta['receipt']['is_paid'] = true;
            $meta['receipt']['verified_at'] = $paidAt->toIso8601String();
        }
        if (($meta['coupon_usage']['status'] ?? null) === 'reserved') {
            $meta['coupon_usage']['status'] = 'consumed';
            $meta['coupon_usage']['consumed_at'] = now()->toIso8601String();
        }
        // A payment that lands after the shift closed is tagged; the closed shift's figures stay as reconciled.
        if ($order->shift?->status === 'closed') {
            $meta['late_payment_after_shift_close'] = true;
            $meta['original_closed_shift_id'] = $order->shift->id;
        }
        $meta['owner_payment_allocation_id'] = $allocation->id;
        $order->update(['status' => 'completed', 'completed_at' => now(), 'metadata' => $meta]);

        $otherTenders = (int) $order->total_amount - (int) $allocation->amount;
        if ($otherTenders > 0) {
            $this->financials->postPosSale($order->organization_id, $order->id, $otherTenders, $order->payment_method, $order->payment_reference);
        }
        if ($order->customer_id && $this->loyaltyPoints->getActiveBaseEarnRule($order->organization_id) !== null) {
            app(PosService::class)->awardPoints($order->organization_id, $order->customer_id, $order->total_amount, PosOrder::class, $order->id);
        }
    }

    private function lockOrder(int $orderId): ?PosOrder
    {
        return PosOrder::query()->withoutGlobalScope('tenant_isolation')->lockForUpdate()->find($orderId);
    }
}
