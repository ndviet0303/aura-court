<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    /**
     * Points are no longer spent anywhere: court bookings do not redeem them (design D21) and a debit-only
     * redemption would lose points without credit.
     */
    public function redeem(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Quy đổi điểm trực tiếp đã ngừng hỗ trợ. Đặt sân không còn dùng điểm; điểm vẫn được tích khi mua hàng tại quầy.',
            'error_code' => 'DIRECT_REDEMPTION_RETIRED',
            'code' => 'DIRECT_REDEMPTION_RETIRED',
            'details' => [
                'reason' => 'Direct debit redemption is retired to prevent uncredited point loss. Court bookings no longer redeem points; points are still earned at POS.',
            ],
        ], 422);
    }
}
