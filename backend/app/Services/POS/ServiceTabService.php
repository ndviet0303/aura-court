<?php

namespace App\Services\POS;

use App\Models\CashShift;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\ServiceTab;
use App\Models\ServiceTabItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ServiceTabService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly WalletPaymentService $walletPayments,
        private readonly LegacyBookingBackfillService $backfill,
    ) {}

    public function getReservedQuantity(int $productId): int
    {
        return (int) ServiceTabItem::query()
            ->where('product_id', $productId)
            ->where('status', ServiceTabItem::STATUS_RESERVED)
            ->sum('quantity');
    }

    public function getAvailableStock(PosProduct $product): int
    {
        $reserved = $this->getReservedQuantity($product->id);

        return max(0, (int) $product->stock_quantity - $reserved);
    }

    public function resolveReservation(int|string $reservationIdOrCode, int $organizationId): Reservation
    {
        $clean = trim((string) $reservationIdOrCode);
        $clean = preg_replace('/^AURA_(?:V2_RESERVATION|BOOKING)_/i', '', $clean);

        $reservation = Reservation::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($clean) {
                $q->where('code', $clean);
                if (is_numeric($clean)) {
                    $q->orWhere('id', (int) $clean);
                }
            })
            ->first();

        if ($reservation) {
            return $reservation;
        }

        $canonicalId = $this->backfill->resolveCanonicalReservationId((int) $clean, $organizationId);

        return Reservation::query()
            ->where('organization_id', $organizationId)
            ->whereKey($canonicalId)
            ->firstOrFail();
    }

    public function getOrCreateTab(int $organizationId, int|string $reservationIdOrCode, User $staff): ServiceTab
    {
        $reservation = $this->resolveReservation($reservationIdOrCode, $organizationId);

        return ServiceTab::firstOrCreate([
            'organization_id' => $organizationId,
            'reservation_id' => $reservation->id,
            'status' => ServiceTab::STATUS_UNPAID,
        ], [
            'opened_by' => $staff->id,
            'total_amount' => 0,
        ]);
    }

    public function getTab(int $organizationId, int|string $reservationIdOrCode): ?ServiceTab
    {
        $reservation = $this->resolveReservation($reservationIdOrCode, $organizationId);

        return ServiceTab::query()
            ->where('organization_id', $organizationId)
            ->where('reservation_id', $reservation->id)
            ->where('status', ServiceTab::STATUS_UNPAID)
            ->with(['items.product', 'reservation.user', 'reservation.court.venue', 'openedBy:id,name'])
            ->first();
    }

    public function listTabs(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = ServiceTab::query()
            ->where('organization_id', $organizationId)
            ->with(['items.product', 'reservation.user', 'reservation.court.venue', 'openedBy:id,name', 'settledBy:id,name'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['reservation_id'])) {
            $query->where('reservation_id', (int) $filters['reservation_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function addItem(User $staff, int $organizationId, int|string $reservationIdOrCode, int $productId, int $quantity): ServiceTab
    {
        abort_unless($quantity > 0, 422, 'Quantity must be greater than zero.');

        return DB::transaction(function () use ($staff, $organizationId, $reservationIdOrCode, $productId, $quantity): ServiceTab {
            $tab = $this->getOrCreateTab($organizationId, $reservationIdOrCode, $staff);
            abort_unless($tab->status === ServiceTab::STATUS_UNPAID, 422, 'Cannot add items to a settled or cancelled tab.');

            $product = PosProduct::query()
                ->where('organization_id', $organizationId)
                ->whereKey($productId)
                ->lockForUpdate()
                ->firstOrFail();

            $availableStock = $this->getAvailableStock($product);
            abort_unless($availableStock >= $quantity, 409, "Insufficient stock for {$product->name}. (Available: {$availableStock}, requested: {$quantity})");

            $item = $tab->items()
                ->where('product_id', $productId)
                ->where('status', ServiceTabItem::STATUS_RESERVED)
                ->first();

            if ($item) {
                $item->quantity += $quantity;
                $item->line_total = $item->quantity * $item->unit_price;
                $item->save();
            } else {
                $tab->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'line_total' => $quantity * $product->price,
                    'status' => ServiceTabItem::STATUS_RESERVED,
                ]);
            }

            // Recalculate tab total
            $tab->total_amount = (int) $tab->items()->where('status', ServiceTabItem::STATUS_RESERVED)->sum('line_total');
            $tab->save();

            return $tab->fresh(['items.product', 'reservation.user', 'reservation.court.venue', 'openedBy:id,name']);
        });
    }

    public function addItems(User $staff, int $organizationId, int|string $reservationIdOrCode, array $items): ServiceTab
    {
        return DB::transaction(function () use ($staff, $organizationId, $reservationIdOrCode, $items): ServiceTab {
            $tab = $this->getOrCreateTab($organizationId, $reservationIdOrCode, $staff);
            abort_unless($tab->status === ServiceTab::STATUS_UNPAID, 422, 'Cannot add items to a settled or cancelled tab.');

            foreach ($items as $row) {
                $productId = (int) $row['product_id'];
                $quantity = (int) ($row['quantity'] ?? 1);
                if ($quantity <= 0) {
                    continue;
                }

                $product = PosProduct::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($productId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $availableStock = $this->getAvailableStock($product);
                abort_unless($availableStock >= $quantity, 409, "Insufficient stock for {$product->name}. (Available: {$availableStock}, requested: {$quantity})");

                $item = $tab->items()
                    ->where('product_id', $productId)
                    ->where('status', ServiceTabItem::STATUS_RESERVED)
                    ->first();

                if ($item) {
                    $item->quantity += $quantity;
                    $item->line_total = $item->quantity * $item->unit_price;
                    $item->save();
                } else {
                    $tab->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'line_total' => $quantity * $product->price,
                        'status' => ServiceTabItem::STATUS_RESERVED,
                    ]);
                }
            }

            $tab->total_amount = (int) $tab->items()->where('status', ServiceTabItem::STATUS_RESERVED)->sum('line_total');
            $tab->save();

            return $tab->fresh(['items.product', 'reservation.user', 'reservation.court.venue', 'openedBy:id,name']);
        });
    }

    public function removeItem(User $staff, int $organizationId, int|string $reservationIdOrCode, int $itemId): ServiceTab
    {
        return DB::transaction(function () use ($staff, $organizationId, $reservationIdOrCode, $itemId): ServiceTab {
            $tab = $this->getTab($organizationId, $reservationIdOrCode);
            abort_unless($tab !== null, 404, 'Active service tab not found.');

            $item = $tab->items()->whereKey($itemId)->firstOrFail();
            $item->update(['status' => ServiceTabItem::STATUS_RELEASED]);

            $tab->total_amount = (int) $tab->items()->where('status', ServiceTabItem::STATUS_RESERVED)->sum('line_total');
            $tab->save();

            return $tab->fresh(['items.product', 'reservation.user', 'reservation.court.venue', 'openedBy:id,name']);
        });
    }

    public function cancelTab(User $staff, int $organizationId, int $tabId, ?string $reason = null): ServiceTab
    {
        return DB::transaction(function () use ($staff, $organizationId, $tabId, $reason): ServiceTab {
            $tab = ServiceTab::query()
                ->where('organization_id', $organizationId)
                ->whereKey($tabId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($tab->status === ServiceTab::STATUS_UNPAID, 422, 'Only unpaid tabs can be cancelled.');

            // Release all stock reservations
            $tab->items()->where('status', ServiceTabItem::STATUS_RESERVED)->update(['status' => ServiceTabItem::STATUS_RELEASED]);

            $tab->update([
                'status' => ServiceTab::STATUS_CANCELLED,
                'notes' => $reason ? trim(($tab->notes ? $tab->notes . "\n" : '') . "Cancelled: {$reason}") : $tab->notes,
            ]);

            return $tab->fresh(['items.product', 'reservation']);
        });
    }

    public function settleTab(User $cashier, int $organizationId, int $tabId, array $paymentData): array
    {
        // 1. Enforce active open shift for cashier
        $activeShift = CashShift::query()
            ->where('organization_id', $organizationId)
            ->where('cashier_id', $cashier->id)
            ->where('status', 'open')
            ->first();
        abort_unless($activeShift !== null, 409, 'Cashier does not have an active open shift.');

        return DB::transaction(function () use ($cashier, $organizationId, $tabId, $paymentData): array {
            $tab = ServiceTab::query()
                ->where('organization_id', $organizationId)
                ->whereKey($tabId)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent retry: if already settled, return existing order and receipt
            if ($tab->status === ServiceTab::STATUS_SETTLED && $tab->pos_order_id) {
                $existingOrder = PosOrder::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($tab->pos_order_id)
                    ->with(['items', 'payments', 'customer', 'cashier'])
                    ->firstOrFail();

                return [
                    'tab' => $tab,
                    'order' => $existingOrder,
                    'receipt' => $existingOrder->receipt,
                    'duplicate' => true,
                ];
            }

            abort_unless($tab->status === ServiceTab::STATUS_UNPAID, 422, 'Cannot settle a cancelled or already processed tab.');

            $reservedItems = $tab->items()
                ->where('status', ServiceTabItem::STATUS_RESERVED)
                ->with('product')
                ->get();

            $totalAmount = (int) $tab->total_amount;
            $method = (string) ($paymentData['payment_method'] ?? 'cash');
            abort_unless(in_array($method, ['cash', 'wallet', 'qr'], true), 422, 'Invalid payment method.');

            $reservation = $tab->reservation()->with('user')->first();
            $customer = ! empty($paymentData['customer_id'])
                ? User::find((int) $paymentData['customer_id'])
                : $reservation?->user;

            $cashTendered = null;
            $changeDue = null;

            if ($method === 'cash') {
                $cashTendered = (int) ($paymentData['cash_tendered'] ?? $totalAmount);
                abort_unless($cashTendered >= $totalAmount, 422, 'Tiền mặt khách đưa chưa đủ để thanh toán tab dịch vụ.');
                $changeDue = max(0, $cashTendered - $totalAmount);
            } elseif ($method === 'wallet') {
                abort_unless($customer !== null, 422, 'Thanh toán ví yêu cầu xác định khách hàng.');
                $wallet = $this->walletPayments->getOrCreateWallet($customer->id, $organizationId);
                abort_unless((int) $wallet->balance >= $totalAmount, 422, 'Số dư ví của khách hàng không đủ để thanh toán tab dịch vụ.');
                $this->walletPayments->debit($wallet, $totalAmount, 'pos_tab_settlement', $tab->id, "Thanh toán tab dịch vụ #{$tab->id}");
                $changeDue = 0;
            } else {
                $changeDue = 0;
            }
            $orderCode = 'TAB-POS-' . strtoupper(uniqid());

            // Create PosOrder
            $order = PosOrder::create([
                'organization_id' => $organizationId,
                'customer_id' => $customer?->id,
                'cashier_id' => $cashier->id,
                'reservation_id' => $tab->reservation_id,
                'status' => 'completed',
                'payment_method' => $method,
                'subtotal' => $totalAmount,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'rounding_amount' => 0,
                'total_amount' => $totalAmount,
                'payment_reference' => $orderCode,
                'cash_tendered' => $cashTendered,
                'change_due' => $changeDue ?? 0,
                'completed_at' => now(),
                'metadata' => [
                    'notes' => "Settlement of Service Tab #{$tab->id}",
                ],
            ]);

            // Convert stock reservations into sale stock movements exactly once
            $orderLines = [];
            foreach ($reservedItems as $item) {
                $product = PosProduct::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();

                // Atomically decrement actual product stock
                $product->decrement('stock_quantity', $item->quantity);

                // Create sale stock movement
                StockMovement::create([
                    'organization_id' => $organizationId,
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'quantity_delta' => -$item->quantity,
                    'unit_cost' => (int) $product->cost,
                    'reference_type' => 'service_tab',
                    'reference_id' => $tab->id,
                    'created_by' => $cashier->id,
                    'metadata' => [
                        'balance_after' => $product->fresh()->stock_quantity,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit' => $product->unit,
                        'tab_id' => $tab->id,
                        'reservation_id' => $tab->reservation_id,
                    ],
                ]);

                // Update item status
                $item->update(['status' => ServiceTabItem::STATUS_SETTLED]);

                $order->items()->create([
                    'organization_id' => $organizationId,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]);

                $orderLines[] = [
                    'name' => $product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ];
            }

            // Record payment line
            $order->payments()->create([
                'organization_id' => $organizationId,
                'method' => $method,
                'amount' => $totalAmount,
                'status' => 'completed',
                'metadata' => [
                    'cash_tendered' => $cashTendered,
                    'change_due' => $changeDue,
                ],
            ]);
            // Save server receipt
            $receipt = [
                'order_code' => $orderCode,
                'created_at' => now()->toIso8601String(),
                'cashier_name' => $cashier->name,
                'customer_name' => $customer?->name,
                'court_info' => "Tab Sân #{$reservation?->court_id}",
                'items' => $orderLines,
                'subtotal' => $totalAmount,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => $totalAmount,
                'payment_method' => $method,
                'cash_tendered' => $cashTendered,
                'change_due' => $changeDue,
            ];
            $order->update(['metadata' => array_merge($order->metadata ?? [], ['receipt' => $receipt])]);

            // Post balanced financial journals
            if ($totalAmount > 0) {
                $this->financials->postPosSale(
                    organizationId: $organizationId,
                    orderId: $order->id,
                    amount: $totalAmount,
                    paymentMethod: $method,
                    reference: $orderCode,
                );
            }

            // Update tab status
            $tab->update([
                'status' => ServiceTab::STATUS_SETTLED,
                'settled_at' => now(),
                'settled_by' => $cashier->id,
                'pos_order_id' => $order->id,
            ]);

            return [
                'tab' => $tab->fresh(),
                'order' => $order->load(['items', 'payments']),
                'receipt' => $receipt,
                'duplicate' => false,
            ];
        });
    }
}
