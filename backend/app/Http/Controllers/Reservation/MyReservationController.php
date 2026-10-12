<?php

namespace App\Http\Controllers\Reservation;

use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MyReservationController extends Controller
{
    public function __invoke(Request $request, ReservationRepositoryInterface $reservations)
    {
        return $reservations->paginateUserReservations((int) $request->user()->id);
    }
}
