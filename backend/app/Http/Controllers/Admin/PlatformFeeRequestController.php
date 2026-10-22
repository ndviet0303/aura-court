<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\PlatformFeeRequestRepositoryInterface;
use App\Domain\Package\DTOs\PlatformFeeRequestFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPlatformFeeRequestsRequest;
use App\Http\Resources\Package\PlatformFeeRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Package payment requests of every organization for the platform admin (D15). */
class PlatformFeeRequestController extends Controller
{
    public function __construct(private readonly PlatformFeeRequestRepositoryInterface $requests) {}

    public function index(ListPlatformFeeRequestsRequest $request): AnonymousResourceCollection
    {
        return PlatformFeeRequestResource::collection($this->requests->paginate(PlatformFeeRequestFilterData::fromRequest($request), now()));
    }

    public function show(string $code): JsonResponse
    {
        $paymentRequest = $this->requests->findByCode(strtoupper($code));
        abort_if($paymentRequest === null, 404, 'Yêu cầu thanh toán gói không tồn tại.');

        return response()->json(['data' => PlatformFeeRequestResource::make($paymentRequest)]);
    }
}
