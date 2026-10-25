<?php

namespace App\Services\POS;

use App\Contracts\Repositories\VenueStockRepositoryInterface;
use App\Models\CashShift;
use App\Models\MembershipEntitlement;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\ShiftReconciliation;
use App\Models\TenantCustomer;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\Finance\FinancialPostingService;
use App\Services\Finance\VenueCreditService;
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
        private readonly VenueStockRepositoryInterface $stock,
        private readonly VenueCreditService $credits,
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
        // Opening stock lands at one venue the owner names (D3).
        $venueId = $initialStock > 0 ? (int) ($data['venue_id'] ?? 0) : null;
        if ($venueId !== null) {
            abort_if($venueId === 0 || $actor === null, 422, 'Chọn cơ sở nhận tồn ban đầu.');
            $this->posQr->assertOperableVenue($actor, $organizationId, $venueId);
        }

        return DB::transaction(function () use ($organizationId, $data, $metadata, $initialStock, $venueId, $actor): PosProduct {
            $product = PosProduct::create([
                'organization_id' => $organizationId,
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => (int) $data['price'],
                'cost' => (int) ($data['cost'] ?? 0),
                'is_active' => true,
                'metadata' => $metadata,
            ]);

            if ($venueId) {
                $stock = $this->stock->lock($organizationId, $venueId, $product->id);
                $this->stock->apply($stock, $initialStock, 'in', $actor->id, 'initial_stock', null, ['note' => 'Khởi tạo tồn kho ban đầu']);
            }

            return $product->setAttribute('stock_quantity', $venueId ? $initialStock : null);
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

    /** Receipt, issue or count at one venue; the product comes back with that venue's stock. */
    public function adjustStock(PosProduct $product, int $venueId, int $delta, User $actor, string $type = 'adjustment', ?string $reference = null, ?string $note = null): PosProduct
    {
        return DB::transaction(function () use ($product, $venueId, $delta, $actor, $type, $reference, $note): PosProduct {
            $stock = $this->stock->lock($product->organization_id, $venueId, $product->id);
            $this->stock->apply($stock, $delta, $type, $actor->id, 'pos', null, ['reference' => $reference, 'note' => $note ?? $reference]);

            return $product->setAttribute('stock_quantity', $stock->quantity);
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
        abort_unless(in_array($method, ['cash', 'qr', 'wallet', 'credit', 'mixed'], true), 422, 'Unsupported POS payment method.');
        $this->walletAvailability->assertAvailable($organizationId, in_array('wallet', [$method, ...array_column((array) ($data['tenders'] ?? []), 'method')], true));

        $activeShift = CashShift::where('organization_id', $organizationId)
            ->where('cashier_id', $cashier->id)
            ->where('status', 'open')
            ->first();

        abort_if($activeShift === null, 409, 'Cashier does not have an active open shift.');
        // The order belongs to the shift's venue; a different venue or a booking elsewhere is refused before any effect.
        abort_if($activeShift->venue_id === null, 422, 'Ca đang mở chưa gắn cơ sở. Hãy đóng ca và mở ca mới tại cơ sở bạn đang bán.');
        $saleVenueId = (int) $activeShift->venue_id;
        abort_if(! empty($data['venue_id']) && (int) $data['venue_id'] !== $saleVenueId, 422, 'Đơn phải thuộc cơ sở của ca đang mở.');
        $this->posQr->assertOperableVenue($cashier, $organizationId, $saleVenueId);

        $reservationId = empty($data['reservation_id']) ? null : $this->backfill->resolveCanonicalReservationId((int) $data['reservation_id'], $organizationId);
        abort_if($reservationId && (int) Reservation::query()->find($reservationId)?->court()->value('venue_id') !== $saleVenueId, 422, 'Lượt đặt sân thuộc cơ sở khác với ca đang mở.');

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
                $allowMixed = (bool) data_get(Venue::find($saleVenueId)?->currentBookingPolicy?->settings, 'pos.allow_mixed_tender', false);
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
                    abort_unless(in_array($tMethod, ['cash', 'qr', 'wallet', 'credit'], true), 422, "Unsupported tender method: {$tMethod}");
                    abort_unless($tAmount > 0, 422, 'Tender amount must be positive.');
                    $tenderSum += $tAmount;
                }
                abort_unless($tenderSum === $grandTotal, 422, "Total tender allocations ({$tenderSum}) must equal order total ({$grandTotal}).");
            }
            // Venue credit of this organization (design D6); an order still awaiting QR only checks the balance and spends it on payment.
            $creditPart = $method === 'credit' ? $grandTotal : (int) collect($tenders)->where('method', 'credit')->sum('amount');
            abort_if($creditPart > 0 && empty($data['customer_id']), 422, 'Customer is required for credit payment.');
            abort_if($creditPart > 0 && $this->credits->balance($organizationId, (int) $data['customer_id']) < $creditPart, 422, 'Số dư credit không đủ.');

            // Stock of the sale venue, rows locked in product id order (D4); what tabs and QR orders there hold is not for sale.
            $stocks = [];
            foreach (collect($quote['items'])->sortBy('product_id') as $item) {
                $product = $item['product'];
                if (! $product->isTracked()) {
                    continue;
                }
                $stock = $stocks[$product->id] ??= $this->stock->lock($organizationId, $saleVenueId, $product->id);
                $held = $this->stock->held($saleVenueId, $product->id);
                $available = max(0, $stock->quantity - $held);
                $tabMsg = $held > 0 ? " ({$held} reserved by pending orders/tabs)." : '.';
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
                'cash_shift_id' => $activeShift->id,
                'venue_id' => $saleVenueId,
                'register_id' => $data['register_id'] ?? $activeShift->register_id,
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
            if ($creditPart > 0 && ! $isPendingQr) {
                $this->credits->spend($organizationId, (int) $data['customer_id'], $creditPart, 'pos_order', $order->id, $cashier->id, $orderCode);
            }

            foreach ($quote['items'] as $item) {
                $product = $item['product'];
                if (! $isPendingQr && $product->isTracked()) {
                    $this->stock->apply($stocks[$product->id], -$item['quantity'], 'sale', $cashier->id, PosOrder::class, $order->id);
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
                    $tStatus = $tMethod === 'qr' || ($tMethod === 'credit' && $isPendingQr) ? 'pending' : 'paid';

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
            } elseif ($method !== 'wallet' && $grandTotal > $creditPart) {
                // The credit part is revenue through its own venue_credit.spend journal.
                $this->financials->postPosSale($organizationId, $order->id, $grandTotal - $creditPart, $method, $order->payment_reference);
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
