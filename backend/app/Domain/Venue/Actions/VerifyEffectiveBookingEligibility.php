<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Domain\Package\Enums\PackageStatus;
use App\Domain\Partner\Services\PartnerRolloutService;
use App\Models\Court;
use App\Models\Venue;
use App\Services\Reservation\CourtPriceRuleService;
use Illuminate\Validation\ValidationException;

class VerifyEffectiveBookingEligibility
{
    public function __construct(
        private readonly VenuePackageRepositoryInterface $packages,
        private readonly CourtPriceRuleService $priceRules,
    ) {}

    public function execute(Court|int $court): array
    {
        $courtModel = $court instanceof Court
            ? $court->loadMissing(['venue.organization'])
            : Court::with(['venue.organization'])->find($court);

        if (! $courtModel || ! $courtModel->is_active) {
            return [
                'eligible' => false,
                'reason' => 'COURT_INACTIVE',
                'message' => 'Sân không tồn tại hoặc đã ngừng hoạt động.',
            ];
        }

        if (! $courtModel->venue) {
            return [
                'eligible' => false,
                'reason' => 'VENUE_NOT_OPERATIONAL',
                'message' => 'Cơ sở thể thao không tồn tại.',
                'is_nonpublic' => true,
            ];
        }

        $venueEligibility = $this->evaluateVenue($courtModel->venue);
        if (! $venueEligibility['eligible']) {
            return $venueEligibility + [
                'court' => $courtModel,
                'is_nonpublic' => ! $courtModel->venue->isActive() || $courtModel->venue->isSuspended(),
            ];
        }

        if (! $this->priceRules->isPriced($courtModel)) {
            return [
                'eligible' => false,
                'reason' => 'COURT_UNPRICED',
                'message' => 'Sân chưa được cấu hình giá.',
                'court' => $courtModel,
                'is_nonpublic' => true,
            ];
        }

        return $venueEligibility + ['court' => $courtModel];
    }

    public function evaluateVenue(Venue|int $venue): array
    {
        $venueModel = $venue instanceof Venue
            ? $venue->loadMissing('organization')
            : Venue::with('organization')->find($venue);
        if (! $venueModel) {
            return [
                'eligible' => false,
                'reason' => 'VENUE_NOT_OPERATIONAL',
                'message' => 'Cơ sở thể thao không tồn tại.',
                'is_nonpublic' => true,
            ];
        }

        $org = $venueModel->organization;
        $enforcePublication = $org ? app(PartnerRolloutService::class)->shouldEnforce($org, 'publication') : false;

        if (! $venueModel->isActive() || $venueModel->isSuspended()) {
            return [
                'eligible' => false,
                'reason' => 'VENUE_NOT_OPERATIONAL',
                'message' => 'Cơ sở thể thao chưa được xuất bản hoặc đang tạm ngừng hoạt động.',
                'is_nonpublic' => true,
            ];
        }

        if (! $org || $org->status !== 'active') {
            return [
                'eligible' => false,
                'reason' => 'PARTNER_NOT_ELIGIBLE',
                'message' => 'Cơ sở thể thao hiện đang tạm ngừng hoạt động.',
            ];
        }

        if ($org->isSuspendedPartner() || $org->partner_status === 'suspended') {
            return [
                'eligible' => false,
                'reason' => 'PARTNER_SUSPENDED',
                'message' => 'Cơ sở thể thao hiện đang tạm ngừng hoạt động do đối tác bị đình chỉ.',
            ];
        }

        if (($enforcePublication || $venueModel->isActive()) && ! $org->isApprovedPartner()) {
            return [
                'eligible' => false,
                'reason' => 'PARTNER_NOT_ELIGIBLE',
                'message' => 'Cơ sở thể thao hiện đang tạm ngừng hoạt động hoặc ngừng nhận lịch đặt mới.',
            ];
        }

        // D9: the venue's own package at now; booking paths call this after locking the venue row, which every package change locks first.
        if ($this->packages->statusAt($venueModel->id, now()) !== PackageStatus::Active) {
            return [
                'eligible' => false,
                'reason' => 'package_inactive',
                'message' => 'Cơ sở chưa có gói nền tảng còn hiệu lực nên tạm thời không nhận lịch đặt mới.',
            ];
        }

        return [
            'eligible' => true,
            'venue' => $venueModel,
            'organization' => $org,
        ];
    }

    public function assertEligible(Court|int $court, bool $publicFacing = false): Court
    {
        $result = $this->execute($court);

        if (! $result['eligible']) {
            if ($result['reason'] === 'package_inactive') {
                throw ValidationException::withMessages([
                    'package' => [$result['message']],
                ]);
            }

            if ($result['reason'] === 'PARTNER_SUSPENDED') {
                throw ValidationException::withMessages([
                    'partner' => [$result['message']],
                ]);
            }
            if ($publicFacing || ($result['is_nonpublic'] ?? false)) {
                abort(404, 'Sân không tồn tại hoặc đã ngừng hoạt động.');
            }

            throw ValidationException::withMessages([
                'booking' => $result['message'],
            ]);
        }

        return $result['court'];
    }
}
