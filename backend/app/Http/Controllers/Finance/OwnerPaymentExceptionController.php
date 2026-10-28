<?php

namespace App\Http\Controllers\Finance;

use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListOwnerPaymentExceptionsRequest;
use App\Http\Resources\Finance\OwnerPaymentExceptionDetailResource;
use App\Http\Resources\Finance\OwnerPaymentExceptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Read-only lookup of direct-owner payment exceptions; it stays available while legacy payouts are off (FR-81, D8). */
class OwnerPaymentExceptionController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly FinanceExceptionRepositoryInterface $exceptions,
        private readonly ReservationRepositoryInterface $reservations,
    ) {}

    public function index(ListOwnerPaymentExceptionsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->validated('per_page', 30);

        return OwnerPaymentExceptionResource::collection(
            $this->exceptions->paginateOwnerPaymentExceptions($this->organization($request), $perPage)
        );
    }

    /** UC-4.12 steps 4-5; another organization's exception is 404 (BR-88). */
    public function show(Request $request, int $exception): OwnerPaymentExceptionDetailResource
    {
        $organization = $this->organization($request);
        $record = $this->exceptions->findOwnerPaymentException($organization, $exception) ?? abort(404);
        $metadata = $record->metadata ?? [];
        // Only a booking request's id is a booking code (see OwnerPaymentExceptionResource).
        $booking = ($metadata['purpose'] ?? null) === 'booking' && is_string($metadata['purpose_id'] ?? null)
            ? $this->reservations->findInOrganizationByCode((int) $organization->id, $metadata['purpose_id'])
            : null;

        return new OwnerPaymentExceptionDetailResource($record, $booking);
    }
}
