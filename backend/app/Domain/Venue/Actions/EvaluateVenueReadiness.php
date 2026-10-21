<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\Court;
use App\Models\ReceivingConnection;
use App\Models\Venue;
use App\Services\Reservation\CourtPriceRuleService;
use App\Services\Reservation\VenueOperatingCalendar;

class EvaluateVenueReadiness
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly PlatformSubscriptionRepositoryInterface $subRepo,
        private readonly VenueOperatingCalendar $operatingCalendar,
        private readonly CourtPriceRuleService $priceRules,
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

        $venue->loadMissing(['organization', 'courts', 'currentBookingPolicy', 'currentReceivingConnection']);
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

        // 5. Every active court has a normal price table link in effect
        $courtsWithoutPrices = $activeCourts->count() - count($this->priceRules->pricedCourtIds($activeCourts));
        $pricingComplete = $hasActiveCourts && $courtsWithoutPrices === 0;
        $checklist['court_pricing'] = $pricingComplete;
        if ($courtsWithoutPrices > 0) {
            $missingItems[] = [
                'code' => 'COURT_PRICES_MISSING',
                'message' => "Có {$courtsWithoutPrices} sân thi đấu chưa được thiết lập bảng giá theo khung giờ.",
                'field' => 'pricing',
            ];
        }

        $hasBookingPolicy = $venue->currentBookingPolicy !== null;
        $checklist['policies_configured'] = $hasBookingPolicy;
        if (! $hasBookingPolicy) {
            $missingItems[] = [
                'code' => 'BOOKING_POLICY_REQUIRED',
                'message' => 'Cơ sở cần lưu chính sách đặt sân, đổi/hủy, hoàn tiền, giờ đến, định kỳ và chuyển nhượng trước khi gửi duyệt.',
                'field' => 'booking_policy',
            ];
        }

        $calendarConfigured = $this->operatingCalendar->configuredForVenue($venue->id);
        $checklist['operating_calendar_configured'] = $calendarConfigured;
        if (! $calendarConfigured) {
            $missingItems[] = [
                'code' => 'OPERATING_CALENDAR_REQUIRED',
                'message' => 'Cơ sở cần lưu lịch hoạt động có ít nhất một khung giờ mở cửa trước khi gửi duyệt.',
                'field' => 'operating_calendar',
            ];
        }

        $receiver = $venue->currentReceivingConnection;
        $receiverSelected = $receiver !== null
            && (int) $receiver->organization_id === (int) $venue->organization_id
            && (int) $receiver->venue_id === (int) $venue->id
            && $receiver->owner_attested_at !== null
            && $receiver->status === ReceivingConnection::STATUS_VERIFIED
            && $receiver->webhook_secret_ciphertext !== null
            && (int) $receiver->credential_version > 0
            && $receiver->verified_at !== null
            && ! $receiver->verified_at->isFuture()
            && $receiver->effective_from !== null
            && ! $receiver->effective_from->isFuture()
            && ($receiver->effective_until === null || $receiver->effective_until->isFuture());
        $checklist['receiving_connection_selected'] = $receiverSelected;
        if (! $receiverSelected) {
            $missingItems[] = [
                'code' => 'RECEIVING_CONNECTION_REQUIRED',
                'message' => 'Cơ sở cần chọn một tài khoản nhận tiền đã xác minh trước khi gửi duyệt.',
                'field' => 'receiving_connection',
            ];
        }

        // 6. Quota Compliance (Venues and Courts within Plan Limits)
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
