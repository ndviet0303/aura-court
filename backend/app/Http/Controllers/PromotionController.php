<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function preview(Request $request, DiscountService $discounts)
    {
        $data = $request->validate(['subtotal' => 'required|integer|min:0', 'code' => 'nullable|string|max:40']);

        return $discounts->preview($request->user(), $data['subtotal'], $data['code'] ?? null);
    }

    public function membership(Request $request)
    {
        return ['membership' => $request->user()->memberships()->with('plan')->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>=', now())->latest('ends_at')->first()];
    }

    public function coupons()
    {
        return Coupon::latest()->paginate(30);
    }

    public function storeCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:coupons,code',
            'discount_type' => ['required', Rule::in(['fixed', 'percent'])],
            'discount_value' => 'required|integer|min:1',
            'max_discount' => 'nullable|integer|min:1',
            'min_spend' => 'nullable|integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);
        $data['code'] = strtoupper(trim($data['code']));
        if ($data['discount_type'] === 'percent' && $data['discount_value'] > 100) {
            abort(422, 'Giảm theo phần trăm không được vượt quá 100%.');
        }

        return response()->json(Coupon::create($data), 201);
    }

    public function plans()
    {
        return MembershipPlan::where('is_active', true)->orderBy('discount_percent', 'desc')->get();
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80|unique:membership_plans,name', 'description' => 'nullable|string', 'discount_percent' => 'required|integer|min:0|max:100', 'is_active' => 'boolean']);

        return response()->json(MembershipPlan::create($data), 201);
    }

    public function assignMembership(Request $request)
    {
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'membership_plan_id' => 'required|integer|exists:membership_plans,id', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at']);
        Membership::where('user_id', $data['user_id'])->where('status', 'active')->update(['status' => 'replaced']);

        return response()->json(Membership::create($data + ['status' => 'active'])->load(['user:id,name,email', 'plan']), 201);
    }
}
