<?php

namespace App\Http\Controllers\Pricing;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Domain\Pricing\Actions\ChangeCourtPriceTableLinks;
use App\Domain\Pricing\Actions\CreatePriceTable;
use App\Domain\Pricing\Actions\PublishPriceTableVersion;
use App\Domain\Pricing\Actions\RetirePriceTable;
use App\Domain\Pricing\DTOs\CreatePriceTableData;
use App\Domain\Pricing\DTOs\PriceTableLinkChange;
use App\Domain\Pricing\DTOs\PublishPriceTableVersionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\ApplyPriceTableLinksRequest;
use App\Http\Requests\Pricing\PublishPriceTableVersionRequest;
use App\Http\Requests\Pricing\StorePriceTableRequest;
use App\Http\Resources\Pricing\PriceTableResource;
use App\Models\PriceTable;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Price tables of a venue (design D1, D2); the routes are owner-only and tables are never edited or deleted. */
class PriceTableController extends Controller
{
    public function __construct(private readonly PriceTableRepositoryInterface $priceTables) {}

    public function index(Request $request, Venue $venue): JsonResponse
    {
        $this->authorizeVenue($request, (int) $venue->id);

        return response()->json(['data' => PriceTableResource::collection($this->priceTables->forVenue((int) $venue->id))]);
    }

    public function store(StorePriceTableRequest $request, Venue $venue, CreatePriceTable $action): JsonResponse
    {
        $table = $action->execute(CreatePriceTableData::from($request));

        return response()->json(['data' => PriceTableResource::make($this->priceTables->loadDetail($table))], 201);
    }

    public function show(Request $request, PriceTable $table): JsonResponse
    {
        $this->authorizeVenue($request, (int) $table->venue_id);

        return response()->json(['data' => PriceTableResource::make($this->priceTables->loadDetail($table))]);
    }

    public function publishVersion(PublishPriceTableVersionRequest $request, PriceTable $table, PublishPriceTableVersion $action): JsonResponse
    {
        $table = $action->execute(PublishPriceTableVersionData::from($request));

        return response()->json(['data' => PriceTableResource::make($this->priceTables->loadDetail($table))], 201);
    }

    public function applyLinks(ApplyPriceTableLinksRequest $request, PriceTable $table, ChangeCourtPriceTableLinks $action): JsonResponse
    {
        $action->execute(PriceTableLinkChange::apply($table, $request->validated('court_ids'), $request->date('starts_at')));

        return response()->json(['data' => PriceTableResource::make($this->priceTables->loadDetail($table))]);
    }

    public function retire(Request $request, PriceTable $table, RetirePriceTable $action): JsonResponse
    {
        $this->authorizeVenue($request, (int) $table->venue_id);

        return response()->json(['data' => PriceTableResource::make($this->priceTables->loadDetail($action->execute($table)))]);
    }

    /** D25: only the owner of the venue's organization; staff and other organizations get 403. */
    private function authorizeVenue(Request $request, int $venueId): void
    {
        abort_unless($request->user()->canManageVenue($venueId), 403, 'Bạn không có quyền quản lý bảng giá của cơ sở này.');
    }
}
