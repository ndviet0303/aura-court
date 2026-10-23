<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PointLedger;
use App\Models\TenantCustomer;
use App\Models\TenantMembership;
use App\Models\WalletAccount;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    // Tenant comes only from ResolveTenantContext, which rejects non-members of the requested organization.
    private function resolveOrganization(): Organization
    {
        return $this->tenantContext->getOrganization() ?? abort(400, 'Organization context required.');
    }

    public function index(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization();

        $query = TenantCustomer::where('organization_id', $org->id)->with('user');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ilike', "%{$q}%")
                    ->orWhere('phone', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%");
            });
        }

        if ($request->has('is_guest')) {
            $query->where('is_guest', $request->boolean('is_guest'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $customers = $query->orderBy('created_at', 'desc')->get()->map(function (TenantCustomer $tc) use ($org) {
            $walletBalance = 0;
            $loyaltyPoints = 0;
            $tier = 'Standard';
            $discountRate = 0.0;

            if (! $tc->is_guest && $tc->user_id !== null) {
                $wallet = WalletAccount::where('organization_id', $org->id)
                    ->where('user_id', $tc->user_id)
                    ->first();
                $walletBalance = $wallet ? (int) $wallet->balance : 0;

                $lastPoint = PointLedger::where('organization_id', $org->id)
                    ->where('user_id', $tc->user_id)
                    ->latest('id')
                    ->first();
                $loyaltyPoints = $lastPoint ? (int) $lastPoint->balance_after : 0;

                $membership = TenantMembership::where('organization_id', $org->id)
                    ->where('user_id', $tc->user_id)
                    ->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now())
                    ->with('plan')
                    ->latest('ends_at')
                    ->first();

                if ($membership && $membership->plan) {
                    $tier = $membership->plan->name;
                    $discountPercent = (int) ($membership->plan->benefits['discount_percent'] ?? 0);
                    $discountRate = round($discountPercent / 100, 2);
                }
            }

            return [
                'id' => $tc->user_id ?? $tc->id,
                'tenant_customer_id' => $tc->id,
                'user_id' => $tc->user_id,
                'name' => $tc->name,
                'phone' => $tc->phone ?? '',
                'email' => $tc->email ?? '',
                'is_guest' => $tc->is_guest,
                'tier' => $tier,
                'discount_rate' => $discountRate,
                'wallet_balance' => $walletBalance,
                'loyalty_points' => $loyaltyPoints,
                'status' => $tc->status,
                'notes' => $tc->notes,
                'created_at' => $tc->created_at,
            ];
        });

        return response()->json([
            'organization_id' => $org->id,
            'customers' => $customers,
        ]);
    }
}
