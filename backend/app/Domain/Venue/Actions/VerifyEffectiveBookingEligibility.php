<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Domain\Package\Enums\PackageStatus;
use App\Models\Court;
use App\Models\ReceivingConnection;
use App\Models\Venue;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Pricing\PriceCoverageService;
use App\Services\Reservation\CourtPriceRuleService;
use App\Services\Reservation\SlotSelectionPolicy;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class VerifyEffectiveBookingEligibility
{
    public function __construct(
        private readonly VenuePackageRepositoryInterface $packages,
        private readonly CourtPriceRuleService $priceRules,
        private readonly PriceCoverageService $coverage,
        private readonly SlotSelectionPolicy $slots,
        private readonly FeatureFlagService $features,
    ) {}

    public function execute(Court|int $court): array
    {
        $courtModel = $court instanceof Court
            ? $court->loadMissing(['venue.organization'])
            : Court::with(['venue.organization'])->find($court);

        if (! $courtModel || $courtModel->status !== Court::STATUS_ACTIVE) {
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

        // BR-41, BR-47: a gap anywhere in the effective service schedule stops every booking of the court, priced times included.
        if ($this->coverage->missingItems($courtModel, now()) !== []) {
            return [
                'eligible' => false,
                'reason' => 'COURT_UNPRICED',
                'message' => 'Bảng giá của sân chưa phủ kín lịch phục vụ nên sân chưa nhận đặt.',
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

        if (! $venueModel->isActive() || $venueModel->isSuspended()) {
            return [
                'eligible' => false,
                'reason' => 'VENUE_NOT_OPERATIONAL',
                'message' => 'Cơ sở thể thao chưa mở hoạt động hoặc đang tạm ngừng.',
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

        if (! $org->isApprovedPartner()) {
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

        // D2: only direct-owner organizations collect through the owner's connection; the legacy wallet cohort has none.
        if ($this->features->enabled('direct_owner_payments', (int) $org->id) && ! $this->hasVerifiedConnection($venueModel)) {
            return [
                'eligible' => false,
                'reason' => 'RECEIVING_CONNECTION_REQUIRED',
                'message' => 'Cơ sở chưa có tài khoản nhận tiền đã xác minh nên chưa nhận đặt sân mới. Chủ cơ sở cần thêm và xác minh tài khoản nhận tiền cho cơ sở.',
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
            abort_if($result['reason'] === 'RECEIVING_CONNECTION_REQUIRED', 409, $result['message']);
            if ($publicFacing || ($result['is_nonpublic'] ?? false)) {
                abort(404, 'Sân không tồn tại hoặc đã ngừng hoạt động.');
            }

            throw ValidationException::withMessages([
                'booking' => $result['message'],
            ]);
        }

        return $result['court'];
    }

    /**
     * Slot resale (design "Kiểm eligibility dưới lock"): the narrower check on a court and venue the caller has locked.
     * The slot keeps its recorded time and price, so price, booking policy and service time are not rechecked.
     */
    public function assertResalable(Court $lockedCourt, CarbonInterface $start, CarbonInterface $end): void
    {
        abort_unless($lockedCourt->status === Court::STATUS_ACTIVE, 409, 'Sân không tồn tại hoặc đã ngừng hoạt động.');
        $venue = $this->evaluateVenue($lockedCourt->venue);
        abort_unless($venue['eligible'], 409, $venue['message']);
        $this->slots->assertNoStop($lockedCourt, $start, $end);
    }

    /** Re-read through the relation query so a public court or venue payload never carries the connection. */
    private function hasVerifiedConnection(Venue $venue): bool
    {
        $receiver = $venue->currentReceivingConnection()->first();

        return $receiver !== null
            && (int) $receiver->organization_id === (int) $venue->organization_id
            && (int) $receiver->venue_id === (int) $venue->id
            && $receiver->owner_attested_at !== null
            && $receiver->status === ReceivingConnection::STATUS_VERIFIED
            && $receiver->webhook_secret_ciphertext !== null
            && (int) $receiver->credential_version > 0
            && $receiver->verified_at !== null && ! $receiver->verified_at->isFuture()
            && $receiver->effective_from !== null && ! $receiver->effective_from->isFuture()
            && ($receiver->effective_until === null || $receiver->effective_until->isFuture());
    }
}
