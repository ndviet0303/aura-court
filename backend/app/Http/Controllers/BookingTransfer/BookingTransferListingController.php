<?php

namespace App\Http\Controllers\BookingTransfer;

use App\Contracts\Repositories\BookingTransferListingRepositoryInterface;
use App\Domain\BookingTransfer\Actions\ContactTransferListing;
use App\Domain\BookingTransfer\Actions\PostTransferListing;
use App\Domain\BookingTransfer\Actions\WithdrawTransferListing;
use App\Domain\BookingTransfer\DTOs\TransferListingData;
use App\Domain\BookingTransfer\Rules\TransferEligibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingTransfer\SearchTransferListingsRequest;
use App\Http\Requests\BookingTransfer\StoreTransferListingRequest;
use App\Http\Requests\BookingTransfer\TransferReservationsRequest;
use App\Http\Resources\BookingTransfer\TransferCandidateResource;
use App\Http\Resources\BookingTransfer\TransferListingResource;
use App\Models\BookingTransferListing;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Module 08.2 UC-9.1..UC-9.6: choosing bookings, posting, the shared market, withdrawing and contacting. */
class BookingTransferListingController extends Controller
{
    public function __construct(private readonly BookingTransferListingRepositoryInterface $listings) {}

    public function candidates(Request $request, TransferEligibility $eligibility): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $this->listings->candidates((int) $user->id)->map(fn (Reservation $reservation) => new TransferCandidateResource(
            $reservation,
            $eligibility->refusal($reservation, $user, listed: true),
            $eligibility->maxPrice($reservation),
        ))->values()]);
    }

    public function index(SearchTransferListingsRequest $request): JsonResponse
    {
        $page = $this->listings->search($request->validated('q'), 20);

        return response()->json([
            'data' => array_map(fn (BookingTransferListing $listing) => new TransferListingResource($listing), $page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(StoreTransferListingRequest $request, PostTransferListing $action): JsonResponse
    {
        $listing = $action->execute($request->user(), TransferListingData::from($request));

        return response()->json(['listing' => new TransferListingResource($this->listings->withDetails($listing), [])], 201);
    }

    public function show(Request $request, BookingTransferListing $listing): JsonResponse
    {
        $mine = (int) $listing->seller_id === (int) $request->user()->id;

        return response()->json(['listing' => new TransferListingResource($this->listings->withDetails($listing), $mine ? $this->listings->contacts($listing) : null)]);
    }

    public function withdraw(TransferReservationsRequest $request, BookingTransferListing $listing, WithdrawTransferListing $action): JsonResponse
    {
        $listing = $action->execute($listing, $request->user(), $request->reservationIds());

        return response()->json(['listing' => new TransferListingResource($listing, $this->listings->contacts($listing))]);
    }

    public function contact(TransferReservationsRequest $request, BookingTransferListing $listing, ContactTransferListing $action): JsonResponse
    {
        $conversation = $action->execute($listing, $request->user(), (array) $request->reservationIds());

        return response()->json(['conversation_id' => (int) $conversation->id], 201);
    }
}
