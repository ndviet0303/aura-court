<?php

namespace App\Domain\Venue\Actions;

use App\Domain\Partner\Services\PartnerRolloutService;
use App\Domain\Subscription\Actions\EvaluatePlatformEntitlement;
use App\Models\Court;
use App\Models\Venue;
use Illuminate\Validation\ValidationException;

class VerifyEffectiveBookingEligibility
{
    public function __construct(
        private readonly EvaluatePlatformEntitlement $entitlementEvaluator,
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
                'is_nonpublic' => ! $courtModel->venue->isPublished() || ! $courtModel->venue->is_active,
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
        $rollout = app(PartnerRolloutService::class);
        $enforcePublication = $org ? $rollout->shouldEnforce($org, 'publication') : false;
        $enforceSubscription = $org ? $rollout->shouldEnforce($org, 'subscription') : false;

        if (! $venueModel->is_active || ! $venueModel->isPublished()) {
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

        if (($enforcePublication || $venueModel->isPublished()) && ! $org->isApprovedPartner()) {
            return [
                'eligible' => false,
                'reason' => 'PARTNER_NOT_ELIGIBLE',
                'message' => 'Cơ sở thể thao hiện đang tạm ngừng hoạt động hoặc ngừng nhận lịch đặt mới.',
            ];
        }

        if ($enforceSubscription) {
            $entitlement = $this->entitlementEvaluator->execute($org->id);
            if (! $entitlement['can_take_bookings']) {
                return [
                    'eligible' => false,
                    'reason' => 'SUBSCRIPTION_INELIGIBLE',
                    'message' => 'Gói dịch vụ nền tảng của câu lạc bộ đã hết hạn hoặc chưa kích hoạt. Không thể nhận lịch đặt mới.',
                ];
            }
        } else {
            $entitlement = $this->entitlementEvaluator->execute($org->id);
            $rollout->recordShadowComparison($venueModel, 'booking_eligibility', true, (bool) ($entitlement['can_take_bookings'] ?? false));
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
            if ($result['reason'] === 'SUBSCRIPTION_INELIGIBLE') {
                throw ValidationException::withMessages([
                    'subscription' => [$result['message']],
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
