<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\SplitGroup;
use App\Models\SplitShare;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Payment\SplitHostReservations;
use App\Services\Payment\SplitPaymentService;
use Illuminate\Http\Request;

class AdvancedBookingController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly SplitPaymentService $splits,
        private readonly IdempotencyService $idempotency,
        private readonly SplitHostReservations $hosts,
    ) {}

    public function reservationSplit(Request $request, string $reservation)
    {
        $record = $this->hosts->find($request->user(), $reservation);

        return response()->json([
            'reservation' => $record->only(['id', 'code', 'status', 'total_amount', 'hold_expires_at']),
            'split' => $this->hosts->group($record),
        ]);
    }

    public function createSplit(Request $request, string $reservation)
    {
        $record = $this->hosts->find($request->user(), $reservation);
        $data = $request->validate(['shares' => 'required|array|min:2|max:8', 'shares.*.amount' => 'required|integer|min:1']);

        return $this->idempotency->run($request, fn () => response()->json(['split' => $this->hosts->create($record, $request->user(), $data['shares'])], 201));
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
