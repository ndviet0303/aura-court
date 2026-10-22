<?php

namespace App\Http\Controllers\Promotion;

use App\Domain\Promotion\DTOs\VoucherQuoteData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\CheckVoucherCodeRequest;
use App\Http\Requests\Promotion\ListVenueVouchersRequest;
use App\Http\Resources\Promotion\VoucherQuoteResource;
use App\Models\Venue;
use App\Services\Promotion\VoucherChoiceService;
use Illuminate\Http\JsonResponse;

/**
 * The vouchers a booking form offers for a venue's court interval (design D10, D20); public, and neither route holds
 * or consumes a use. $venue is bound so an unknown venue is 404; the requests keep court_id within it.
 */
class VoucherChoiceController extends Controller
{
    public function __construct(private readonly VoucherChoiceService $choices) {}

    public function index(ListVenueVouchersRequest $request, Venue $venue): JsonResponse
    {
        $choice = $this->choices->applicable(VoucherQuoteData::from($request));

        return response()->json([
            'data' => VoucherQuoteResource::collection($choice['vouchers']),
            'rental' => $choice['rental'],
            'default_program_id' => $choice['vouchers'] === [] ? null : $choice['vouchers'][0]->snapshot['voucher']['program_id'],
        ]);
    }

    /** A code that does not apply propagates VoucherNoLongerApplies, which renders the shared 422 VOUCHER_INVALID. */
    public function check(CheckVoucherCodeRequest $request, Venue $venue): JsonResponse
    {
        return response()->json(['data' => VoucherQuoteResource::make($this->choices->check(VoucherQuoteData::from($request)))]);
    }
}
