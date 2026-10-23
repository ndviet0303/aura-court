<?php

namespace App\Http\Controllers\POS;

use App\Contracts\Repositories\StockTransferRepositoryInterface;
use App\Domain\Inventory\Actions\TransferStock;
use App\Domain\Inventory\DTOs\TransferStockData;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\POS\StoreStockTransferRequest;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\POS\PosQrPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StockTransferController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly TransferStock $transferStock,
        private readonly StockTransferRepositoryInterface $transfers,
        private readonly PosQrPaymentService $posQr,
        private readonly IdempotencyService $idempotency,
    ) {}

    /** Transfers out of or into the venue read, chosen as for the rest of the inventory (pos-venue-inventory D7). */
    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $venueId = $this->posQr->stockVenueId($request->user(), $organization->id, $request->integer('venue_id') ?: null);

        return response()->json($venueId ? $this->transfers->forVenue($organization->id, $venueId) : []);
    }

    public function store(StoreStockTransferRequest $request): Response
    {
        $data = TransferStockData::fromRequest($request, $this->organization($request)->id);

        return $this->idempotency->run($request, fn () => response()->json([
            'message' => 'Đã điều chuyển hàng.',
            'transfer' => $this->transferStock->execute($data, $request->user()),
        ], 201));
    }
}
