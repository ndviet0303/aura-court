<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\CashShift;
use App\Models\LoyaltyRule;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Models\TenantMembershipPlan;
use App\Models\WalletAccount;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\POS\PosService;
use Illuminate\Http\Request;

class PosController extends Controller
{
    use ResolvesV2Organization;

    public function __construct(private readonly PosService $pos, private readonly IdempotencyService $idempotency) {}

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

    public function storeProduct(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'sku' => 'required|string|max:80',
            'barcode' => 'nullable|string|max:100',
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
            'sku' => 'sometimes|string|max:80',
            'barcode' => 'nullable|string|max:100',
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
            ->latest('created_at');

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
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
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

    public function completeOrder(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['items' => 'required|array|min:1', 'items.*.product_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1', 'customer_id' => 'nullable|integer', 'reservation_id' => 'nullable|integer', 'payment_method' => 'required|in:cash,qr,wallet', 'payment_reference' => 'nullable|string|max:160', 'discount_amount' => 'nullable|integer|min:0', 'offline' => 'nullable|boolean']);
        return $this->idempotency->run($request, fn () => response()->json(['order' => $this->pos->completeOrder($request->user(), $organization->id, $data)], 201));
    }

    public function openShift(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['opening_cash' => 'required|integer|min:0']);
        return $this->idempotency->run($request, fn () => response()->json(['shift' => $this->pos->openShift($request->user(), $organization->id, $data['opening_cash'])], 201));
    }

    public function closeShift(Request $request, CashShift $shift)
    {
        $organization = $this->organization($request);
        abort_unless($shift->organization_id === $organization->id, 404);
        $data = $request->validate(['declared_cash' => 'required|integer|min:0', 'note' => 'nullable|string|max:2000']);
        return $this->idempotency->run($request, fn () => response()->json(['shift' => $this->pos->closeShift($shift, $request->user(), $data['declared_cash'], $data['note'] ?? null)]));
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
            $endTime = $shift->closed_at ?: now();
            $orders = PosOrder::where('organization_id', $shift->organization_id)
                ->where('cashier_id', $shift->cashier_id)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$shift->opened_at, $endTime])
                ->get();

            $cashSales = (int) $orders->where('payment_method', 'cash')->sum('total_amount');
            $qrSales = (int) $orders->where('payment_method', 'qr')->sum('total_amount');
            $walletSales = (int) $orders->where('payment_method', 'wallet')->sum('total_amount');
            $totalSales = (int) $orders->sum('total_amount');

            $shift->cash_sales = $cashSales;
            $shift->qr_sales = $qrSales;
            $shift->wallet_sales = $walletSales;
            $shift->total_sales = $totalSales;
            $shift->orders_count = $orders->count();
            if ($shift->expected_cash == 0 && $shift->status === 'open') {
                $shift->expected_cash = $shift->opening_cash + $cashSales;
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

        if (!$shift) {
            $shift = CashShift::where('organization_id', $organization->id)
                ->where('status', 'open')
                ->with(['reconciliation.reviewer:id,name,email', 'cashier:id,name,email'])
                ->latest('opened_at')
                ->first();
        }

        if ($shift) {
            $orders = PosOrder::where('organization_id', $shift->organization_id)
                ->where('cashier_id', $shift->cashier_id)
                ->where('status', 'completed')
                ->where('created_at', '>=', $shift->opened_at)
                ->get();

            $cashSales = (int) $orders->where('payment_method', 'cash')->sum('total_amount');
            $qrSales = (int) $orders->where('payment_method', 'qr')->sum('total_amount');
            $walletSales = (int) $orders->where('payment_method', 'wallet')->sum('total_amount');
            $totalSales = (int) $orders->sum('total_amount');

            $shift->cash_sales = $cashSales;
            $shift->qr_sales = $qrSales;
            $shift->wallet_sales = $walletSales;
            $shift->total_sales = $totalSales;
            $shift->orders_count = $orders->count();
            $shift->expected_cash = $shift->opening_cash + $cashSales;
        }

        return response()->json(['shift' => $shift]);
    }

    public function reviewReconciliation(Request $request, CashShift $shift)
    {
        $organization = $this->organization($request);
        abort_unless($shift->organization_id === $organization->id, 404);
        $data = $request->validate(['status' => 'required|in:matched,approved,exception,rejected', 'note' => 'nullable|string|max:2000']);
        
        $reconciliation = $shift->reconciliation ?: new \App\Models\ShiftReconciliation([
            'organization_id' => $organization->id,
            'cash_shift_id' => $shift->id,
            'variance' => $shift->variance ?? 0,
        ]);

        $reconciliation->status = $data['status'];
        if (isset($data['note'])) {
            $reconciliation->note = ($reconciliation->note ? $reconciliation->note . " | " : "") . $data['note'];
        }
        $reconciliation->reviewed_by = $request->user()->id;
        $reconciliation->reviewed_at = now();
        $reconciliation->save();

        return response()->json(['reconciliation' => $reconciliation, 'shift' => $shift->fresh('reconciliation')]);
    }


    public function membershipPlans(Request $request)
    {
        $organization = $this->organization($request);
        return TenantMembershipPlan::where('organization_id', $organization->id)->latest('published_at')->get();
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

    public function wallet(Request $request)
    {
        $organization = $this->organization($request);
        return WalletAccount::firstOrCreate(['organization_id' => $organization->id, 'user_id' => $request->user()->id, 'currency' => 'VND'], ['balance' => 0, 'status' => 'active']);
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
