<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\SplitGroup;
use App\Models\SplitShare;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Payment\SplitPaymentService;
use Illuminate\Http\Request;

class AdvancedBookingController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly SplitPaymentService $splits,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function createSplit(Request $request, Reservation $reservation)
    {
        $organization = $this->organization($request);
        abort_unless($reservation->organization_id === $organization->id && $reservation->user_id === $request->user()->id, 404);
        $data = $request->validate(['shares' => 'required|array|min:2|max:8', 'shares.*.amount' => 'required|integer|min:1']);

        return $this->idempotency->run($request, fn () => response()->json(['split' => $this->splits->create($reservation, $request->user(), $data['shares'])], 201));
    }

    public function split(Request $request, SplitGroup $split)
    {
        $organization = $this->organization($request);
        abort_unless($split->organization_id === $organization->id, 404);

        return response()->json(['split' => $split->load('shares', 'reservation')]);
    }

    public function paySplitShare(Request $request, SplitShare $share)
    {
        $organization = $this->organization($request);
        abort_unless($share->organization_id === $organization->id, 404);
        $data = $request->validate(['amount' => 'required|integer|min:1', 'payment_reference' => 'required|string|max:160']);

        return $this->idempotency->run($request, fn () => response()->json(['split' => $this->splits->payShare($share, $request->user(), $data['amount'], $data['payment_reference'])]));
    }

    public function coverSplit(Request $request, SplitGroup $split)
    {
        $organization = $this->organization($request);
        abort_unless($split->organization_id === $organization->id, 404);
        $data = $request->validate(['payment_reference' => 'nullable|string|max:160']);

        return $this->idempotency->run($request, fn () => response()->json(['split' => $this->splits->coverShortfall($split, $request->user(), $data['payment_reference'] ?? null)]));
    }
}
