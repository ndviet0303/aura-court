<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\LockResaleListingRequest;
use App\Http\Requests\Booking\ResaleListingActionRequest;
use App\Http\Requests\Booking\StoreResaleListingRequest;
use App\Models\MarketplaceListing;
use App\Models\ReservationEntitlement;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Social\MarketplaceService;
use App\Services\Social\PeerToPeerResaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Peer-to-peer resale: list, lock, report payment, release and confirm receipt. */
class ResaleListingController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly MarketplaceService $marketplace,
        private readonly PeerToPeerResaleService $resale,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function store(StoreResaleListingRequest $request, ReservationEntitlement $entitlement): Response
    {
        $organization = $this->organization($request);
        abort_unless($entitlement->organization_id === $organization->id && $entitlement->user_id === $request->user()->id, 404);
        $data = $request->validated();

        return $this->idempotency->run($request, fn () => response()->json([
            'listing' => $this->marketplace->list($entitlement, $request->user(), (int) $data['asking_price'], $data['seller_receiver'] ?? null),
        ], 201));
    }

    public function show(Request $request, MarketplaceListing $listing): JsonResponse
    {
        $this->assertTenant($request, $listing);

        return response()->json($this->resale->state($listing, $request->user()));
    }

    public function lock(LockResaleListingRequest $request, MarketplaceListing $listing): Response
    {
        $this->assertTenant($request, $listing);

        return $this->idempotency->run($request, fn () => response()->json(
            $this->resale->lock($listing, $request->user(), $request->boolean('accept_free_transfer'))
        ));
    }

    public function paymentSent(ResaleListingActionRequest $request, MarketplaceListing $listing): Response
    {
        $this->assertTenant($request, $listing);

        return $this->idempotency->run($request, fn () => response()->json($this->resale->paymentSent($listing, $request->user())));
    }

    public function release(ResaleListingActionRequest $request, MarketplaceListing $listing): Response
    {
        $this->assertTenant($request, $listing);

        return $this->idempotency->run($request, fn () => response()->json(
            $this->resale->release($listing, $request->user(), $request->validated('reason'))
        ));
    }

    public function confirmReceipt(ResaleListingActionRequest $request, MarketplaceListing $listing): Response
    {
        $this->assertTenant($request, $listing);

        return $this->idempotency->run($request, fn () => response()->json($this->resale->confirmReceipt($listing, $request->user())));
    }

    private function assertTenant(Request $request, MarketplaceListing $listing): void
    {
        abort_unless($listing->organization_id === $this->organization($request)->id, 404);
    }
}
