<?php

namespace App\Http\Controllers\BookingTransfer;

use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Domain\BookingTransfer\Actions\CancelBookingTransfer;
use App\Domain\BookingTransfer\Actions\ConfirmBookingTransfer;
use App\Domain\BookingTransfer\Actions\CreateBookingTransfer;
use App\Domain\BookingTransfer\DTOs\BookingTransferData;
use App\Domain\BookingTransfer\Rules\TransferEligibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingTransfer\StoreBookingTransferRequest;
use App\Http\Resources\BookingTransfer\BookingTransferResource;
use App\Models\BookingTransfer;
use App\Models\BookingTransferItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Module 08.2 UC-9.7..UC-9.9: a transfer to one buyer, seen by both parties, confirmed by the seller. */
class BookingTransferController extends Controller
{
    public function __construct(
        private readonly BookingTransferRepositoryInterface $transfers,
        private readonly TransferEligibility $eligibility,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->transfers->forUser((int) $request->user()->id)
            ->map(fn (BookingTransfer $transfer) => new BookingTransferResource($transfer))->values()]);
    }

    public function store(StoreBookingTransferRequest $request, CreateBookingTransfer $action): JsonResponse
    {
        return response()->json(['transfer' => $this->present($action->execute($request->user(), BookingTransferData::from($request)))], 201);
    }

    public function show(Request $request, BookingTransfer $transfer): JsonResponse
    {
        if (! $transfer->isParty($request->user())) {
            TransferEligibility::fail('TRANSFER_FORBIDDEN', 'Bạn không tham gia giao dịch chuyển nhượng này.', 403);
        }

        return response()->json(['transfer' => $this->present($this->transfers->withDetails($transfer))]);
    }

    public function confirm(Request $request, BookingTransfer $transfer, ConfirmBookingTransfer $action): JsonResponse
    {
        return response()->json(['transfer' => $this->present($action->execute($transfer, $request->user()))]);
    }

    public function cancel(Request $request, BookingTransfer $transfer, CancelBookingTransfer $action): JsonResponse
    {
        return response()->json(['transfer' => $this->present($action->execute($transfer, $request->user()))]);
    }

    /** FR-145: a pending booking is checked again as the seller would confirm it, so a refusal shows beforehand. */
    private function present(BookingTransfer $transfer): BookingTransferResource
    {
        $refusals = [];
        if ($transfer->status === BookingTransfer::STATUS_PENDING) {
            foreach ($transfer->items as $item) {
                /** @var BookingTransferItem $item */
                $refusals[$item->reservation_id] = $this->eligibility->refusal($item->reservation, $transfer->seller, (int) $item->price);
            }
        }

        return new BookingTransferResource($transfer, $refusals);
    }
}
