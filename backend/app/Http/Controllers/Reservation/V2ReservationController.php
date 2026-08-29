<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\Request;

class V2ReservationController extends Controller
{
    use ResolvesV2Organization;

    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly ReservationService $reservations,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(Request $request)
    {
        $organization = $this->organization($request);
        $query = Reservation::query()
            ->where('organization_id', $organization->id)
            ->with(['court.venue', 'quote', 'slots', 'entitlements'])
            ->latest();
        if (! $this->isOrganizationOperator($request, $organization)) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->paginate(20);
    }

    public function show(Request $request, string $reservation)
    {
        $organization = $this->organization($request);
        $record = $this->findReservation($request, $organization->id, $reservation);

        return response()->json(['reservation' => $record->load(['court.venue', 'quote', 'slots', 'entitlements'])]);
    }

    public function quote(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'court_id' => 'required|integer',
            'slots' => 'required|array|min:1|max:24',
            'slots.*' => 'required',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $quote = $this->quotes->create($request->user()->id, $organization->id, (int) $data['court_id'], $data['slots']);

            return response()->json(['quote' => $quote], 201);
        });
    }

    public function store(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'quote_id' => 'nullable|integer',
            'quote_token' => 'nullable|string|max:80',
            'court_id' => 'required_without_all:quote_id,quote_token|integer',
            'slots' => 'required_without_all:quote_id,quote_token|array|min:1|max:24',
            'slots.*' => 'required',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $reservation = $this->reservations->hold($request->user(), $organization->id, $data);

            return response()->json(['reservation' => $reservation], 201);
        });
    }

    public function confirm(Request $request, string $reservation)
    {
        $organization = $this->organization($request);
        $record = $this->findReservation($request, $organization->id, $reservation);

        return $this->idempotency->run($request, function () use ($record) {
            return response()->json(['reservation' => $this->reservations->confirm($record)]);
        });
    }

    public function cancel(Request $request, string $reservation)
    {
        $organization = $this->organization($request);
        $record = $this->findReservation($request, $organization->id, $reservation);

        return $this->idempotency->run($request, function () use ($record) {
            return response()->json(['reservation' => $this->reservations->cancel($record)]);
        });
    }

    public function pricingCalendar(Request $request)
    {
        $organization = $this->organization($request);
        $query = PriceRule::query()->where('organization_id', $organization->id)->where('is_active', true)->with(['venue', 'court']);
        if ($request->filled('court_id')) {
            $query->where(function ($rules) use ($request) {
                $rules->whereNull('court_id')->orWhere('court_id', (int) $request->integer('court_id'));
            });
        }

        return response()->json(['rules' => $query->orderBy('type')->orderBy('priority', 'desc')->get()]);
    }

    private function findReservation(Request $request, int $organizationId, string $identifier): Reservation
    {
        $query = Reservation::query()->where('organization_id', $organizationId);
        if (ctype_digit($identifier)) {
            $query->whereKey((int) $identifier);
        } else {
            $query->where('code', $identifier);
        }
        if (! $this->isOrganizationOperator($request, $this->organization($request))) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->firstOrFail();
    }
}
