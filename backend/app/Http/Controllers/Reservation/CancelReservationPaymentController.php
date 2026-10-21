<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Reservation\CustomerPaymentCancel;
use App\Services\Reservation\VisibleReservations;
use Illuminate\Http\Request;

/** The customer cancels their own booking while it is still pending payment; another visible booking is 403. */
class CancelReservationPaymentController extends Controller
{
    use ResolvesOrganization;

    public function __invoke(
        Request $request,
        string $reservation,
        VisibleReservations $visible,
        CustomerPaymentCancel $cancellation,
        IdempotencyService $idempotency
    ) {
        $record = $visible->find($request->user(), $this->organization($request), $reservation);

        return $idempotency->run($request, fn () => response()->json([
            'reservation' => $cancellation->cancel($record, $request->user()),
        ]));
    }
}
