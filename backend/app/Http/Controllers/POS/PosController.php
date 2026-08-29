<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\CashShift;
use App\Models\LoyaltyRule;
use App\Models\PosProduct;
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
        return PosProduct::where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get();
    }

    public function storeProduct(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['sku' => 'required|string|max:80', 'barcode' => 'nullable|string|max:100', 'name' => 'required|string|max:180', 'description' => 'nullable|string', 'price' => 'required|integer|min:0', 'cost' => 'nullable|integer|min:0']);
        return $this->idempotency->run($request, fn () => response()->json(['product' => $this->pos->createProduct($organization->id, $data)], 201));
    }

    public function adjustStock(Request $request, PosProduct $product)
    {
        $organization = $this->organization($request);
        abort_unless($product->organization_id === $organization->id, 404);
        $data = $request->validate(['quantity_delta' => 'required|integer|not_in:0', 'type' => 'nullable|in:receipt,adjustment']);
        return $this->idempotency->run($request, fn () => response()->json(['product' => $this->pos->adjustStock($product, (int) $data['quantity_delta'], $request->user(), $data['type'] ?? 'adjustment')]));
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
            ->with(['reconciliation', 'cashier:id,name,email'])
            ->latest('opened_at');

        if ($request->has('status') && in_array($request->status, ['open', 'closed'])) {
            $query->where('status', $request->status);
        }

        return $query->paginate((int) ($request->get('per_page', 20)));
    }

    public function activeShift(Request $request)
    {
        $organization = $this->organization($request);
        $shift = CashShift::where('organization_id', $organization->id)
            ->where('cashier_id', $request->user()->id)
            ->where('status', 'open')
            ->with(['cashier:id,name,email'])
            ->latest('opened_at')
            ->first();

        if (!$shift) {
            $shift = CashShift::where('organization_id', $organization->id)
                ->where('status', 'open')
                ->with(['cashier:id,name,email'])
                ->latest('opened_at')
                ->first();
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
