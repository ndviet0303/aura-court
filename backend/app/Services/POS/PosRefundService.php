<?php

namespace App\Services\POS;

use App\Contracts\Repositories\VenueStockRepositoryInterface;
use App\Models\CashShift;
use App\Models\PosOrder;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Support\Facades\DB;

/** Refunds of a completed POS order: stock back to the sale's venue, cash out of a register. */
class PosRefundService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly VenueStockRepositoryInterface $stock,
    ) {}

    public function refundOrder(
        PosOrder $order,
        User $cashier,
        array $items = [],
        string $reason = 'Customer return',
        string $refundMethod = 'cash'
    ): PosOrder {
        abort_unless($order->status === 'completed', 409, 'Only completed orders can be refunded.');
        abort_unless(in_array($refundMethod, ['cash', 'original_method', 'wallet'], true), 422, 'Unsupported refund method.');

        return DB::transaction(function () use ($order, $cashier, $items, $reason, $refundMethod): PosOrder {
            $order = PosOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'completed', 409, 'Only completed orders can be refunded.');

            $order->load(['items.product', 'payments']);

            $refundAmount = 0;
            $refundedItems = [];

            if (empty($items)) {
                $refundAmount = $order->total_amount;
                foreach ($order->items as $item) {
                    if ($item->product?->isTracked()) {
                        $this->restock($order, $item->product_id, $item->quantity, $cashier, $reason);
                    }
                    $refundedItems[] = [
                        'product_id' => $item->product_id,
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'amount' => $item->line_total,
                    ];
                }
            } else {
                foreach ($items as $reqItem) {
                    $productId = (int) ($reqItem['product_id'] ?? 0);
                    $qty = (int) ($reqItem['quantity'] ?? 1);
                    $restock = (bool) ($reqItem['restock'] ?? true);

                    $orderItem = $order->items->firstWhere('product_id', $productId);
                    abort_unless($orderItem !== null, 422, "Product ID {$productId} was not part of this order.");
                    abort_unless($qty > 0 && $qty <= $orderItem->quantity, 422, "Invalid refund quantity for {$orderItem->name}.");

                    $lineRefund = (int) round(($orderItem->line_total / $orderItem->quantity) * $qty);
                    $refundAmount += $lineRefund;

                    if ($restock && $orderItem->product?->isTracked()) {
                        $this->restock($order, $productId, $qty, $cashier, $reason);
                    }

                    $refundedItems[] = [
                        'product_id' => $productId,
                        'name' => $orderItem->name,
                        'quantity' => $qty,
                        'amount' => $lineRefund,
                    ];
                }
            }

            $refundAmount = min($refundAmount, $order->total_amount);

            $meta = $order->metadata ?? [];
            $refunds = (array) ($meta['refunds'] ?? []);
            $refundRecord = [
                'id' => uniqid('ref_'),
                'amount' => $refundAmount,
                'method' => $refundMethod,
                'reason' => $reason,
                'items' => $refundedItems,
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'refunded_at' => now()->toIso8601String(),
            ];
            $refunds[] = $refundRecord;
            $meta['refunds'] = $refunds;

            if ($refundMethod === 'cash') {
                // The owner refunds (BR-21); without a shift of their own the cash leaves the order's still-open register.
                $activeShift = CashShift::where('organization_id', $order->organization_id)
                    ->where('cashier_id', $cashier->id)
                    ->where('status', 'open')
                    ->first() ?? CashShift::where('organization_id', $order->organization_id)->where('status', 'open')->find($order->cash_shift_id);
                if ($activeShift) {
                    $movements = (array) ($activeShift->cash_movements ?? []);
                    $movements[] = [
                        'id' => uniqid('cm_'),
                        'type' => 'refund',
                        'amount' => $refundAmount,
                        'reason' => "Refund for Order {$order->payment_reference}: {$reason}",
                        'cashier_id' => $cashier->id,
                        'order_id' => $order->id,
                        'created_at' => now()->toIso8601String(),
                    ];
                    $activeShift->update(['cash_movements' => $movements]);
                }
            }

            $order->update([
                'status' => $refundAmount >= $order->total_amount ? 'refunded' : 'completed',
                'metadata' => $meta,
            ]);

            $this->financials->postRefund(
                $order->organization_id,
                $order->payment_reference ?? "POS-{$order->id}",
                $refundAmount,
                $refundRecord['id'],
                ['order_id' => $order->id, 'reason' => $reason]
            );

            return $order->fresh(['items', 'payments']);
        });
    }

    /** A return goes back to the venue of its sale (D3); an order without a venue cannot take stock back. */
    private function restock(PosOrder $order, int $productId, int $quantity, User $cashier, string $reason): void
    {
        abort_if($order->venue_id === null, 422, 'Đơn chưa gắn cơ sở nên không nhập lại kho được. Hãy trả tiền mà không nhập kho (restock = false).');
        $stock = $this->stock->lock($order->organization_id, (int) $order->venue_id, $productId);
        $this->stock->apply($stock, $quantity, 'return', $cashier->id, PosOrder::class, $order->id, ['reason' => $reason]);
    }
}
