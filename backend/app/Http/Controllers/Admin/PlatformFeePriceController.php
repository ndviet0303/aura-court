<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Package\Actions\SchedulePackagePrice;
use App\Domain\Package\DTOs\SchedulePackagePriceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackagePriceRequest;
use App\Http\Resources\Package\PackagePriceResource;
use App\Http\Resources\Package\PackageTermPriceResource;
use App\Services\Package\PackagePriceCatalog;
use Illuminate\Http\JsonResponse;

class PlatformFeePriceController extends Controller
{
    public function __construct(
        private readonly PackagePriceCatalog $catalog,
        private readonly SchedulePackagePrice $schedulePrice,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PackageTermPriceResource::collection($this->catalog->termsAt(now())),
        ]);
    }

    public function store(StorePackagePriceRequest $request): JsonResponse
    {
        $result = $this->schedulePrice->execute(SchedulePackagePriceData::fromRequest($request));

        // UC-5.3 E-3: the price is saved either way; a failed notice is reported beside it, not as a failure.
        return response()->json([
            'data' => PackagePriceResource::make($result->price),
            'notification_failed' => $result->notificationFailed,
            'message' => $result->notificationFailed ? 'Đã đặt giá gói nhưng chưa gửi được thông báo cho đối tác.' : 'Đã đặt giá gói.',
        ], 201);
    }
}
