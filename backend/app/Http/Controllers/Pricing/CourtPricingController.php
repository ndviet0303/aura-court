<?php

namespace App\Http\Controllers\Pricing;

use App\Domain\Pricing\Actions\ChangeCourtPriceTableLinks;
use App\Domain\Pricing\DTOs\PriceTableLinkChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\EndCourtPriceTableLinkRequest;
use App\Models\Court;
use App\Services\Pricing\PriceCoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A court's price links and coverage on the court screen (design D2, D3); owner-only routes. */
class CourtPricingController extends Controller
{
    public function endLink(EndCourtPriceTableLinkRequest $request, Court $court, ChangeCourtPriceTableLinks $action): JsonResponse
    {
        $kind = (string) $request->validated('kind');
        $endsAt = $action->execute(PriceTableLinkChange::end([(int) $court->id], $kind, $request->date('ends_at')));

        return response()->json(['data' => ['court_id' => (int) $court->id, 'kind' => $kind, 'ends_at' => $endsAt->utc()->toIso8601String()]]);
    }

    public function coverage(Request $request, Court $court, PriceCoverageService $coverage): JsonResponse
    {
        // D25: only the owner of the court's organization; staff and other organizations get 403.
        abort_unless($request->user()->canManageVenue((int) $court->venue_id), 403, 'Bạn không có quyền xem độ phủ giá của sân này.');
        $missing = $coverage->missingItems($court, now());

        return response()->json(['data' => ['court_id' => (int) $court->id, 'covered' => $missing === [], 'missing_items' => $missing]]);
    }
}
