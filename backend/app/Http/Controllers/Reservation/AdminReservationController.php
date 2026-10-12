<?php

namespace App\Http\Controllers\Reservation;

use App\Domain\Reservation\Actions\ListAdminReservations;
use App\Domain\Reservation\DTOs\AdminReservationFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\ListAdminReservationsRequest;

class AdminReservationController extends Controller
{
    public function __invoke(ListAdminReservationsRequest $request, ListAdminReservations $action)
    {
        return $action->execute(AdminReservationFilterData::from($request), $request->user());
    }
}
