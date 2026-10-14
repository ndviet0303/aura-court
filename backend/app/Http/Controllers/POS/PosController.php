<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\LoyaltyRule;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\ShiftReconciliation;
use App\Models\StockMovement;
use App\Models\TenantMembershipPlan;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\POS\PosQrPaymentService;
use App\Services\POS\PosService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly PosService $pos, private readonly IdempotencyService $idempotency, private readonly PosQrPaymentService $posQr) {}

    public function products(Request $request)
    {
        $organization = $this->organization($request);
        $query = PosProduct::where('organization_id', $organization->id)->where('is_active', true);

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $cat = $request->category;
            $query->where('metadata->category', $cat);
        }

        return $query->orderBy('name')->get();
    }

    public function showProduct(Request $request, PosProduct $product)
    {
        $organization = $this->organization($request);
        abort_unless($product->organization_id === $organization->id, 404);

        return response()->json(['product' => $product->load('movements')]);
    }

    public function storeProduct(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'sku' => [
                'required', 'string', 'max:80',
                Rule::unique('pos_products')->where('organization_id', $organization->id),
            ],
            'barcode' => [
                'nullable', 'string', 'max:100',
                Rule::unique('pos_products')->where('organization_id', $organization->id),
            ],
            'name' => 'required|string|max:180',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'cost' => 'nullable|integer|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'min_stock' => 'nullable|integer|min:0',
            'supplier' => 'nullable|string|max:180',
            'tax_rate' => 'nullable|integer|in:0,5,8,10',
            'metadata' => 'nullable|array',
        ]);

        return $this->idempotency->run($request, fn () => response()->json(['product' => $this->pos->createProduct($organization->id, $data, $request->user())], 201));
    }

    public function updateProduct(Request $request, PosProduct $product)
    {
        $organization = $this->organization($request);
        abort_unless($product->organization_id === $organization->id, 404);
        $data = $request->validate([
            'sku' => [
                'sometimes', 'string', 'max:80',
                Rule::unique('pos_products')->where('organization_id', $organization->id)->ignore($product->id),
            ],
            'barcode' => [
                'nullable', 'string', 'max:100',
                Rule::unique('pos_products')->where('organization_id', $organization->id)->ignore($product->id),
            ],
            'name' => 'sometimes|string|max:180',
            'description' => 'nullable|string',
            'price' => 'sometimes|integer|min:0',
            'cost' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'min_stock' => 'nullable|integer|min:0',
            'supplier' => 'nullable|string|max:180',
            'tax_rate' => 'nullable|integer|in:0,5,8,10',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        return $this->idempotency->run($request, fn () => response()->json(['product' => $this->pos->updateProduct($product, $data)]));
    }

    public function destroyProduct(Request $request, PosProduct $product)
    {
        $organization = $this->organization($request);
        abort_unless($product->organization_id === $organization->id, 404);
        $this->pos->deleteProduct($product);

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    public function adjustStock(Request $request, PosProduct $product)
    {
        $organization = $this->organization($request);
        abort_unless($product->organization_id === $organization->id, 404);
        $data = $request->validate([
            'quantity_delta' => 'nullable|integer',
            'quantity' => 'nullable|integer|min:0',
            'type' => 'nullable|string|in:in,out,adjust,receipt,adjustment,return,disposal',
            'note' => 'nullable|string|max:1000',
            'reference' => 'nullable|string|max:160',
        ]);

        $type = $data['type'] ?? 'adjustment';
        $note = $data['note'] ?? $data['reference'] ?? null;
        $reference = $data['reference'] ?? null;

        if (isset($data['quantity_delta']) && $data['quantity_delta'] !== 0) {
            $delta = (int) $data['quantity_delta'];
        } else {
            $qty = (int) ($data['quantity'] ?? 0);
            if (in_array($type, ['in', 'receipt'])) {
                $delta = abs($qty);
            } elseif (in_array($type, ['out', 'disposal'])) {
                $delta = -abs($qty);
            } else {
                $delta = $qty - $product->stock_quantity;
            }
        }

        abort_if($delta === 0, 422, 'Số lượng điều chỉnh không thể bằng 0.');

        return $this->idempotency->run($request, fn () => response()->json([
            'product' => $this->pos->adjustStock($product, $delta, $request->user(), $type, $reference, $note),
        ]));
    }

    public function movements(Request $request)
    {
        $organization = $this->organization($request);
        $query = StockMovement::where('organization_id', $organization->id)
            ->with(['product', 'creator:id,name,email'])
            ->latest('created_at')->latest('id');

        if ($request->filled('type') && $request->type !== 'all') {
            $type = $request->type;
            if ($type === 'in') {
                $query->whereIn('type', ['in', 'receipt']);
            } elseif ($type === 'out') {
                $query->whereIn('type', ['out', 'sale', 'disposal']);
            } elseif ($type === 'adjust') {
                $query->whereIn('type', ['adjust', 'adjustment']);
            } else {
                $query->where('type', $type);
            }
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from.' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to.' 23:59:59');
        }

        return $query->take(150)->get();
    }

    public function inventoryKpis(Request $request)
    {
        $organization = $this->organization($request);
        $products = PosProduct::where('organization_id', $organization->id)->where('is_active', true)->get();

        $totalItems = (int) $products->sum('stock_quantity');
        $totalValue = (int) $products->reduce(fn ($sum, $p) => $sum + ($p->stock_quantity * $p->cost), 0);
        $lowStockCount = $products->filter(fn ($p) => $p->stock_quantity <= $p->min_stock && $p->stock_quantity > 0)->count();
        $outOfStockCount = $products->filter(fn ($p) => $p->stock_quantity === 0)->count();

        return response()->json([
            'totalItems' => $totalItems,
            'totalValue' => $totalValue,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
        ]);
    }

    public function quote(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.rental_duration_hours' => 'nullable|integer|min:1',
            'items.*.rental_hours' => 'nullable|integer|min:1',
            'items.*.vat_rate' => 'nullable|numeric|min:0|max:1',
            'customer_id' => 'nullable|integer',
            'reservation_id' => 'nullable|integer',
            'venue_id' => 'nullable|integer',
            'coupon_code' => 'nullable|string|max:50',
            'discount_amount' => 'nullable|integer|min:0',
            'payment_method' => 'nullable|in:cash,qr,wallet',
            'cash_tendered' => 'nullable|integer|min:0',
            'rounding_amount' => 'nullable|integer',
        ]);
        $quote = $this->pos->calculateQuote($organization->id, $data, $request->user());

        return response()->json(['quote' => $quote]);
    }

    public function completeOrder(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.rental_duration_hours' => 'nullable|integer|min:1',
            'items.*.rental_hours' => 'nullable|integer|min:1',
            'items.*.vat_rate' => 'nullable|numeric|min:0|max:1',
            'customer_id' => 'nullable|integer',
            'reservation_id' => 'nullable|integer',
            'venue_id' => 'nullable|integer',
            'register_id' => 'nullable|string|max:80',
            'payment_method' => 'required|in:cash,qr,wallet,mixed',
            'allow_mixed_tender' => 'nullable|boolean',
            'tenders' => 'nullable|array',
            'tenders.*.method' => 'required_with:tenders|in:cash,qr,wallet',
            'tenders.*.amount' => 'required_with:tenders|integer|min:1',
            'tenders.*.cash_tendered' => 'nullable|integer|min:0',
            'tenders.*.change_due' => 'nullable|integer|min:0',
            'payment_reference' => 'nullable|string|max:160',
            'discount_amount' => 'nullable|integer|min:0',
            'coupon_code' => 'nullable|string|max:50',
            'cash_tendered' => 'nullable|integer|min:0',
            'rounding_amount' => 'nullable|integer',
            'offline' => 'nullable|boolean',
        ]);

        $activeShift = CashShift::where('organization_id', $organization->id)
            ->where('cashier_id', $request->user()->id)
            ->where('status', 'open')
            ->first();
        abort_unless($activeShift !== null, 409, 'Cashier does not have an active open shift.');

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $order = $this->pos->completeOrder($request->user(), $organization->id, $data);

            return response()->json([
                'order' => $order,
                'receipt' => $order->receipt,
                'payment_attempt' => $this->posQr->instructionsFor($order),
            ], 201);
        });
    }

    public function showOrder(Request $request, PosOrder $order)
    {
        $organization = $this->organization($request);
        abort_unless($order->organization_id === $organization->id, 404);

        return response()->json([
            'order' => $order->load(['items', 'payments', 'customer:id,name,email,phone', 'cashier:id,name']),
            'receipt' => $order->receipt,
            'payment_attempt' => $this->posQr->instructionsFor($order),
        ]);
    }

    public function cancelQrPayment(Request $request, PosOrder $order)
    {
        $organization = $this->organization($request);
        abort_unless($order->organization_id === $organization->id, 404);
        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        return $this->idempotency->run($request, function () use ($order, $data) {
            $cancelled = $this->pos->cancelPendingQr($order, $data['reason'] ?? 'cancelled_by_cashier');

            return response()->json([
                'order' => $cancelled,
                'receipt' => $cancelled->receipt,
            ]);
        });
    }

    public function printReceipt(Request $request, PosOrder $order)
    {
        $organization = $this->organization($request);
        abort_unless($order->organization_id === $organization->id, 404);

        $order->load(['items', 'payments', 'customer:id,name,email,phone', 'cashier:id,name']);
        $receipt = $order->receipt ?? [];

        $isPaid = $order->status === 'completed' && ((bool) ($receipt['is_paid'] ?? false));
        if (! $isPaid) {
            $receipt['is_paid'] = false;
            $receipt['status'] = 'unpaid_draft';
            $receipt['warning'] = 'UNPAID DRAFT RECEIPT - NOT A VALID TAX INVOICE';
        }

        return response()->json([
            'order' => $order,
            'receipt' => $receipt,
            'printable' => $isPaid,
            'printed_at' => now()->toIso8601String(),
        ]);
    }

    public function sendReceipt(Request $request, PosOrder $order)
    {
        $organization = $this->organization($request);
        abort_unless($order->organization_id === $organization->id, 404);

        $data = $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
        ]);

        $recipient = $data['email'] ?? $data['phone'] ?? $order->customer?->email ?? $order->customer?->phone;
        abort_unless(! empty($recipient), 422, 'Recipient email or phone is required.');

        $receipt = $order->receipt ?? [];
        $isPaid = $order->status === 'completed' && ((bool) ($receipt['is_paid'] ?? false));
        if (! $isPaid) {
            $receipt['is_paid'] = false;
            $receipt['status'] = 'unpaid_draft';
            $receipt['warning'] = 'UNPAID DRAFT RECEIPT - NOT A VALID TAX INVOICE';
        }

        return response()->json([
            'sent' => true,
            'recipient' => $recipient,
            'order_id' => $order->id,
            'receipt' => $receipt,
            'sent_at' => now()->toIso8601String(),
        ]);
    }

    public function refundOrder(Request $request, PosOrder $order)
    {
        $organization = $this->organization($request);
        abort_unless($order->organization_id === $organization->id, 404);

        $data = $request->validate([
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.restock' => 'nullable|boolean',
            'reason' => 'nullable|string|max:500',
            'refund_method' => 'nullable|in:cash,original_method,wallet',
        ]);

        return $this->idempotency->run($request, function () use ($order, $request, $data) {
            $refunded = $this->pos->refundOrder(
                $order,
                $request->user(),
                $data['items'] ?? [],
                $data['reason'] ?? 'Customer return',
                $data['refund_method'] ?? 'cash'
            );

            return response()->json([
                'order' => $refunded,
                'receipt' => $refunded->receipt,
            ]);
        });
    }

    public function openShift(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'opening_cash' => 'required|integer|min:0',
            'venue_id' => 'nullable|integer',
            'register_id' => 'nullable|string|max:80',
        ]);

        return $this->idempotency->run($request, fn () => response()->json([
            'shift' => $this->pos->openShift(
                $request->user(),
                $organization->id,
                $data['opening_cash'],
                $data['venue_id'] ?? null,
                $data['register_id'] ?? null
            ),
        ], 201));
    }

    public function closeShift(Request $request, CashShift $shift)
    {
        $organization = $this->organization($request);
        abort_unless($shift->organization_id === $organization->id, 404);
        $data = $request->validate([
            'declared_cash' => 'required|integer|min:0',
            'note' => 'nullable|string|max:2000',
            'pending_disposition' => 'nullable|string|in:cancel,handover',
            'handover_to_shift_id' => 'nullable|integer',
        ]);

        return $this->idempotency->run($request, fn () => response()->json([
            'shift' => $this->pos->closeShift(
                $shift,
                $request->user(),
                $data['declared_cash'],
                $data['note'] ?? null,
                $data['pending_disposition'] ?? null,
                $data['handover_to_shift_id'] ?? null
            ),
        ]));
    }

    public function recordCashMovement(Request $request, CashShift $shift)
    {
        $organization = $this->organization($request);
        abort_unless($shift->organization_id === $organization->id, 404);

        $data = $request->validate([
            'type' => 'required|in:cash_in,cash_out',
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
        ]);

        return $this->idempotency->run($request, function () use ($shift, $request, $data) {
            $updatedShift = $this->pos->recordCashMovement(
                $shift,
                $request->user(),
                $data['type'],
                $data['amount'],
                $data['reason']
            );

            return response()->json([
                'shift' => $updatedShift,
            ]);
        });
    }

    public function shifts(Request $request)
    {
        $organization = $this->organization($request);
        $query = CashShift::where('organization_id', $organization->id)
            ->with(['reconciliation.reviewer:id,name,email', 'cashier:id,name,email'])
            ->latest('opened_at');

        if ($request->has('status') && in_array($request->status, ['open', 'closed'])) {
            $query->where('status', $request->status);
        }

        $shifts = $query->paginate((int) ($request->get('per_page', 50)));

        $shifts->getCollection()->transform(function ($shift) {
            $orders = PosOrder::where('cash_shift_id', $shift->id)
                ->where('status', 'completed')
                ->get();

            if ($orders->isEmpty()) {
                $endTime = $shift->closed_at ?: now();
                $orders = PosOrder::where('organization_id', $shift->organization_id)
                    ->where('cashier_id', $shift->cashier_id)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$shift->opened_at, $endTime])
                    ->whereNull('cash_shift_id')
                    ->get();
            }

            $cashSales = (int) $orders->where('payment_method', 'cash')->sum('total_amount');
            $mixedCash = (int) PosPayment::whereHas('order', function ($q) use ($shift) {
                $q->where('cash_shift_id', $shift->id)
                    ->where('status', 'completed')
                    ->where('payment_method', 'mixed');
            })->where('method', 'cash')->where('status', 'paid')->sum('amount');
            $cashSales += $mixedCash;

            $qrSales = (int) $orders->where('payment_method', 'qr')->sum('total_amount');
            $walletSales = (int) $orders->where('payment_method', 'wallet')->sum('total_amount');
            $totalSales = (int) $orders->sum('total_amount');

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

            $shift->cash_sales = $cashSales;
            $shift->qr_sales = $qrSales;
            $shift->wallet_sales = $walletSales;
            $shift->total_sales = $totalSales;
            $shift->orders_count = $orders->count();
            if ($shift->status === 'open') {
                $shift->expected_cash = $shift->opening_cash + $cashSales + $cashInflows - $cashOutflows - $cashRefunds;
            }

            return $shift;
        });

        return $shifts;
    }

    public function activeShift(Request $request)
    {
        $organization = $this->organization($request);
        $shift = CashShift::where('organization_id', $organization->id)
            ->where('cashier_id', $request->user()->id)
            ->where('status', 'open')
            ->with(['reconciliation.reviewer:id,name,email', 'cashier:id,name,email'])
            ->latest('opened_at')
            ->first();

        if (! $shift) {
            $shift = CashShift::where('organization_id', $organization->id)
                ->where('status', 'open')
                ->with(['reconciliation.reviewer:id,name,email', 'cashier:id,name,email'])
                ->latest('opened_at')
                ->first();
        }

        if ($shift) {
            $orders = PosOrder::where('cash_shift_id', $shift->id)
                ->where('status', 'completed')
                ->get();

            if ($orders->isEmpty()) {
                $orders = PosOrder::where('organization_id', $shift->organization_id)
                    ->where('cashier_id', $shift->cashier_id)
                    ->where('status', 'completed')
                    ->where('created_at', '>=', $shift->opened_at)
                    ->whereNull('cash_shift_id')
                    ->get();
            }

            $cashSales = (int) $orders->where('payment_method', 'cash')->sum('total_amount');
            $mixedCash = (int) PosPayment::whereHas('order', function ($q) use ($shift) {
                $q->where('cash_shift_id', $shift->id)
                    ->where('status', 'completed')
                    ->where('payment_method', 'mixed');
            })->where('method', 'cash')->where('status', 'paid')->sum('amount');
            $cashSales += $mixedCash;

            $qrSales = (int) $orders->where('payment_method', 'qr')->sum('total_amount');
            $walletSales = (int) $orders->where('payment_method', 'wallet')->sum('total_amount');
            $totalSales = (int) $orders->sum('total_amount');

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

            $shift->cash_sales = $cashSales;
            $shift->qr_sales = $qrSales;
            $shift->wallet_sales = $walletSales;
            $shift->total_sales = $totalSales;
            $shift->orders_count = $orders->count();
            $shift->expected_cash = $shift->opening_cash + $cashSales + $cashInflows - $cashOutflows - $cashRefunds;
            // The POS hides QR tender unless the shift's venue has a verified owner receiving connection.
            $shift->setAttribute('qr_available', $this->posQr->connectionFor($organization->id, $this->posQr->venueIdFor($request->user(), $organization->id, $shift->venue_id)) !== null);
        }

        return response()->json(['shift' => $shift]);
    }

    public function reviewReconciliation(Request $request, CashShift $shift)
    {
        $organization = $this->organization($request);
        abort_unless($shift->organization_id === $organization->id, 404);
        $data = $request->validate(['status' => 'required|in:matched,approved,exception,rejected', 'note' => 'nullable|string|max:2000']);

        $reconciliation = $shift->reconciliation ?: new ShiftReconciliation([
            'organization_id' => $organization->id,
            'cash_shift_id' => $shift->id,
            'variance' => $shift->variance ?? 0,
        ]);

        $reconciliation->status = $data['status'];
        if (isset($data['note'])) {
            $reconciliation->note = ($reconciliation->note ? $reconciliation->note.' | ' : '').$data['note'];
        }
        $reconciliation->reviewed_by = $request->user()->id;
        $reconciliation->reviewed_at = now();
        $reconciliation->save();

        return response()->json(['reconciliation' => $reconciliation, 'shift' => $shift->fresh('reconciliation')]);
    }

    public function membershipPlans(Request $request)
    {
        $organization = $this->organization($request);

        return TenantMembershipPlan::where('organization_id', $organization->id)
            ->whereIn('status', ['active', 'published'])
            ->latest('published_at')
            ->get();
    }

    public function publishMembershipPlan(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['name' => 'required|string|max:160', 'price' => 'required|integer|min:0', 'benefits' => 'required|array']);

        return $this->idempotency->run($request, fn () => response()->json(['plan' => $this->pos->publishPlan($organization->id, $data)], 201));
    }

    public function assignMembership(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['user_id' => 'required|integer', 'plan_id' => 'required|integer', 'months' => 'nullable|integer|min:1|max:24']);
        $plan = TenantMembershipPlan::where('organization_id', $organization->id)->findOrFail($data['plan_id']);

        return $this->idempotency->run($request, fn () => response()->json(['membership' => $this->pos->assignMembership($organization->id, $data['user_id'], $plan, $data['months'] ?? 1)], 201));
    }

    public function publishLoyaltyRule(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['name' => 'required|string|max:160', 'earn_points_per_100_vnd' => 'required|integer|min:0', 'redeem_points_per_100_vnd' => 'required|integer|min:1']);

        return $this->idempotency->run($request, fn () => response()->json(['rule' => LoyaltyRule::create($data + ['organization_id' => $organization->id, 'version' => (int) LoyaltyRule::where('organization_id', $organization->id)->where('name', $data['name'])->max('version') + 1, 'status' => 'published', 'published_at' => now()])], 201));
    }

    public function benefits(Request $request)
    {
        $organization = $this->organization($request);

        return $this->pos->benefits($organization->id, $request->user()->id);
    }

    public function redeemPoints(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['points' => 'required|integer|min:1', 'source_type' => 'required|string|max:120', 'source_id' => 'required|integer']);

        return $this->idempotency->run($request, fn () => response()->json(['points' => $this->pos->redeemPoints($organization->id, $request->user()->id, $data['points'], $data['source_type'], $data['source_id'])], 201));
    }

    public function creditWallet(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['user_id' => 'required|integer', 'amount' => 'required|integer|min:1', 'source_type' => 'required|string|max:120', 'source_id' => 'required|integer', 'reference' => 'required|string|max:160']);

        return $this->idempotency->run($request, fn () => response()->json(['transaction' => $this->pos->creditWallet($organization->id, $data['user_id'], $data['amount'], $data['source_type'], $data['source_id'], $data['reference'])], 201));
    }

    public function spendWallet(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['amount' => 'required|integer|min:1', 'reference' => 'required|string|max:160']);

        return $this->idempotency->run($request, fn () => response()->json(['transaction' => $this->pos->spendWallet($organization->id, $request->user()->id, $data['amount'], $data['reference'])], 201));
    }
}
