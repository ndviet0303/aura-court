<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\PackagePaymentRequestRepositoryInterface;
use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Domain\Package\Actions\CreatePackagePaymentRequest;
use App\Domain\Package\Actions\TerminateVenuePackage;
use App\Domain\Package\DTOs\CreatePackagePaymentRequestData;
use App\Domain\Package\DTOs\TerminateVenuePackageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\StorePackagePaymentRequest;
use App\Http\Requests\Partner\TerminateVenuePackageRequest;
use App\Http\Resources\Package\PackagePaymentRequestResource;
use App\Http\Resources\Package\PackageTermPriceResource;
use App\Http\Resources\Package\VenuePackageOverviewResource;
use App\Http\Resources\Package\VenuePackageResource;
use App\Models\OrganizationMember;
use App\Services\Package\PackagePriceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Venue packages for the organization owner (D4, D8). These path-id routes have no tenant context, so owner rights
 * are checked against the path organization, as the other partner venue routes do.
 */
class VenuePackageController extends Controller
{
    public function __construct(
        private readonly VenuePackageRepositoryInterface $packages,
        private readonly PackagePaymentRequestRepositoryInterface $requests,
        private readonly PackagePriceCatalog $catalog,
        private readonly CreatePackagePaymentRequest $createRequest,
        private readonly TerminateVenuePackage $terminatePackage,
    ) {}

    public function index(Request $request, int $organizationId): JsonResponse
    {
        $this->authorizeOwner($request, $organizationId);

        return response()->json([
            'data' => [
                'venues' => VenuePackageOverviewResource::collection($this->packages->venuesWithPackages($organizationId)),
                'terms' => PackageTermPriceResource::collection($this->catalog->termsAt(now())),
            ],
        ]);
    }

    public function storeRequest(StorePackagePaymentRequest $request, int $organizationId, int $venueId): JsonResponse
    {
        $paymentRequest = $this->createRequest->execute(CreatePackagePaymentRequestData::fromRequest($request, $organizationId, $venueId));

        // D4: the venue's pending request for the same term comes back unchanged with 200.
        return response()->json([
            'data' => PackagePaymentRequestResource::make($paymentRequest),
        ], $paymentRequest->wasRecentlyCreated ? 201 : 200);
    }

    public function showRequest(Request $request, int $organizationId, string $code): JsonResponse
    {
        $this->authorizeOwner($request, $organizationId);
        $paymentRequest = $this->requests->findByCodeForOrganization(strtoupper($code), $organizationId);
        abort_if($paymentRequest === null, 404, 'Yêu cầu thanh toán gói không tồn tại.');

        return response()->json(['data' => PackagePaymentRequestResource::make($paymentRequest)]);
    }

    public function terminate(TerminateVenuePackageRequest $request, int $organizationId, int $venueId): JsonResponse
    {
        $package = $this->terminatePackage->execute(TerminateVenuePackageData::fromRequest($request, $organizationId, $venueId));

        return response()->json([
            'data' => VenuePackageResource::make($package),
            'message' => 'Đã chấm dứt gói của cơ sở.',
        ]);
    }

    private function authorizeOwner(Request $request, int $organizationId): void
    {
        abort_unless(
            $request->user()->hasRoleInOrganization($organizationId, OrganizationMember::ROLE_ORGANIZATION_OWNER),
            403,
            'Chỉ chủ tổ chức mới xem được gói của cơ sở.',
        );
    }
}
