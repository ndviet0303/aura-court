<?php

namespace App\Services\POS;

use App\Models\CashShift;
use App\Models\MembershipEntitlement;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\ServiceTabItem;
use App\Models\ShiftReconciliation;
use App\Models\StockMovement;
use App\Models\TenantCustomer;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\Finance\FinancialPostingService;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\Payment\WalletAvailabilityService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly LegacyBookingBackfillService $backfill,
        private readonly LoyaltyPointService $loyaltyPoints,
        private readonly WalletAvailabilityService $walletAvailability,
        private readonly PosQrPaymentService $posQr,
    ) {}

    public function createProduct(int $organizationId, array $data, ?User $actor = null): PosProduct
    {
        $metadata = $data['metadata'] ?? [];
        if (isset($data['category'])) {
            $metadata['category'] = $data['category'];
        }
        if (isset($data['unit'])) {
            $metadata['unit'] = $data['unit'];
        }
        if (isset($data['min_stock'])) {
            $metadata['min_stock'] = (int) $data['min_stock'];
        }
        if (isset($data['supplier'])) {
            $metadata['supplier'] = $data['supplier'];
        }
        if (isset($data['tax_rate'])) {
            $metadata['tax_rate'] = (int) $data['tax_rate'];
        }

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
        if (isset($data['category'])) {
            $metadata['category'] = $data['category'];
        }
        if (isset($data['unit'])) {
            $metadata['unit'] = $data['unit'];
        }
        if (isset($data['min_stock'])) {
            $metadata['min_stock'] = (int) $data['min_stock'];
        }
        if (isset($data['supplier'])) {
            $metadata['supplier'] = $data['supplier'];
        }
        if (isset($data['tax_rate'])) {
            $metadata['tax_rate'] = (int) $data['tax_rate'];
        }
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

    public function calculateQuote(int $organizationId, array $data): array
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

        // Membership is the only POS discount; vouchers are refused at the request (design D12).
        $membershipDiscount = 0;
        $membershipSnapshot = null;
        if (! empty($data['customer_id'])) {
            $now = now();
            $membership = TenantMembership::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', (int) $data['customer_id'])
                ->where('status', 'active')
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)
                ->whereHas('plan', fn ($query) => $query->where('organization_id', $organizationId))
                ->with(['entitlements' => fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now)])
                ->latest('ends_at')
                ->first();

            $entitlements = $membership?->entitlements ?? collect();
            $entitlement = $entitlements->firstWhere('benefit_code', 'pos_discount_percent')
                ?? $entitlements->firstWhere('benefit_code', 'discount_percent')
                ?? $entitlements->firstWhere('benefit_code', 'court_discount_percent');
            $value = $entitlement?->benefit_value;
            $membershipPercent = $entitlement
                ? (is_numeric($value) ? (int) $value : (int) ($value['percent'] ?? $value['discount_percent'] ?? $value['value'] ?? 0))
                : 0;
            if ($membershipPercent > 0) {
                $membershipPercent = min(100, $membershipPercent);
                $membershipDiscount = intdiv($subtotal * $membershipPercent, 100);
                $membershipSnapshot = [
                    'membership_id' => (int) $membership->id,
                    'organization_id' => (int) $membership->organization_id,
                    'plan_id' => (int) $membership->plan_id,
                    'plan_version' => (int) $membership->plan_version,
                    'entitlement_id' => (int) $entitlement->id,
                    'benefit_code' => $entitlement->benefit_code,
                    'benefit_value' => $value,
                    'starts_at' => $entitlement->starts_at?->toIso8601String(),
                    'ends_at' => $entitlement->ends_at?->toIso8601String(),
                    'discount_amount' => $membershipDiscount,
                ];
            }
        }

        $discount = min($subtotal, $membershipDiscount);
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
            'membership_discount_amount' => $membershipDiscount,
            'membership_snapshot' => $membershipSnapshot,
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

    public function completeOrder(User $cashier, int $organizationId, array $data): PosOrder
    {
        abort_if((bool) ($data['offline'] ?? false), 409, 'Offline POS drafts cannot finalize payment or inventory.');
        $method = (string) ($data['payment_method'] ?? '');
        abort_unless(in_array($method, ['cash', 'qr', 'wallet', 'mixed'], true), 422, 'Unsupported POS payment method.');
        $this->walletAvailability->assertAvailable($organizationId, in_array('wallet', [$method, ...array_column((array) ($data['tenders'] ?? []), 'method')], true));

        $activeShift = CashShift::where('organization_id', $organizationId)
            ->where('cashier_id', $cashier->id)
            ->where('status', 'open')
            ->first();

        abort_if(! empty($data['require_shift']) && $activeShift === null, 409, 'Cashier does not have an active open shift.');

        $reservationId = empty($data['reservation_id']) ? null : $this->backfill->resolveCanonicalReservationId((int) $data['reservation_id'], $organizationId);

        $saleVenueId = $data['venue_id'] ?? $activeShift?->venue_id ?? ($reservationId ? Reservation::query()->find($reservationId)?->court()->value('venue_id') : null);
        $saleVenueId = $this->posQr->venueIdFor($cashier, $organizationId, $saleVenueId === null ? null : (int) $saleVenueId);
        $this->posQr->assertOperableVenue($cashier, $organizationId, $saleVenueId);

        // QR pays the venue owner's verified connection; without one the tender is refused before any mutation.
        $isPendingQr = $method === 'qr' || ($method === 'mixed' && in_array('qr', array_column((array) ($data['tenders'] ?? []), 'method'), true));
        $qrConnection = null;
        if ($isPendingQr) {
            $qrConnection = $this->posQr->connectionFor($organizationId, $saleVenueId);
            abort_unless($qrConnection !== null, 422, PosQrPaymentService::UNAVAILABLE);
        }

        return DB::transaction(function () use ($cashier, $organizationId, $data, $method, $reservationId, $activeShift, $isPendingQr, $qrConnection, $saleVenueId): PosOrder {
            $quote = $this->calculateQuote($organizationId, $data);

            $subtotal = $quote['subtotal'];
            $discount = $quote['discount_amount'];
            $taxAmount = $quote['tax_amount'];
            $roundingAmount = $quote['rounding_amount'];
            $grandTotal = $quote['grand_total'];
            $cashTendered = $quote['cash_tendered'];
            $changeDue = $quote['change_due'];
            $vatBreakdown = $quote['vat_breakdown'];

            // Mixed tender validation (Task 8.1)
            $tenders = [];
            if ($method === 'mixed') {
                $reservation = $reservationId ? Reservation::find($reservationId) : null;
                $venue = null;
                if (! empty($data['venue_id'])) {
                    $venue = Venue::find((int) $data['venue_id']);
                } elseif ($reservation && $reservation->court?->venue) {
                    $venue = $reservation->court->venue;
                } elseif ($activeShift && $activeShift->venue_id) {
                    $venue = $activeShift->venue;
                }
                $allowMixed = false;
                if ($venue) {
                    $allowMixed = (bool) data_get($venue->currentBookingPolicy?->settings, 'pos.allow_mixed_tender', false);
                }
                if (! $allowMixed && ! empty($data['allow_mixed_tender'])) {
                    $allowMixed = true;
                }
                abort_unless($allowMixed, 422, 'Mixed tender is not allowed by venue policy.');

                $tenders = (array) ($data['tenders'] ?? []);
                abort_if(empty($tenders), 422, 'Tender allocations are required for mixed payment.');

                $tenderSum = 0;
                foreach ($tenders as $tender) {
                    $tMethod = $tender['method'] ?? '';
                    $tAmount = (int) ($tender['amount'] ?? 0);
                    abort_unless(in_array($tMethod, ['cash', 'qr', 'wallet'], true), 422, "Unsupported tender method: {$tMethod}");
                    abort_unless($tAmount > 0, 422, 'Tender amount must be positive.');
                    $tenderSum += $tAmount;
                }
                abort_unless($tenderSum === $grandTotal, 422, "Total tender allocations ({$tenderSum}) must equal order total ({$grandTotal}).");
            }

            // Stock lock and check (Task 8.11 / D58)
            foreach ($quote['items'] as $item) {
                $product = PosProduct::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $product->isTracked()) {
                    continue;
                }

                $reservedTabs = (int) ServiceTabItem::query()
                    ->where('product_id', $product->id)
                    ->where('status', ServiceTabItem::STATUS_RESERVED)
                    ->sum('quantity');

                $reservedQr = (int) PosOrderItem::query()
                    ->where('product_id', $product->id)
                    ->whereHas('order', fn ($q) => $q->where('status', 'pending_payment'))
                    ->sum('quantity');

                $totalReserved = $reservedTabs + $reservedQr;
                $available = max(0, (int) $product->stock_quantity - $totalReserved);
                $tabMsg = $totalReserved > 0 ? " ({$totalReserved} reserved by pending orders/tabs)." : '.';
                abort_unless($available >= $item['quantity'], 409, "Insufficient stock for {$product->sku}{$tabMsg}");
            }

            // A QR order's reference is the server-generated transfer code; any client-sent reference is ignored.
            $orderCode = $isPendingQr ? 'AC'.Str::upper(Str::random(18)) : ($data['payment_reference'] ?? "POS-{$cashier->id}-".strtoupper(uniqid()));
            $customer = ! empty($data['customer_id']) ? User::find((int) $data['customer_id']) : null;
            $reservation = $reservationId ? Reservation::find($reservationId) : null;

            $orderStatus = $isPendingQr ? 'pending_payment' : 'completed';

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
                'membership_discount_amount' => $quote['membership_discount_amount'],
                'taxable_amount' => $quote['taxable_amount'],
                'tax_amount' => $taxAmount,
                'vat_breakdown' => $vatBreakdown,
                'rounding_amount' => $roundingAmount,
                'grand_total' => $grandTotal,
                'payment_method' => $method,
                'tenders' => $tenders,
                'cash_tendered' => $cashTendered,
                'change_due' => $changeDue,
                'status' => $orderStatus,
                'is_paid' => ! $isPendingQr,
            ];

            $expiresAt = now()->addMinutes(15);

            $order = PosOrder::create([
                'organization_id' => $organizationId,
                'cart_id' => $data['cart_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => $cashier->id,
                'cash_shift_id' => $activeShift?->id,
                'venue_id' => $qrConnection->venue_id ?? $saleVenueId,
                'register_id' => $data['register_id'] ?? $activeShift?->register_id ?? null,
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
                    'expires_at' => $isPendingQr ? $expiresAt->toIso8601String() : null,
                    'membership_snapshot' => $quote['membership_snapshot'],
                ],
            ]);

            if ($method === 'wallet') {
                abort_unless(! empty($data['customer_id']), 422, 'Customer is required for wallet payment.');
                $this->spendWallet($organizationId, (int) $data['customer_id'], $grandTotal, "pos:{$order->id}");
            }

            foreach ($quote['items'] as $item) {
                $product = $item['product'];
                if (! $isPendingQr && $product->isTracked()) {
                    $product->decrement('stock_quantity', $item['quantity']);

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
            }

            if ($method === 'mixed') {
                foreach ($tenders as $tender) {
                    $tMethod = $tender['method'];
                    $tAmount = (int) $tender['amount'];
                    $tStatus = ($tMethod === 'qr') ? 'pending' : 'paid';

                    $order->payments()->create([
                        'organization_id' => $organizationId,
                        'method' => $tMethod,
                        'amount' => $tAmount,
                        'status' => $tStatus,
                        'provider_reference' => $orderCode,
                        'metadata' => [
                            'cash_tendered' => $tMethod === 'cash' ? ($tender['cash_tendered'] ?? $tAmount) : null,
                            'change_due' => $tMethod === 'cash' ? ($tender['change_due'] ?? 0) : null,
                        ],
                    ]);

                    if ($tMethod === 'wallet') {
                        abort_unless(! empty($data['customer_id']), 422, 'Customer is required for wallet payment.');
                        $this->spendWallet($organizationId, (int) $data['customer_id'], $tAmount, "pos:{$order->id}:mixed");
                    }
                }
            } else {
                $paymentStatus = $isPendingQr ? 'pending' : 'paid';
                $order->payments()->create([
                    'organization_id' => $organizationId,
                    'method' => $method,
                    'amount' => $grandTotal,
                    'status' => $paymentStatus,
                    'provider_reference' => $orderCode,
                    'metadata' => [
                        'cash_tendered' => $cashTendered,
                        'change_due' => $changeDue,
                    ],
                ]);
            }

            if ($isPendingQr) {
                $qrAmount = $method === 'mixed' ? (int) collect($tenders)->firstWhere('method', 'qr')['amount'] : $grandTotal;
                $this->posQr->open($order, $qrConnection, $qrAmount, $expiresAt);
            } elseif ($method !== 'wallet') {
                $this->financials->postPosSale($organizationId, $order->id, $grandTotal, $method, $order->payment_reference);
            }

            if (! $isPendingQr && $order->customer_id && $this->loyaltyPoints->getActiveBaseEarnRule($organizationId) !== null) {
                $this->awardPoints($organizationId, $order->customer_id, $grandTotal, PosOrder::class, $order->id);
            }

            return $order->fresh(['items', 'payments']);
        });
    }

    public function cancelPendingQr(PosOrder $order, string $reason = 'cancelled'): PosOrder
    {
        abort_unless($order->status === 'pending_payment', 409, 'Only pending orders can be cancelled.');

        return DB::transaction(function () use ($order, $reason): PosOrder {
            // Receipt processing locks the attempt before the order; keep that order. An abort below rolls this back.
            $this->posQr->expire($order);
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

    public function openShift(User $cashier, int $organizationId, int $openingCash, ?int $venueId = null, ?string $registerId = null): CashShift
    {
        abort_unless($openingCash >= 0, 422);
        $query = CashShift::where('organization_id', $organizationId)
            ->where('cashier_id', $cashier->id)
            ->where('status', 'open');
        if ($registerId !== null) {
            $query->where('register_id', $registerId);
        }
        abort_if($query->exists(), 409, 'Cashier already has an open shift.');
        $venueId = $this->posQr->venueIdFor($cashier, $organizationId, $venueId);
        $this->posQr->assertOperableVenue($cashier, $organizationId, $venueId);

        return CashShift::create([
            'organization_id' => $organizationId,
            'venue_id' => $venueId,
            'register_id' => $registerId,
            'cashier_id' => $cashier->id,
            'opening_cash' => $openingCash,
            'opened_at' => now(),
            'status' => 'open',
            'cash_movements' => [],
        ]);
    }

    public function closeShift(
        CashShift $shift,
        User $cashier,
        int $declaredCash,
        ?string $note = null,
        ?string $pendingDisposition = null,
        ?int $handoverToShiftId = null
    ): CashShift {
        return DB::transaction(function () use ($shift, $cashier, $declaredCash, $note, $pendingDisposition, $handoverToShiftId): CashShift {
            $shift = CashShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            abort_unless($shift->cashier_id === $cashier->id && $shift->status === 'open', 409);

            // Task 8.9 [D56]: Check for unresolved pending orders
            $pendingOrders = PosOrder::where('cash_shift_id', $shift->id)
                ->where('status', 'pending_payment')
                ->get();

            if ($pendingOrders->isNotEmpty()) {
                abort_unless(
                    in_array($pendingDisposition, ['cancel', 'handover'], true),
                    409,
                    "Cannot close shift with {$pendingOrders->count()} pending order(s) without disposition ('cancel' or 'handover')."
                );

                if ($pendingDisposition === 'cancel') {
                    foreach ($pendingOrders as $pendingOrder) {
                        $this->cancelPendingQr($pendingOrder, 'Cancelled upon shift close');
                    }
                } elseif ($pendingDisposition === 'handover') {
                    foreach ($pendingOrders as $pendingOrder) {
                        $pendingOrder->update([
                            'handover_status' => 'handed_over',
                            'handed_over_to_shift_id' => $handoverToShiftId,
                        ]);
                    }
                }
            }

            // Task 8.8 [D55] & Task 8.10 [D57]: Calculate expected cash strictly by shift orders & cash movements
            $cashSales = (int) PosOrder::where('cash_shift_id', $shift->id)
                ->where('status', 'completed')
                ->where('payment_method', 'cash')
                ->sum('total_amount');

            $mixedCashSales = (int) PosPayment::whereHas('order', function ($q) use ($shift) {
                $q->where('cash_shift_id', $shift->id)
                    ->where('status', 'completed')
                    ->where('payment_method', 'mixed');
            })
                ->where('method', 'cash')
                ->where('status', 'paid')
                ->sum('amount');

            // Fallback for legacy orders without cash_shift_id
            if ($cashSales === 0 && $mixedCashSales === 0) {
                $cashSales = (int) PosOrder::where('organization_id', $shift->organization_id)
                    ->where('cashier_id', $cashier->id)
                    ->where('payment_method', 'cash')
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$shift->opened_at, now()])
                    ->whereNull('cash_shift_id')
                    ->sum('total_amount');
            }

            $movements = (array) ($shift->cash_movements ?? []);
            $cashInflows = 0;
            $cashOutflows = 0;
            $cashRefunds = 0;
            foreach ($movements as $m) {
                $mType = $m['type'] ?? '';
                $mAmt = (int) ($m['amount'] ?? 0);
                if ($mType === 'cash_in') {
                    $cashInflows += $mAmt;
                } elseif ($mType === 'cash_out') {
                    $cashOutflows += $mAmt;
                } elseif ($mType === 'refund') {
                    $cashRefunds += $mAmt;
                }
            }

            $expected = (int) $shift->opening_cash + $cashSales + $mixedCashSales + $cashInflows - $cashOutflows - $cashRefunds;
            $variance = $declaredCash - $expected;

            $shift->update([
                'expected_cash' => $expected,
                'declared_cash' => $declaredCash,
                'variance' => $variance,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            ShiftReconciliation::create([
                'organization_id' => $shift->organization_id,
                'cash_shift_id' => $shift->id,
                'variance' => $variance,
                'status' => $variance === 0 ? 'matched' : 'exception',
                'note' => $note,
            ]);

            return $shift->fresh('reconciliation');
        });
    }

    public function recordCashMovement(CashShift $shift, User $cashier, string $type, int $amount, string $reason): CashShift
    {
        abort_unless(in_array($type, ['cash_in', 'cash_out'], true), 422, 'Invalid cash movement type.');
        abort_unless($amount > 0, 422, 'Amount must be positive.');

        return DB::transaction(function () use ($shift, $cashier, $type, $amount, $reason): CashShift {
            $shift = CashShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
            abort_unless($shift->status === 'open', 409, 'Cannot record cash movement on a closed shift.');

            $movements = (array) ($shift->cash_movements ?? []);
            $movements[] = [
                'id' => uniqid('cm_'),
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'created_at' => now()->toIso8601String(),
            ];
            $shift->update(['cash_movements' => $movements]);

            return $shift->fresh();
        });
    }

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
                    $product = $item->product;
                    if ($product && $product->isTracked()) {
                        $product->increment('stock_quantity', $item->quantity);
                        StockMovement::create([
                            'organization_id' => $order->organization_id,
                            'product_id' => $product->id,
                            'type' => 'return',
                            'quantity_delta' => $item->quantity,
                            'unit_cost' => $product->cost,
                            'reference_type' => PosOrder::class,
                            'reference_id' => $order->id,
                            'created_by' => $cashier->id,
                            'metadata' => ['reason' => $reason],
                        ]);
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

                    $product = $orderItem->product;
                    if ($restock && $product && $product->isTracked()) {
                        $product->increment('stock_quantity', $qty);
                        StockMovement::create([
                            'organization_id' => $order->organization_id,
                            'product_id' => $product->id,
                            'type' => 'return',
                            'quantity_delta' => $qty,
                            'unit_cost' => $product->cost,
                            'reference_type' => PosOrder::class,
                            'reference_id' => $order->id,
                            'created_by' => $cashier->id,
                            'metadata' => ['reason' => $reason],
                        ]);
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

    public function releaseExpiredPendingOrders(int $organizationId): int
    {
        $now = now();
        $orders = PosOrder::where('organization_id', $organizationId)
            ->where('status', 'pending_payment')
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $isExpired = false;
            if ($order->created_at && $order->created_at->lt($now->copy()->subMinutes(15))) {
                $isExpired = true;
            }
            $expiresAt = data_get($order->metadata, 'expires_at');
            if ($expiresAt && Carbon::parse($expiresAt)->lt($now)) {
                $isExpired = true;
            }

            if ($isExpired) {
                $this->cancelPendingQr($order, 'Expired automatically due to pending payment TTL');
                $count++;
            }
        }

        return $count;
    }

    public function publishPlan(int $organizationId, array $data): TenantMembershipPlan
    {
        $version = (int) TenantMembershipPlan::where('organization_id', $organizationId)->where('name', $data['name'])->max('version') + 1;

        return TenantMembershipPlan::create(['organization_id' => $organizationId, 'name' => $data['name'], 'version' => $version, 'price' => $data['price'], 'benefits' => $data['benefits'], 'status' => 'published', 'published_at' => now()]);
    }

    public function assignMembership(int $organizationId, int $userId, TenantMembershipPlan $plan, int $months = 1): TenantMembership
    {
        return DB::transaction(function () use ($organizationId, $userId, $plan, $months): TenantMembership {
            $plan = TenantMembershipPlan::query()
                ->where('organization_id', $organizationId)
                ->whereKey($plan->id)
                ->whereIn('status', ['active', 'published'])
                ->lockForUpdate()
                ->firstOrFail();
            $customerBelongsToOrganization = OrganizationMember::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first() !== null
                || TenantCustomer::query()
                    ->where('organization_id', $organizationId)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first() !== null;
            abort_unless($customerBelongsToOrganization, 422, 'Customer does not belong to this organization.');

            $membership = TenantMembership::create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'plan_id' => $plan->id,
                'plan_version' => $plan->version,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonths($months),
            ]);
            foreach ($plan->benefits as $code => $value) {
                MembershipEntitlement::create([
                    'organization_id' => $organizationId,
                    'membership_id' => $membership->id,
                    'benefit_code' => $code,
                    'benefit_value' => ['value' => $value, 'plan_version' => $plan->version],
                    'starts_at' => $membership->starts_at,
                    'ends_at' => $membership->ends_at,
                ]);
            }

            return $membership->fresh('entitlements');
        });
    }

    public function awardPoints(int $organizationId, int $userId, int $amountVnd, string $sourceType, int $sourceId): PointLedger
    {
        // Earn under the disclosed base rule times the active member multiplier
        // (applied once), with the rule's forward-dated (book-ahead) expiry.
        $earn = $this->loyaltyPoints->resolveEarnPoints($organizationId, $userId, $amountVnd);

        return $this->writePoints(
            $organizationId,
            $userId,
            $earn['rule']->id,
            $earn['points'],
            $sourceType,
            $sourceId,
            $earn['expires_at'],
            [
                'base_points' => $earn['base_points'],
                'earn_multiplier' => $earn['multiplier'],
                'rule_version' => (int) $earn['rule']->version,
            ],
        );
    }

    public function benefits(int $organizationId, int $userId)
    {
        $now = now();

        return MembershipEntitlement::query()->where('organization_id', $organizationId)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->whereHas('membership', fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now))
            ->get();
    }

    public function spendWallet(int $organizationId, int $userId, int $amount, string $reference): WalletTransaction
    {
        abort_unless($amount > 0, 422);
        $this->walletAvailability->assertAvailable($organizationId);

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
        $this->walletAvailability->assertAvailable($organizationId);

        return DB::transaction(function () use ($organizationId, $userId, $amount, $sourceType, $sourceId, $reference): WalletTransaction {
            $wallet = WalletAccount::firstOrCreate(['organization_id' => $organizationId, 'user_id' => $userId, 'currency' => 'VND'], ['balance' => 0, 'status' => 'active']);
            $wallet = WalletAccount::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance', $amount);
            $transaction = WalletTransaction::create(['organization_id' => $organizationId, 'wallet_account_id' => $wallet->id, 'type' => 'credit', 'amount' => $amount, 'balance_after' => $wallet->balance, 'source_type' => $sourceType, 'source_id' => $sourceId, 'reference' => $reference]);
            $this->financials->postWalletCredit($organizationId, $wallet->id, $amount, $reference);

            return $transaction;
        });
    }

    private function writePoints(int $organizationId, int $userId, int $ruleId, int $delta, string $sourceType, int $sourceId, ?CarbonInterface $expiresAt = null, array $metadata = []): PointLedger
    {
        return DB::transaction(function () use ($organizationId, $userId, $ruleId, $delta, $sourceType, $sourceId, $expiresAt, $metadata): PointLedger {
            User::query()->whereKey($userId)->lockForUpdate()->first();
            $balance = (int) PointLedger::where('organization_id', $organizationId)->where('user_id', $userId)->sum('delta');
            abort_unless($balance + $delta >= 0, 409, 'Loyalty points cannot become negative.');

            return PointLedger::create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'loyalty_rule_id' => $ruleId,
                'delta' => $delta,
                'balance_after' => $balance + $delta,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'expires_at' => $expiresAt,
                'metadata' => $metadata !== [] ? $metadata : null,
            ]);
        });
    }
}
