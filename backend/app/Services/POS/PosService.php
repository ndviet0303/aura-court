<?php

namespace App\Services\POS;

use App\Services\Finance\FinancialPostingService;
use App\Services\Reservation\LegacyBookingBackfillService;

use App\Models\CashShift;
use App\Models\Coupon;
use App\Models\LoyaltyRule;
use App\Models\MembershipEntitlement;
use App\Models\PointLedger;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\ShiftReconciliation;
use App\Models\StockMovement;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly LegacyBookingBackfillService $backfill,
    ) {}

    public function createProduct(int $organizationId, array $data, ?User $actor = null): PosProduct
    {
        $metadata = $data['metadata'] ?? [];
        if (isset($data['category'])) $metadata['category'] = $data['category'];
        if (isset($data['unit'])) $metadata['unit'] = $data['unit'];
        if (isset($data['min_stock'])) $metadata['min_stock'] = (int) $data['min_stock'];
        if (isset($data['supplier'])) $metadata['supplier'] = $data['supplier'];
        if (isset($data['tax_rate'])) $metadata['tax_rate'] = (int) $data['tax_rate'];

        $initialStock = (int) ($data['stock_quantity'] ?? 0);

        return DB::transaction(function () use ($organizationId, $data, $metadata, $initialStock, $actor): PosProduct {
            $product = PosProduct::create([
                'organization_id' => $organizationId,
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => (int) $data['price'],
                'cost' => (int) ($data['cost'] ?? 0),
                'stock_quantity' => $initialStock,
                'is_active' => true,
                'metadata' => $metadata,
            ]);

            if ($initialStock > 0) {
                StockMovement::create([
                    'organization_id' => $organizationId,
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity_delta' => $initialStock,
                    'unit_cost' => $product->cost,
                    'reference_type' => 'initial_stock',
                    'reference_id' => null,
                    'created_by' => $actor?->id,
                    'metadata' => [
                        'note' => 'Khởi tạo tồn kho ban đầu',
                        'balance_after' => $initialStock,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit' => $product->unit,
                    ],
                ]);
            }

            return $product;
        });
    }

    public function updateProduct(PosProduct $product, array $data): PosProduct
    {
        $metadata = $product->metadata ?? [];
        if (isset($data['category'])) $metadata['category'] = $data['category'];
        if (isset($data['unit'])) $metadata['unit'] = $data['unit'];
        if (isset($data['min_stock'])) $metadata['min_stock'] = (int) $data['min_stock'];
        if (isset($data['supplier'])) $metadata['supplier'] = $data['supplier'];
        if (isset($data['tax_rate'])) $metadata['tax_rate'] = (int) $data['tax_rate'];
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $metadata = array_merge($metadata, $data['metadata']);
        }

        $product->update([
            'sku' => $data['sku'] ?? $product->sku,
            'barcode' => array_key_exists('barcode', $data) ? $data['barcode'] : $product->barcode,
            'name' => $data['name'] ?? $product->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
            'price' => isset($data['price']) ? (int) $data['price'] : $product->price,
            'cost' => isset($data['cost']) ? (int) $data['cost'] : $product->cost,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $product->is_active,
            'metadata' => $metadata,
        ]);

        return $product->fresh();
    }

    public function deleteProduct(PosProduct $product): void
    {
        $product->update(['is_active' => false]);
    }

    public function adjustStock(PosProduct $product, int $delta, User $actor, string $type = 'adjustment', ?string $reference = null, ?string $note = null): PosProduct
    {
        return DB::transaction(function () use ($product, $delta, $actor, $type, $reference, $note): PosProduct {
            $product = PosProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($product->stock_quantity + $delta >= 0, 409, 'Insufficient stock.');
            $product->increment('stock_quantity', $delta);
            $fresh = $product->fresh();

            StockMovement::create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity_delta' => $delta,
                'unit_cost' => $product->cost,
                'reference_type' => 'pos',
                'reference_id' => null,
                'created_by' => $actor->id,
                'metadata' => [
                    'reference' => $reference,
                    'note' => $note ?? $reference,
                    'balance_after' => $fresh->stock_quantity,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit' => $product->unit,
                ],
            ]);

            return $fresh;
        });
    }
    public function calculateQuote(int $organizationId, array $data, ?User $cashier = null): array
    {
        $items = $data['items'] ?? [];
        abort_if($items === [], 422, 'POS order requires at least one item.');

        $reservationId = null;
        if (! empty($data['reservation_id'])) {
            $reservationId = $this->backfill->resolveCanonicalReservationId((int) $data['reservation_id'], $organizationId);
        }
        $subtotal = 0;
        $calculatedItems = [];
        $taxTotal = 0;
        $vatMap = [];

        foreach ($items as $item) {
            $product = PosProduct::query()
                ->where('organization_id', $organizationId)
                ->whereKey((int) $item['product_id'])
                ->firstOrFail();

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $rentalHours = ($product->is_rental || ! empty($item['rental_hours']) || ! empty($item['rental_duration_hours']))
                ? max(1, (int) ($item['rental_duration_hours'] ?? $item['rental_hours'] ?? 1))
                : 1;

            $unitPrice = (int) $product->price;
            $lineSubtotal = $unitPrice * $quantity * $rentalHours;
            $subtotal += $lineSubtotal;

            $ratePercent = isset($item['vat_rate'])
                ? (is_float($item['vat_rate']) && $item['vat_rate'] <= 1 ? (int) round($item['vat_rate'] * 100) : (int) $item['vat_rate'])
                : (int) $product->tax_rate;

            $calculatedItems[] = [
                'product' => $product,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity' => $quantity,
                'rental_duration_hours' => $rentalHours,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'tax_rate' => $ratePercent,
                'vat_rate' => $ratePercent / 100,
            ];
        }

        // Discounts
        $couponDiscount = 0;
        $couponCode = trim((string) ($data['coupon_code'] ?? ''));
        if ($couponCode !== '') {
            $coupon = Coupon::query()->where('code', strtoupper($couponCode))->first();
            if (! $coupon || ! $coupon->is_active) {
                throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không hợp lệ.']);
            }
            if ($coupon->starts_at?->isFuture() || $coupon->ends_at?->isPast()) {
                throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá chưa có hiệu lực hoặc đã hết hạn.']);
            }
            if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
                throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá đã hết lượt sử dụng.']);
            }
            if ($subtotal < $coupon->min_spend) {
                throw ValidationException::withMessages(['coupon_code' => 'Đơn tối thiểu để dùng mã là '.number_format($coupon->min_spend, 0, ',', '.').'₫.']);
            }

            $couponDiscount = $coupon->discount_type === 'percent'
                ? (int) floor($subtotal * min(100, $coupon->discount_value) / 100)
                : min($subtotal, $coupon->discount_value);
            if ($coupon->max_discount !== null) {
                $couponDiscount = min($couponDiscount, $coupon->max_discount);
            }
        }

        $membershipDiscount = 0;
        if (! empty($data['customer_id'])) {
            $membership = TenantMembership::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', (int) $data['customer_id'])
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->with(['plan', 'entitlements'])
                ->latest('ends_at')
                ->first();

            $entitlementDiscount = null;
            if ($membership?->entitlements) {
                $ent = $membership->entitlements->first(fn ($e) => in_array($e->benefit_code, ['discount_percent', 'court_discount_percent', 'pos_discount_percent']));
                if ($ent) {
                    $val = $ent->benefit_value;
                    $entitlementDiscount = is_numeric($val) ? (int) $val : ($val['percent'] ?? $val['discount_percent'] ?? null);
                }
            }

            $membershipPercent = $entitlementDiscount !== null
                ? (int) $entitlementDiscount
                : (int) ($membership?->plan?->benefits['pos_discount_percent'] ?? $membership?->plan?->benefits['discount_percent'] ?? $membership?->plan?->benefits['court_discount_percent'] ?? 0);
            if ($membershipPercent > 0) {
                // Membership discount SHALL be applied once
                $membershipDiscount = intdiv($subtotal * $membershipPercent, 100);
            }
        }

        $cannotCombine = ! empty($coupon) && (! ($coupon->can_combine_with_membership ?? true) || ($coupon->metadata['can_combine_with_membership'] ?? true) === false);
        if ($couponDiscount > 0 && $membershipDiscount > 0 && $cannotCombine) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không thể kết hợp cùng ưu đãi hội viên trên cùng một đơn hàng.']);
        }

        $discount = min($subtotal, $couponDiscount + $membershipDiscount);
        $submittedDiscount = (int) ($data['discount_amount'] ?? 0);
        if ($submittedDiscount > $discount && $submittedDiscount > 0) {
            throw ValidationException::withMessages(['discount_amount' => 'Giảm giá không hợp lệ hoặc vượt quá mức giảm giá được cấp trên hệ thống.']);
        }
        if ($submittedDiscount > 0 && $submittedDiscount <= $discount) {
            $discount = $submittedDiscount;
        }

        $taxableAmount = max(0, $subtotal - $discount);
        $discountRatio = $subtotal > 0 ? (1 - ($discount / $subtotal)) : 1.0;

        // Line VAT & Breakdown
        foreach ($calculatedItems as &$cItem) {
            $lineTaxable = round($cItem['line_subtotal'] * $discountRatio);
            $rate = $cItem['tax_rate'];
            $lineTax = (int) round($lineTaxable * ($rate / 100));
            $cItem['tax_amount'] = $lineTax;
            $cItem['line_total'] = $cItem['line_subtotal'] + $lineTax;
            $taxTotal += $lineTax;
            if ($rate > 0) {
                $vatMap[$rate] = ($vatMap[$rate] ?? 0) + $lineTax;
            }
        }
        unset($cItem);

        $vatBreakdown = [];
        foreach ($vatMap as $rate => $amount) {
            $vatBreakdown[] = [
                'rate' => $rate / 100,
                'percent' => (int) $rate,
                'amount' => (int) $amount,
            ];
        }
        usort($vatBreakdown, fn ($a, $b) => $a['percent'] <=> $b['percent']);

        $roundingAmount = (int) ($data['rounding_amount'] ?? 0);
        $grandTotal = max(0, (int) round($taxableAmount + $taxTotal + $roundingAmount));

        $method = (string) ($data['payment_method'] ?? 'cash');
        $cashTendered = null;
        $changeDue = 0;
        if ($method === 'cash') {
            if (isset($data['cash_tendered']) && $data['cash_tendered'] !== null) {
                $cashTendered = (int) $data['cash_tendered'];
                abort_if($cashTendered < $grandTotal, 422, 'Tiền mặt khách đưa không đủ để thanh toán.');
                $changeDue = max(0, $cashTendered - $grandTotal);
            }
        }

        return [
            'items' => $calculatedItems,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxTotal,
            'vat_breakdown' => $vatBreakdown,
            'rounding_amount' => $roundingAmount,
            'grand_total' => $grandTotal,
            'payment_method' => $method,
            'cash_tendered' => $cashTendered,
            'change_due' => $changeDue,
            'reservation_id' => $reservationId,
        ];
    }

    public function isQrProviderAvailable(int $organizationId): bool
    {
        if (config('services.payments.qr_enabled') === false) {
            return false;
        }

        $hasSepay = ! empty(config('services.sepay.account'))
            || ! empty(config('services.sepay.api_key'))
            || ! empty(config('services.sepay.hmac_secret'));

        $hasPayOs = ! empty(config('services.payos.client_id'))
            && ! empty(config('services.payos.api_key'));

        $simulated = app()->environment('testing') && config('services.payments.simulate_qr_provider', true);

        return $hasSepay || $hasPayOs || $simulated;
    }

    public function completeOrder(User $cashier, int $organizationId, array $data): PosOrder
    {
        abort_if((bool) ($data['offline'] ?? false), 409, 'Offline POS drafts cannot finalize payment or inventory.');
        $method = (string) ($data['payment_method'] ?? '');
        abort_unless(in_array($method, ['cash', 'qr', 'wallet'], true), 422, 'Unsupported POS payment method.');

        if ($method === 'qr') {
            abort_unless($this->isQrProviderAvailable($organizationId), 422, 'QR payment provider verification is unavailable.');
        }

        if (! empty($data['require_shift'])) {
            $activeShift = CashShift::where('organization_id', $organizationId)
                ->where('cashier_id', $cashier->id)
                ->where('status', 'open')
                ->first();
            abort_unless($activeShift !== null, 409, 'Cashier does not have an active open shift.');
        }

        $reservationId = null;
        if (! empty($data['reservation_id'])) {
            $reservationId = $this->backfill->resolveCanonicalReservationId((int) $data['reservation_id'], $organizationId);
        }

        return DB::transaction(function () use ($cashier, $organizationId, $data, $method, $reservationId): PosOrder {
            $quote = $this->calculateQuote($organizationId, $data, $cashier);

            $subtotal = $quote['subtotal'];
            $discount = $quote['discount_amount'];
            $taxAmount = $quote['tax_amount'];
            $roundingAmount = $quote['rounding_amount'];
            $grandTotal = $quote['grand_total'];
            $cashTendered = $quote['cash_tendered'];
            $changeDue = $quote['change_due'];
            $vatBreakdown = $quote['vat_breakdown'];

            // Stock lock and check
            foreach ($quote['items'] as $item) {
                $product = PosProduct::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $reserved = (int) \App\Models\ServiceTabItem::query()
                    ->where('product_id', $product->id)
                    ->where('status', \App\Models\ServiceTabItem::STATUS_RESERVED)
                    ->sum('quantity');
                $available = max(0, (int) $product->stock_quantity - $reserved);
                $tabMsg = $reserved > 0 ? " ({$reserved} reserved by active tabs)." : ".";
                abort_unless($available >= $item['quantity'], 409, "Insufficient stock for {$product->sku}{$tabMsg}");
            }

            $couponCode = trim((string) ($data['coupon_code'] ?? ''));
            if ($couponCode !== '') {
                $coupon = Coupon::query()->where('code', strtoupper($couponCode))->first();
                $coupon?->increment('usage_count');
            }

            $orderCode = $data['payment_reference'] ?? "POS-{$cashier->id}-" . strtoupper(uniqid());
            $customer = ! empty($data['customer_id']) ? User::find((int) $data['customer_id']) : null;
            $reservation = $reservationId ? \App\Models\Reservation::find($reservationId) : null;

            $isPendingQr = ($method === 'qr');
            $orderStatus = $isPendingQr ? 'pending_payment' : 'completed';
            $paymentStatus = $isPendingQr ? 'pending' : 'paid';

            $receipt = [
                'order_code' => $orderCode,
                'created_at' => now()->toIso8601String(),
                'cashier_name' => $cashier->name,
                'customer_name' => $customer?->name,
                'court_info' => $reservation ? "Sân #{$reservation->court_id}" : null,
                'items' => array_map(fn ($i) => [
                    'product_id' => $i['product_id'],
                    'name' => $i['name'],
                    'quantity' => $i['quantity'],
                    'rental_duration_hours' => $i['rental_duration_hours'],
                    'price' => $i['unit_price'],
                    'vat_rate' => $i['vat_rate'],
                    'tax_amount' => $i['tax_amount'],
                    'total' => $i['line_total'],
                ], $quote['items']),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'taxable_amount' => $quote['taxable_amount'],
                'tax_amount' => $taxAmount,
                'vat_breakdown' => $vatBreakdown,
                'rounding_amount' => $roundingAmount,
                'grand_total' => $grandTotal,
                'payment_method' => $method,
                'cash_tendered' => $cashTendered,
                'change_due' => $changeDue,
                'status' => $orderStatus,
                'is_paid' => ! $isPendingQr,
            ];

            if ($isPendingQr) {
                $receipt['qr_data'] = [
                    'bank' => config('services.sepay.bank', 'MBBank'),
                    'account' => config('services.sepay.account', '0901234567'),
                    'account_name' => config('services.sepay.account_name', 'AURA COURT SYSTEM'),
                    'amount' => $grandTotal,
                    'memo' => $orderCode,
                    'qr_url' => 'https://img.vietqr.io/image/' . config('services.sepay.bank', 'MBBank') . '-' . config('services.sepay.account', '0901234567') . '-compact2.png?amount=' . $grandTotal . '&addInfo=' . urlencode($orderCode) . '&accountName=' . urlencode(config('services.sepay.account_name', 'AURA COURT SYSTEM')),
                ];
            }

            $order = PosOrder::create([
                'organization_id' => $organizationId,
                'cart_id' => $data['cart_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => $cashier->id,
                'reservation_id' => $reservationId,
                'status' => $orderStatus,
                'payment_method' => $method,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $taxAmount,
                'total_amount' => $grandTotal,
                'rounding_amount' => $roundingAmount,
                'payment_reference' => $orderCode,
                'cash_tendered' => $cashTendered,
                'change_due' => $changeDue,
                'completed_at' => $isPendingQr ? null : now(),
                'metadata' => [
                    'vat_breakdown' => $vatBreakdown,
                    'receipt' => $receipt,
                ],
            ]);

            if ($method === 'wallet') {
                abort_unless(! empty($data['customer_id']), 422, 'Customer is required for wallet payment.');
                $this->spendWallet($organizationId, (int) $data['customer_id'], $grandTotal, "pos:{$order->id}");
            }

            foreach ($quote['items'] as $item) {
                $product = $item['product'];
                if (! $isPendingQr) {
                    $product->decrement('stock_quantity', $item['quantity']);
                }

                $order->items()->create([
                    'organization_id' => $organizationId,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => $item['quantity'],
                    'rental_duration_hours' => $item['rental_duration_hours'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'metadata' => [
                        'line_subtotal' => $item['line_subtotal'],
                        'vat_rate' => $item['vat_rate'],
                    ],
                ]);

                if (! $isPendingQr) {
                    StockMovement::create([
                        'organization_id' => $organizationId,
                        'product_id' => $product->id,
                        'type' => 'sale',
                        'quantity_delta' => -$item['quantity'],
                        'unit_cost' => $product->cost,
                        'reference_type' => PosOrder::class,
                        'reference_id' => $order->id,
                        'created_by' => $cashier->id,
                    ]);
                }
            }

            $order->payments()->create([
                'organization_id' => $organizationId,
                'method' => $method,
                'amount' => $grandTotal,
                'status' => $paymentStatus,
                'provider_reference' => $orderCode,
                'metadata' => [
                    'cash_tendered' => $cashTendered,
                    'change_due' => $changeDue,
                    'qr_data' => $isPendingQr ? ($receipt['qr_data'] ?? null) : null,
                ],
            ]);

            if (! $isPendingQr && $method !== 'wallet') {
                $this->financials->postPosSale($organizationId, $order->id, $grandTotal, $method, $order->payment_reference);
            }

            if (! $isPendingQr && LoyaltyRule::where('organization_id', $organizationId)->where('status', 'published')->exists() && $order->customer_id) {
                $this->awardPoints($organizationId, $order->customer_id, $grandTotal, PosOrder::class, $order->id);
            }

            return $order->fresh(['items', 'payments']);
        });
    }

    public function confirmQrPayment(PosOrder $order, array $verificationData = []): PosOrder
    {
        abort_unless($order->payment_method === 'qr', 422, 'Only QR orders can be confirmed via QR verification.');
        abort_unless($order->status === 'pending_payment', 409, 'Order is not awaiting payment.');

        return DB::transaction(function () use ($order, $verificationData): PosOrder {
            $order = PosOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'pending_payment', 409, 'Order is not awaiting payment.');

            $order->load(['items.product', 'payments']);

            // Verify and decrement stock atomically
            foreach ($order->items as $item) {
                $product = PosProduct::where('organization_id', $order->organization_id)
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($product->stock_quantity >= $item->quantity, 409, "Insufficient stock for {$product->sku}.");
                $product->decrement('stock_quantity', $item->quantity);

                StockMovement::create([
                    'organization_id' => $order->organization_id,
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'quantity_delta' => -$item['quantity'],
                    'unit_cost' => $product->cost,
                    'reference_type' => PosOrder::class,
                    'reference_id' => $order->id,
                    'created_by' => $order->cashier_id,
                ]);
            }

            $externalRef = $verificationData['external_transaction_id'] ?? $verificationData['provider_reference'] ?? $order->payment_reference;

            $order->payments()->where('method', 'qr')->update([
                'status' => 'paid',
                'provider_reference' => $externalRef,
            ]);

            $meta = $order->metadata ?? [];
            if (isset($meta['receipt'])) {
                $meta['receipt']['status'] = 'completed';
                $meta['receipt']['is_paid'] = true;
                $meta['receipt']['verified_at'] = now()->toIso8601String();
            }

            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => $meta,
            ]);

            $this->financials->postPosSale(
                $order->organization_id,
                $order->id,
                $order->total_amount,
                'qr',
                $order->payment_reference
            );

            if (LoyaltyRule::where('organization_id', $order->organization_id)->where('status', 'published')->exists() && $order->customer_id) {
                $this->awardPoints($order->organization_id, $order->customer_id, $order->total_amount, PosOrder::class, $order->id);
            }

            return $order->fresh(['items', 'payments']);
        });
    }

    public function cancelPendingQr(PosOrder $order, string $reason = 'cancelled'): PosOrder
    {
        abort_unless($order->status === 'pending_payment', 409, 'Only pending orders can be cancelled.');

        return DB::transaction(function () use ($order, $reason): PosOrder {
            $order = PosOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'pending_payment', 409, 'Only pending orders can be cancelled.');

            $order->payments()->where('status', 'pending')->update([
                'status' => 'cancelled',
            ]);

            $meta = $order->metadata ?? [];
            if (isset($meta['receipt'])) {
                $meta['receipt']['status'] = 'cancelled';
                $meta['receipt']['is_paid'] = false;
                $meta['receipt']['cancelled_at'] = now()->toIso8601String();
                $meta['receipt']['cancel_reason'] = $reason;
            }

            $order->update([
                'status' => 'cancelled',
                'metadata' => array_merge($meta, ['cancelled_at' => now()->toIso8601String(), 'cancel_reason' => $reason]),
            ]);

            return $order->fresh(['items', 'payments']);
        });
    }

    public function openShift(User $cashier, int $organizationId, int $openingCash): CashShift
    {
        abort_unless($openingCash >= 0, 422);
        abort_if(CashShift::where('organization_id', $organizationId)->where('cashier_id', $cashier->id)->where('status', 'open')->exists(), 409, 'Cashier already has an open shift.');
        return CashShift::create(['organization_id' => $organizationId, 'cashier_id' => $cashier->id, 'opening_cash' => $openingCash, 'opened_at' => now(), 'status' => 'open']);
    }

    public function closeShift(CashShift $shift, User $cashier, int $declaredCash, ?string $note = null): CashShift
    {
        return DB::transaction(function () use ($shift, $cashier, $declaredCash, $note): CashShift {
            $shift = CashShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            abort_unless($shift->cashier_id === $cashier->id && $shift->status === 'open', 409);
            $cashSales = PosOrder::where('organization_id', $shift->organization_id)->where('cashier_id', $cashier->id)->where('payment_method', 'cash')->where('status', 'completed')->whereBetween('completed_at', [$shift->opened_at, now()])->sum('total_amount');
            $expected = $shift->opening_cash + $cashSales;
            $variance = $declaredCash - $expected;
            $shift->update(['expected_cash' => $expected, 'declared_cash' => $declaredCash, 'variance' => $variance, 'status' => 'closed', 'closed_at' => now()]);
            ShiftReconciliation::create(['organization_id' => $shift->organization_id, 'cash_shift_id' => $shift->id, 'variance' => $variance, 'status' => $variance === 0 ? 'matched' : 'exception', 'note' => $note]);
            return $shift->fresh('reconciliation');
        });
    }

    public function publishPlan(int $organizationId, array $data): TenantMembershipPlan
    {
        $version = (int) TenantMembershipPlan::where('organization_id', $organizationId)->where('name', $data['name'])->max('version') + 1;
        return TenantMembershipPlan::create(['organization_id' => $organizationId, 'name' => $data['name'], 'version' => $version, 'price' => $data['price'], 'benefits' => $data['benefits'], 'status' => 'published', 'published_at' => now()]);
    }

    public function assignMembership(int $organizationId, int $userId, TenantMembershipPlan $plan, int $months = 1): TenantMembership
    {
        return DB::transaction(function () use ($organizationId, $userId, $plan, $months): TenantMembership {
            $membership = TenantMembership::create(['organization_id' => $organizationId, 'user_id' => $userId, 'plan_id' => $plan->id, 'plan_version' => $plan->version, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonths($months)]);
            foreach ($plan->benefits as $code => $value) MembershipEntitlement::create(['organization_id' => $organizationId, 'membership_id' => $membership->id, 'benefit_code' => $code, 'benefit_value' => ['value' => $value, 'plan_version' => $plan->version], 'starts_at' => $membership->starts_at, 'ends_at' => $membership->ends_at]);
            return $membership->fresh('entitlements');
        });
    }

    public function awardPoints(int $organizationId, int $userId, int $amountVnd, string $sourceType, int $sourceId): PointLedger
    {
        $rule = LoyaltyRule::where('organization_id', $organizationId)->where('status', 'published')->latest('version')->firstOrFail();
        $points = intdiv($amountVnd * $rule->earn_points_per_100_vnd, 100);
        return $this->writePoints($organizationId, $userId, $rule->id, $points, $sourceType, $sourceId);
    }

    public function redeemPoints(int $organizationId, int $userId, int $points, string $sourceType, int $sourceId): PointLedger
    {
        abort_unless($points > 0, 422);
        $current = (int) PointLedger::where('organization_id', $organizationId)->where('user_id', $userId)->sum('delta');
        abort_unless($current >= $points, 409, 'Insufficient loyalty points.');
        $rule = LoyaltyRule::where('organization_id', $organizationId)->where('status', 'published')->latest('version')->firstOrFail();
        return $this->writePoints($organizationId, $userId, $rule->id, -$points, $sourceType, $sourceId);
    }

    public function benefits(int $organizationId, int $userId)
    {
        return MembershipEntitlement::query()->where('organization_id', $organizationId)->whereHas('membership', fn ($query) => $query->where('user_id', $userId)->where('status', 'active')->where('ends_at', '>', now()))->where('ends_at', '>', now())->get();
    }

    public function spendWallet(int $organizationId, int $userId, int $amount, string $reference): WalletTransaction
    {
        abort_unless($amount > 0, 422);
        return DB::transaction(function () use ($organizationId, $userId, $amount, $reference): WalletTransaction {
            $wallet = WalletAccount::firstOrCreate(['organization_id' => $organizationId, 'user_id' => $userId, 'currency' => 'VND'], ['balance' => 0, 'status' => 'active']);
            $wallet = WalletAccount::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            abort_unless($wallet->balance >= $amount, 409, 'Insufficient tenant wallet credit.');
            $wallet->decrement('balance', $amount);
            $transaction = WalletTransaction::create(['organization_id' => $organizationId, 'wallet_account_id' => $wallet->id, 'type' => 'debit', 'amount' => $amount, 'balance_after' => $wallet->balance, 'source_type' => 'wallet_spend', 'source_id' => abs(crc32($reference)), 'reference' => $reference]);
            $this->financials->postWalletSpend($organizationId, $wallet->id, $amount, $reference);
            return $transaction;
        });
    }

    public function creditWallet(int $organizationId, int $userId, int $amount, string $sourceType, int $sourceId, string $reference): WalletTransaction
    {
        abort_unless($amount > 0, 422);
        return DB::transaction(function () use ($organizationId, $userId, $amount, $sourceType, $sourceId, $reference): WalletTransaction {
            $wallet = WalletAccount::firstOrCreate(['organization_id' => $organizationId, 'user_id' => $userId, 'currency' => 'VND'], ['balance' => 0, 'status' => 'active']);
            $wallet = WalletAccount::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance', $amount);
            $transaction = WalletTransaction::create(['organization_id' => $organizationId, 'wallet_account_id' => $wallet->id, 'type' => 'credit', 'amount' => $amount, 'balance_after' => $wallet->balance, 'source_type' => $sourceType, 'source_id' => $sourceId, 'reference' => $reference]);
            $this->financials->postWalletCredit($organizationId, $wallet->id, $amount, $reference);
            return $transaction;
        });
    }

    private function writePoints(int $organizationId, int $userId, int $ruleId, int $delta, string $sourceType, int $sourceId): PointLedger
    {
        return DB::transaction(function () use ($organizationId, $userId, $ruleId, $delta, $sourceType, $sourceId): PointLedger {
            User::query()->whereKey($userId)->lockForUpdate()->first();
            $balance = (int) PointLedger::where('organization_id', $organizationId)->where('user_id', $userId)->sum('delta');
            abort_unless($balance + $delta >= 0, 409, 'Loyalty points cannot become negative.');
            return PointLedger::create(['organization_id' => $organizationId, 'user_id' => $userId, 'loyalty_rule_id' => $ruleId, 'delta' => $delta, 'balance_after' => $balance + $delta, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        });
    }
}
