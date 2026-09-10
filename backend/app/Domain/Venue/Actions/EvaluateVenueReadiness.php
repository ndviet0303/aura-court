<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\Venue;

class EvaluateVenueReadiness
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
    ) {}

    /**
     * @return array{
     *     is_ready: bool,
     *     missing_items: array<int, array{code: string, message: string, field: string}>,
     *     checklist: array<string, bool>
     * }
     */
    public function execute(Venue|int $venueOrId): array
    {
        $venue = is_int($venueOrId) ? $this->venueRepo->findById($venueOrId) : $venueOrId;
        if (! $venue) {
            abort(404, 'Cơ sở thể thao không tồn tại.');
        }

        $venue->loadMissing(['organization', 'courts.priceSlots']);
        $org = $venue->organization;

        $missingItems = [];
        $checklist = [];

        // 1. Check Partner Approved Status
        $partnerApproved = $org !== null && $org->isApprovedPartner();
        $checklist['partner_approved'] = $partnerApproved;
        if (! $partnerApproved) {
            $missingItems[] = [
                'code' => 'ORG_NOT_APPROVED_PARTNER',
                'message' => 'Câu lạc bộ chưa được phê duyệt tư cách đối tác hợp lệ trên nền tảng.',
                'field' => 'organization.partner_status',
            ];
        }

        // 2. Check Subscription / Trial Entitlement
        $sub = $org ? $this->subRepo->findActiveForOrganization($org->id) : null;
        $subscriptionValid = $sub !== null && $sub->isValid();
        $checklist['subscription_entitled'] = $subscriptionValid;
        if (! $subscriptionValid) {
            $missingItems[] = [
                'code' => 'SUBSCRIPTION_REQUIRED',
                'message' => 'Câu lạc bộ cần có gói dịch vụ nền tảng hoặc chương trình dùng thử đang hoạt động.',
                'field' => 'subscription',
            ];
        }

        // 3. Venue Identity (Name, Address)
        $hasName = ! empty($venue->name) && mb_strlen(trim((string) $venue->name)) >= 3;
        $checklist['venue_name'] = $hasName;
        if (! $hasName) {
            $missingItems[] = [
                'code' => 'VENUE_NAME_MISSING',
                'message' => 'Tên cơ sở thể thao phải có ít nhất 3 ký tự.',
                'field' => 'name',
            ];
        }

        $hasAddress = ! empty($venue->address) && mb_strlen(trim((string) $venue->address)) >= 5;
        $checklist['venue_address'] = $hasAddress;
        if (! $hasAddress) {
            $missingItems[] = [
                'code' => 'VENUE_ADDRESS_MISSING',
                'message' => 'Địa chỉ cơ sở thể thao chưa đầy đủ (tối thiểu 5 ký tự).',
                'field' => 'address',
            ];
        }

        // 4. Active Courts (At least 1 active court)
        $activeCourts = $venue->courts->filter(fn (Court $c) => (bool) $c->is_active);
        $hasActiveCourts = $activeCourts->isNotEmpty();
        $checklist['active_courts'] = $hasActiveCourts;
        if (! $hasActiveCourts) {
            $missingItems[] = [
                'code' => 'NO_ACTIVE_COURTS',
                'message' => 'Cơ sở phải có ít nhất một sân thi đấu đang ở trạng thái hoạt động.',
                'field' => 'courts',
            ];
        }

        // 5. Deterministic Pricing on Active Courts
        $courtsWithoutPrices = 0;
        foreach ($activeCourts as $court) {
            $hasActivePrice = $court->priceSlots->where('is_active', true)->isNotEmpty();
            if (! $hasActivePrice) {
                $courtsWithoutPrices++;
            }
        }
        $pricingComplete = $hasActiveCourts && $courtsWithoutPrices === 0;
        $checklist['court_pricing'] = $pricingComplete;
        if ($courtsWithoutPrices > 0) {
            $missingItems[] = [
                'code' => 'COURT_PRICES_MISSING',
                'message' => "Có {$courtsWithoutPrices} sân thi đấu chưa được thiết lập bảng giá theo khung giờ.",
                'field' => 'pricing',
            ];
        }

        // 6. Cancellation & Supported Policies
        $cancellationPolicyValid = ($org?->cancellation_grace_hours ?? 12) >= 0;
        $checklist['cancellation_policy'] = $cancellationPolicyValid;
        if (! $cancellationPolicyValid) {
            $missingItems[] = [
                'code' => 'CANCELLATION_POLICY_INVALID',
                'message' => 'Chính sách hủy đặt sân của câu lạc bộ không hợp lệ.',
                'field' => 'cancellation_policy',
            ];
        }

        // 7. Quota Compliance (Venues and Courts within Plan Limits)
        $quotaCompliant = true;
        if ($sub && $sub->plan && $org) {
            $totalCourts = $org->courts()->count();
            if ($totalCourts > (int) $sub->plan->max_courts) {
                $quotaCompliant = false;
                $missingItems[] = [
                    'code' => 'COURT_QUOTA_EXCEEDED',
                    'message' => "Tổng số sân ({$totalCourts}) vượt quá giới hạn ({$sub->plan->max_courts}) của gói {$sub->plan->name}.",
                    'field' => 'quotas.courts',
                ];
            }
        }
        $checklist['quota_compliant'] = $quotaCompliant;

        // Note: Optional staff and optional POS products MUST NOT block readiness!
        // Having 0 staff or 0 POS products is completely valid for readiness.

        return [
            'is_ready' => empty($missingItems),
            'missing_items' => $missingItems,
            'checklist' => $checklist,
        ];
    }
}
