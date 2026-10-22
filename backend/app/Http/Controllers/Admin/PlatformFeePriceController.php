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
        $price = $this->schedulePrice->execute(SchedulePackagePriceData::fromRequest($request));

        return response()->json([
            'data' => PackagePriceResource::make($price),
            'message' => 'Đã đặt giá gói.',
        ], 201);
    }
}
