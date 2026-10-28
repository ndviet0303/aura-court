<?php

namespace App\Http\Controllers\Booking;

use App\Domain\Booking\Actions\CheckRecurringBookings;
use App\Domain\Booking\Actions\SubmitRecurringBookings;
use App\Domain\Booking\DTOs\RecurringBookingData;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CheckRecurringBookingsRequest;
use App\Http\Requests\Booking\StoreRecurringBookingsRequest;
use App\Http\Resources\Booking\RecurringOccurrenceResource;
use App\Services\Infrastructure\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** Module 08: a recurring booking is checked and submitted occurrence by occurrence through the ordinary booking flow. */
class RecurringBookingController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function check(CheckRecurringBookingsRequest $request, CheckRecurringBookings $action): JsonResponse
    {
        $rows = $action->execute($request->user(), $this->organization($request)->id, RecurringBookingData::from($request));

        return response()->json(['data' => RecurringOccurrenceResource::collection($rows)->toArray($request)]);
    }

    public function store(StoreRecurringBookingsRequest $request, SubmitRecurringBookings $action): Response
    {
        $organizationId = $this->organization($request)->id;
        $data = RecurringBookingData::from($request);

        return $this->idempotency->run($request, fn () => response()->json([
            'data' => RecurringOccurrenceResource::collection($action->execute($request->user(), $organizationId, $data))->toArray($request),
        ], 201));
    }
}
