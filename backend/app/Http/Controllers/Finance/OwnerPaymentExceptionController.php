<?php

namespace App\Http\Controllers\Finance;

use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListOwnerPaymentExceptionsRequest;
use App\Http\Resources\Finance\OwnerPaymentExceptionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Read-only lookup of direct-owner payment exceptions; it stays available while legacy payouts are off (FR-81, D8). */
class OwnerPaymentExceptionController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly FinanceExceptionRepositoryInterface $exceptions) {}

    public function index(ListOwnerPaymentExceptionsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->validated('per_page', 30);

        return OwnerPaymentExceptionResource::collection(
            $this->exceptions->paginateOwnerPaymentExceptions($this->organization($request), $perPage)
        );
    }
}
