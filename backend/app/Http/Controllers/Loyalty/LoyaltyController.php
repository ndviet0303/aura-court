<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Services\Loyalty\LoyaltyPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyPolicyService $loyaltyPolicyService
    ) {}

    /**
     * Read-only preview endpoint for booking loyalty point redemption.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $data = $request->validate([
            'quote_token' => 'nullable|string|max:80',
            'court_id' => 'required_without:quote_token|integer',
            'slots' => 'required_without:quote_token|array|min:1',
            'slots.*' => 'string',
            'points' => 'nullable|integer|min:1',
            'coupon_code' => 'nullable|string|max:50',
            'expected_policy_version' => 'nullable|integer',
        ]);

        $preview = $this->loyaltyPolicyService->preview($user, $data);

        return response()->json($preview);
    }

    /**
     * Rejection of debit-only redemption in favor of atomic quote/booking checkout.
     */
    public function redeem(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Quy đổi điểm trực tiếp đã ngừng hỗ trợ. Vui lòng áp dụng điểm khi xác nhận đặt sân hoặc thanh toán hóa đơn.',
            'error_code' => 'DIRECT_REDEMPTION_RETIRED',
            'code' => 'DIRECT_REDEMPTION_RETIRED',
            'details' => [
                'reason' => 'Direct debit redemption is retired to prevent uncredited point loss. Use loyalty preview and checkout integration instead.',
            ],
        ], 422);
    }
}
