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
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    private function resolveOrganization(Request $request): Organization
    {
        $organization = $this->tenantContext->getOrganization();

        if (! $organization && $request->header('X-Organization-ID')) {
            $organization = Organization::find((int) $request->header('X-Organization-ID'));
        }

        if (! $organization) {
            abort(400, 'Organization context required.');
        }

        return $organization;
    }

    public function index(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

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

    public function store(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:160',
            'user_id' => 'nullable|integer|exists:users,id',
            'is_guest' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $userId = $validated['user_id'] ?? null;
        $phone = $validated['phone'] ?? null;

        // Uniqueness within tenant: user_id
        if ($userId !== null) {
            $exists = TenantCustomer::where('organization_id', $org->id)
                ->where('user_id', $userId)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'user_id' => ['User is already registered as a customer for this organization.'],
                ]);
            }
        }

        // Uniqueness within tenant: phone
        if ($phone !== null && trim($phone) !== '') {
            $exists = TenantCustomer::where('organization_id', $org->id)
                ->where('phone', trim($phone))
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => ['A customer with this phone number already exists in this organization.'],
                ]);
            }
        }

        $isGuest = $validated['is_guest'] ?? ($userId === null);

        $customer = TenantCustomer::create([
            'organization_id' => $org->id,
            'user_id' => $userId,
            'is_guest' => $isGuest,
            'name' => $validated['name'],
            'phone' => $phone ? trim($phone) : null,
            'email' => $validated['email'] ?? null,
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'customer' => $customer->load('user'),
        ], 201);
    }

    public function show(Request $request, TenantCustomer $customer): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if ($customer->organization_id !== $org->id) {
            abort(404, 'Customer not found.');
        }

        return response()->json([
            'customer' => $customer->load('user'),
        ]);
    }
}
