<?php

namespace App\Services;

use App\Models\CashShift;
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
    public function __construct(private readonly FinancialPostingService $financials) {}

    public function createProduct(int $organizationId, array $data): PosProduct
    {
        return PosProduct::create(['organization_id' => $organizationId, 'sku' => $data['sku'], 'barcode' => $data['barcode'] ?? null, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'price' => $data['price'], 'cost' => $data['cost'] ?? 0, 'stock_quantity' => 0, 'metadata' => $data['metadata'] ?? []]);
    }

    public function adjustStock(PosProduct $product, int $delta, User $actor, string $type = 'adjustment', ?string $reference = null): PosProduct
    {
        return DB::transaction(function () use ($product, $delta, $actor, $type, $reference): PosProduct {
            $product = PosProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($product->stock_quantity + $delta >= 0, 409, 'Insufficient stock.');
            $product->increment('stock_quantity', $delta);
            StockMovement::create(['organization_id' => $product->organization_id, 'product_id' => $product->id, 'type' => $type, 'quantity_delta' => $delta, 'unit_cost' => $product->cost, 'reference_type' => 'pos', 'reference_id' => null, 'created_by' => $actor->id, 'metadata' => ['reference' => $reference]]);
            return $product->fresh();
        });
    }

    public function completeOrder(User $cashier, int $organizationId, array $data): PosOrder
    {
        abort_if((bool) ($data['offline'] ?? false), 409, 'Offline POS drafts cannot finalize payment or inventory.');
        $method = (string) ($data['payment_method'] ?? '');
        abort_unless(in_array($method, ['cash', 'qr', 'wallet'], true), 422, 'Unsupported POS payment method.');
        return DB::transaction(function () use ($cashier, $organizationId, $data, $method): PosOrder {
            $items = $data['items'] ?? [];
            abort_if($items === [], 422, 'POS order requires at least one item.');
            $subtotal = 0;
            $snapshots = [];
            foreach ($items as $item) {
                $product = PosProduct::query()->where('organization_id', $organizationId)->whereKey((int) $item['product_id'])->lockForUpdate()->firstOrFail();
                $quantity = max(1, (int) $item['quantity']);
                abort_unless($product->stock_quantity >= $quantity, 409, "Insufficient stock for {$product->sku}.");
                $lineTotal = $product->price * $quantity;
                $subtotal += $lineTotal;
                $snapshots[] = compact('product', 'quantity', 'lineTotal');
            }
            $discount = (int) ($data['discount_amount'] ?? 0);
            if (! empty($data['customer_id'])) {
                $membership = TenantMembership::query()->where('organization_id', $organizationId)->where('user_id', (int) $data['customer_id'])->where('status', 'active')->where('ends_at', '>', now())->with('plan')->latest('ends_at')->first();
                $membershipDiscount = (int) ($membership?->plan?->benefits['discount_percent'] ?? 0);
                if ($membershipDiscount > 0) $discount += intdiv($subtotal * $membershipDiscount, 100);
            }
            $total = max(0, $subtotal - $discount);
            abort_unless($total > 0, 422, 'POS order total must be greater than zero.');
            $order = PosOrder::create(['organization_id' => $organizationId, 'cart_id' => $data['cart_id'] ?? null, 'customer_id' => $data['customer_id'] ?? null, 'cashier_id' => $cashier->id, 'reservation_id' => $data['reservation_id'] ?? null, 'status' => 'completed', 'payment_method' => $method, 'subtotal' => $subtotal, 'discount_amount' => $discount, 'total_amount' => $total, 'payment_reference' => $data['payment_reference'] ?? "pos-{$cashier->id}-".uniqid(), 'completed_at' => now()]);
            if ($method === 'wallet') {
                $this->spendWallet($organizationId, (int) ($data['customer_id'] ?? $cashier->id), $total, "pos:{$order->id}");
            }
            foreach ($snapshots as $snapshot) {
                $product = $snapshot['product'];
                $product->decrement('stock_quantity', $snapshot['quantity']);
                $order->items()->create(['organization_id' => $organizationId, 'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'quantity' => $snapshot['quantity'], 'unit_price' => $product->price, 'line_total' => $snapshot['lineTotal']]);
                StockMovement::create(['organization_id' => $organizationId, 'product_id' => $product->id, 'type' => 'sale', 'quantity_delta' => -$snapshot['quantity'], 'unit_cost' => $product->cost, 'reference_type' => PosOrder::class, 'reference_id' => $order->id, 'created_by' => $cashier->id]);
            }
            $order->payments()->create(['organization_id' => $organizationId, 'method' => $method, 'amount' => $total, 'status' => 'paid', 'provider_reference' => $data['payment_reference'] ?? null]);
            if ($method !== 'wallet') {
                $this->financials->postPosSale($organizationId, $order->id, $total, $method, $order->payment_reference);
            }
            if (LoyaltyRule::where('organization_id', $organizationId)->where('status', 'published')->exists() && $order->customer_id) {
                $this->awardPoints($organizationId, $order->customer_id, $total, PosOrder::class, $order->id);
            }
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
            $balance = (int) PointLedger::where('organization_id', $organizationId)->where('user_id', $userId)->lockForUpdate()->sum('delta');
            abort_unless($balance + $delta >= 0, 409, 'Loyalty points cannot become negative.');
            return PointLedger::create(['organization_id' => $organizationId, 'user_id' => $userId, 'loyalty_rule_id' => $ruleId, 'delta' => $delta, 'balance_after' => $balance + $delta, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        });
    }
}
