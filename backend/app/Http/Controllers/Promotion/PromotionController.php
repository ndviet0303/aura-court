<?php

namespace App\Http\Controllers\Promotion;

use App\Domain\Subscription\Actions\EvaluatePlatformEntitlement;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PromotionCampaign;
use App\Models\TenantMembership;
use App\Models\Venue;
use App\Services\Booking\DiscountService;
use App\Services\Infrastructure\AuditLogger;
use App\Services\Infrastructure\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function preview(Request $request, DiscountService $discounts)
    {
        $data = $request->validate([
            'subtotal' => 'required|integer|min:0',
            'code' => 'nullable|string|max:40',
            'venue_id' => 'nullable|integer',
            'court_id' => 'nullable|integer',
        ]);
        $organization = $this->selectedOrganization($request);
        abort_unless($organization->hasMember($request->user()->id), 403);
        $this->assertLocationScope($organization, $data['venue_id'] ?? null, $data['court_id'] ?? null);

        return $discounts->preview(
            $request->user(),
            $data['subtotal'],
            $data['code'] ?? null,
            (int) $organization->id,
            $data['venue_id'] ?? null,
            $data['court_id'] ?? null,
        );
    }

    public function membership(Request $request)
    {
        $user = $request->user();
        $organizationId = $request->header('X-Organization-ID')
            ?? $request->header('X-Organization-Id')
            ?? $request->query('organization_id');
        $organization = $organizationId
            ? Organization::query()->where('status', 'active')->find($organizationId)
            : $this->tenantContext->getOrganization();
        if (! $organization && ! $organizationId) {
            $membershipOrganizationId = TenantMembership::query()
                ->withoutGlobalScope('tenant_isolation')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->latest('ends_at')
                ->value('organization_id');
            $organization = $membershipOrganizationId
                ? Organization::query()->where('status', 'active')->find($membershipOrganizationId)
                : $user->organizations()->where('organizations.status', 'active')->first();
        }

        $hasTenantMembership = $organization && TenantMembership::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->exists();
        if (! $organization || (! $organization->hasMember($user->id) && ! $hasTenantMembership)) {
            return ['membership' => null];
        }

        $now = now();
        $membership = TenantMembership::query()->with([
            'plan',
            'entitlements' => fn ($query) => $query
                ->where('organization_id', $organization->id)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now),
        ])
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->whereHas('plan', fn ($query) => $query->where('organization_id', $organization->id))
            ->latest('ends_at')
            ->first();

        if (! $membership) {
            return ['membership' => null];
        }

        $discount = $membership->entitlements->first(fn ($entitlement) => in_array($entitlement->benefit_code, [
            'court_discount_percent', 'booking_discount_percent', 'discount_percent',
        ], true));
        $value = $discount?->benefit_value;
        $discountPercent = $discount
            ? (is_numeric($value) ? (int) $value : (int) ($value['percent'] ?? $value['discount_percent'] ?? $value['value'] ?? 0))
            : 0;

        return ['membership' => [
            'id' => (int) $membership->id,
            'user_id' => (int) $membership->user_id,
            'membership_plan_id' => (int) $membership->plan_id,
            'organization_id' => (int) $membership->organization_id,
            'status' => $membership->status,
            'starts_at' => $membership->starts_at,
            'ends_at' => $membership->ends_at,
            'plan' => [
                'id' => (int) $membership->plan_id,
                'name' => $membership->plan?->name,
                'discount_percent' => $discountPercent,
                'benefits' => $membership->plan?->benefits ?? [],
            ],
            'entitlements' => $membership->entitlements->map(fn ($entitlement) => [
                'id' => (int) $entitlement->id,
                'benefit_code' => $entitlement->benefit_code,
                'benefit_value' => $entitlement->benefit_value,
                'starts_at' => $entitlement->starts_at,
                'ends_at' => $entitlement->ends_at,
            ])->values(),
        ]];
    }

    public function coupons(Request $request)
    {
        $organization = $this->selectedOrganization($request);
        $this->authorizePromotionManager($request, $organization);

        return Coupon::query()->with('campaign')->where('organization_id', $organization->id)->latest()->paginate(30);
    }

    public function storeCoupon(Request $request)
    {
        $organization = $this->selectedOrganization($request);
        $this->authorizePromotionManager($request, $organization, requireEntitlement: true);
        $data = $this->validatedCoupon($request);
        $data['code'] = strtoupper(trim($data['code']));
        if ($data['discount_type'] === 'percent' && $data['discount_value'] > 100) {
            abort(422, 'Giảm theo phần trăm không được vượt quá 100%.');
        }

        $this->assertRange($data['starts_at'] ?? null, $data['ends_at'] ?? null, 'ends_at');
        $this->assertRange(
            $data['campaign_starts_at'] ?? $data['starts_at'] ?? null,
            $data['campaign_ends_at'] ?? $data['ends_at'] ?? null,
            'campaign_ends_at'
        );
        $this->assertLocationScope($organization, $data['venue_id'] ?? null, $data['court_id'] ?? null);
        $campaignVenueId = array_key_exists('campaign_venue_id', $data) ? $data['campaign_venue_id'] : ($data['venue_id'] ?? null);
        $campaignCourtId = array_key_exists('campaign_court_id', $data) ? $data['campaign_court_id'] : ($data['court_id'] ?? null);
        $this->assertLocationScope($organization, $campaignVenueId, $campaignCourtId, 'campaign_venue_id', 'campaign_court_id');

        return DB::transaction(function () use ($request, $organization, $data, $campaignVenueId, $campaignCourtId) {
            $campaign = null;
            if (! empty($data['promotion_campaign_id'])) {
                $campaign = PromotionCampaign::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($data['promotion_campaign_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless($campaign->status !== PromotionCampaign::STATUS_RETIRED, 409, 'A retired campaign cannot receive new coupons.');
                $campaignVenueId = $campaign->venue_id;
                $campaignCourtId = $campaign->court_id;
            } else {
                $campaign = PromotionCampaign::create([
                    'organization_id' => $organization->id,
                    'venue_id' => $campaignVenueId,
                    'court_id' => $campaignCourtId,
                    'name' => trim((string) ($data['campaign_name'] ?? $data['code'])),
                    'description' => $data['campaign_description'] ?? null,
                    'status' => PromotionCampaign::STATUS_DRAFT,
                    'eligibility_group' => $data['eligibility_group'] ?? Coupon::GROUP_ALL,
                    'starts_at' => $data['campaign_starts_at'] ?? $data['starts_at'] ?? null,
                    'ends_at' => $data['campaign_ends_at'] ?? $data['ends_at'] ?? null,
                    'usage_limit' => $data['campaign_usage_limit'] ?? $data['usage_limit'] ?? null,
                    'min_spend' => $data['campaign_min_spend'] ?? $data['min_spend'] ?? 0,
                    'can_combine_with_membership' => $data['campaign_can_combine_with_membership'] ?? $data['can_combine_with_membership'] ?? true,
                    'version' => 1,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                $this->auditLogger->record($request, 'promotion.campaign.created', $campaign, [
                    'organization_id' => (int) $organization->id,
                    'name' => $campaign->name,
                    'eligibility_group' => $campaign->eligibility_group,
                    'venue_id' => $campaign->venue_id,
                    'court_id' => $campaign->court_id,
                    'starts_at' => $campaign->starts_at?->toIso8601String(),
                    'ends_at' => $campaign->ends_at?->toIso8601String(),
                    'usage_limit' => $campaign->usage_limit,
                ]);
            }

            $this->assertCouponScopeWithinCampaign(
                $data['venue_id'] ?? null,
                $data['court_id'] ?? null,
                $campaignVenueId,
                $campaignCourtId,
            );

            $coupon = Coupon::create([
                'organization_id' => $organization->id,
                'venue_id' => $data['venue_id'] ?? null,
                'court_id' => $data['court_id'] ?? null,
                'promotion_campaign_id' => $campaign->id,
                'code' => $data['code'],
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'max_discount' => $data['max_discount'] ?? null,
                'min_spend' => $data['min_spend'] ?? 0,
                'usage_limit' => $data['usage_limit'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => false,
                'status' => Coupon::STATUS_DRAFT,
                'eligibility_group' => $data['eligibility_group'] ?? Coupon::GROUP_ALL,
                'can_combine_with_membership' => $data['can_combine_with_membership'] ?? true,
                'metadata' => $data['metadata'] ?? null,
                'version' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->auditLogger->record($request, 'promotion.coupon.created', $coupon, [
                'organization_id' => (int) $organization->id,
                'venue_id' => $coupon->venue_id,
                'court_id' => $coupon->court_id,
                'promotion_campaign_id' => (int) $campaign->id,
                'status' => Coupon::STATUS_DRAFT,
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (int) $coupon->discount_value,
                'usage_limit' => $coupon->usage_limit,
            ]);

            return response()->json(['coupon' => $coupon->load('campaign')], 201);
        });
    }

    public function campaigns(Request $request)
    {
        $organization = $this->selectedOrganization($request);
        $this->authorizePromotionManager($request, $organization);

        return PromotionCampaign::query()
            ->where('organization_id', $organization->id)
            ->withCount('coupons')
            ->latest()
            ->paginate(30);
    }

    public function storeCampaign(Request $request)
    {
        $organization = $this->selectedOrganization($request);
        $this->authorizePromotionManager($request, $organization, requireEntitlement: true);
        $data = $this->validatedCampaign($request);
        $data['eligibility_group'] = $data['eligibility_group'] ?? Coupon::GROUP_ALL;
        $data['min_spend'] = $data['min_spend'] ?? 0;
        $data['can_combine_with_membership'] = $data['can_combine_with_membership'] ?? true;
        $this->assertRange($data['starts_at'] ?? null, $data['ends_at'] ?? null, 'ends_at');
        $this->assertLocationScope($organization, $data['venue_id'] ?? null, $data['court_id'] ?? null);

        return DB::transaction(function () use ($request, $organization, $data) {
            $campaign = PromotionCampaign::create([
                ...$data,
                'organization_id' => $organization->id,
                'status' => PromotionCampaign::STATUS_DRAFT,
                'version' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->auditLogger->record($request, 'promotion.campaign.created', $campaign, [
                'organization_id' => (int) $organization->id,
                'eligibility_group' => $campaign->eligibility_group,
                'venue_id' => $campaign->venue_id,
                'court_id' => $campaign->court_id,
            ]);

            return response()->json(['campaign' => $campaign], 201);
        });
    }

    public function updateCampaign(Request $request, int $campaign)
    {
        $organization = $this->selectedOrganization($request);
        $this->authorizePromotionManager($request, $organization, requireEntitlement: true);
        $record = PromotionCampaign::query()->where('organization_id', $organization->id)->whereKey($campaign)->firstOrFail();
        abort_unless($record->status !== PromotionCampaign::STATUS_RETIRED, 409, 'A retired campaign cannot be edited.');
        $data = $this->validatedCampaign($request, true);
        $this->assertLocationScope(
            $organization,
            array_key_exists('venue_id', $data) ? $data['venue_id'] : $record->venue_id,
            array_key_exists('court_id', $data) ? $data['court_id'] : $record->court_id,
        );
        $this->assertRange(
            array_key_exists('starts_at', $data) ? $data['starts_at'] : $record->starts_at?->toDateTimeString(),
            array_key_exists('ends_at', $data) ? $data['ends_at'] : $record->ends_at?->toDateTimeString(),
            'ends_at'
        );

        return DB::transaction(function () use ($request, $record, $data, $organization) {
            $locked = PromotionCampaign::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $venueId = array_key_exists('venue_id', $data) ? $data['venue_id'] : $locked->venue_id;
            $courtId = array_key_exists('court_id', $data) ? $data['court_id'] : $locked->court_id;
            $this->assertLocationScope($organization, $venueId, $courtId);
            foreach ($locked->coupons as $coupon) {
                $this->assertCouponScopeWithinCampaign($coupon->venue_id, $coupon->court_id, $venueId, $courtId);
            }
            $before = $locked->only(['name', 'description', 'eligibility_group', 'venue_id', 'court_id', 'starts_at', 'ends_at', 'usage_limit', 'min_spend', 'can_combine_with_membership', 'version']);
            $locked->update([...$data, 'version' => $locked->version + 1, 'updated_by' => $request->user()->id]);
            $this->auditLogger->record($request, 'promotion.campaign.updated', $locked, [
                'organization_id' => (int) $organization->id,
                'before' => $before,
                'after' => $locked->only(['name', 'description', 'eligibility_group', 'venue_id', 'court_id', 'starts_at', 'ends_at', 'usage_limit', 'min_spend', 'can_combine_with_membership', 'version']),
            ]);

            return response()->json(['campaign' => $locked->fresh()->loadCount('coupons')]);
        });
    }

    public function activateCampaign(Request $request, int $campaign)
    {
        return $this->transitionCampaign($request, $campaign, PromotionCampaign::STATUS_ACTIVE);
    }

    public function suspendCampaign(Request $request, int $campaign)
    {
        return $this->transitionCampaign($request, $campaign, PromotionCampaign::STATUS_SUSPENDED);
    }

    public function retireCampaign(Request $request, int $campaign)
    {
        return $this->transitionCampaign($request, $campaign, PromotionCampaign::STATUS_RETIRED);
    }

    public function updateCoupon(Request $request, int $coupon)
    {
        $organization = $this->selectedOrganization($request);
        $this->authorizePromotionManager($request, $organization, requireEntitlement: true);
        $record = Coupon::query()->where('organization_id', $organization->id)->with('campaign')->whereKey($coupon)->firstOrFail();
        abort_unless($record->status !== Coupon::STATUS_RETIRED, 409, 'A retired coupon cannot be edited.');
        $data = $this->validatedCoupon($request, true, $record);
        $this->assertLocationScope(
            $organization,
            array_key_exists('venue_id', $data) ? $data['venue_id'] : $record->venue_id,
            array_key_exists('court_id', $data) ? $data['court_id'] : $record->court_id,
        );
        $this->assertRange(
            array_key_exists('starts_at', $data) ? $data['starts_at'] : $record->starts_at?->toDateTimeString(),
            array_key_exists('ends_at', $data) ? $data['ends_at'] : $record->ends_at?->toDateTimeString(),
            'ends_at'
        );
        $this->assertRange(
            array_key_exists('campaign_starts_at', $data)
                ? $data['campaign_starts_at']
                : ($record->campaign?->starts_at?->toDateTimeString() ?? $record->starts_at?->toDateTimeString()),
            array_key_exists('campaign_ends_at', $data)
                ? $data['campaign_ends_at']
                : ($record->campaign?->ends_at?->toDateTimeString() ?? $record->ends_at?->toDateTimeString()),
            'campaign_ends_at'
        );
        if (($data['discount_type'] ?? $record->discount_type) === 'percent' && (int) ($data['discount_value'] ?? $record->discount_value) > 100) {
            abort(422, 'Giảm theo phần trăm không được vượt quá 100%.');
        }

        return DB::transaction(function () use ($request, $record, $data, $organization) {
            $locked = Coupon::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $campaignFields = array_intersect_key($data, array_flip([
                'campaign_name',
                'campaign_description',
                'eligibility_group',
                'campaign_starts_at',
                'campaign_ends_at',
                'campaign_usage_limit',
                'campaign_min_spend',
                'campaign_can_combine_with_membership',
                'campaign_venue_id',
                'campaign_court_id',
            ]));
            $couponFields = array_diff_key($data, $campaignFields);
            unset($couponFields['promotion_campaign_id']);

            if ($locked->promotion_campaign_id && $campaignFields !== []) {
                $campaign = PromotionCampaign::query()->where('organization_id', $organization->id)
                    ->whereKey($locked->promotion_campaign_id)->lockForUpdate()->firstOrFail();
                abort_unless($campaign->status !== PromotionCampaign::STATUS_RETIRED, 409, 'A retired campaign cannot be edited.');
                $newCampaignVenueId = array_key_exists('campaign_venue_id', $campaignFields) ? $campaignFields['campaign_venue_id'] : $campaign->venue_id;
                $newCampaignCourtId = array_key_exists('campaign_court_id', $campaignFields) ? $campaignFields['campaign_court_id'] : $campaign->court_id;
                $newCouponVenueId = array_key_exists('venue_id', $couponFields) ? $couponFields['venue_id'] : $locked->venue_id;
                $newCouponCourtId = array_key_exists('court_id', $couponFields) ? $couponFields['court_id'] : $locked->court_id;
                $this->assertLocationScope($organization, $newCampaignVenueId, $newCampaignCourtId, 'campaign_venue_id', 'campaign_court_id');
                $this->assertLocationScope($organization, $newCouponVenueId, $newCouponCourtId);
                $this->assertCouponScopeWithinCampaign($newCouponVenueId, $newCouponCourtId, $newCampaignVenueId, $newCampaignCourtId);
                foreach ($campaign->coupons()->where('id', '!=', $locked->id)->get() as $siblingCoupon) {
                    $this->assertCouponScopeWithinCampaign($siblingCoupon->venue_id, $siblingCoupon->court_id, $newCampaignVenueId, $newCampaignCourtId);
                }
                $campaignBefore = $campaign->only(['name', 'description', 'eligibility_group', 'venue_id', 'court_id', 'starts_at', 'ends_at', 'usage_limit', 'min_spend', 'can_combine_with_membership', 'version']);
                $campaignUpdate = [];
                if (array_key_exists('campaign_name', $campaignFields)) {
                    $campaignUpdate['name'] = trim((string) $campaignFields['campaign_name']);
                }
                if (array_key_exists('campaign_description', $campaignFields)) {
                    $campaignUpdate['description'] = $campaignFields['campaign_description'];
                }
                if (array_key_exists('eligibility_group', $campaignFields)) {
                    $campaignUpdate['eligibility_group'] = $campaignFields['eligibility_group'] ?? Coupon::GROUP_ALL;
                }
                if (array_key_exists('campaign_venue_id', $campaignFields)) {
                    $campaignUpdate['venue_id'] = $campaignFields['campaign_venue_id'];
                }
                if (array_key_exists('campaign_court_id', $campaignFields)) {
                    $campaignUpdate['court_id'] = $campaignFields['campaign_court_id'];
                }
                foreach ([
                    'campaign_starts_at' => 'starts_at',
                    'campaign_ends_at' => 'ends_at',
                    'campaign_usage_limit' => 'usage_limit',
                    'campaign_min_spend' => 'min_spend',
                    'campaign_can_combine_with_membership' => 'can_combine_with_membership',
                ] as $input => $column) {
                    if (array_key_exists($input, $campaignFields)) {
                        $campaignUpdate[$column] = $campaignFields[$input];
                    }
                }
                if (array_key_exists('min_spend', $campaignUpdate) && $campaignUpdate['min_spend'] === null) {
                    $campaignUpdate['min_spend'] = 0;
                }
                if (array_key_exists('can_combine_with_membership', $campaignUpdate) && $campaignUpdate['can_combine_with_membership'] === null) {
                    $campaignUpdate['can_combine_with_membership'] = true;
                }
                $campaign->update([...$campaignUpdate, 'version' => $campaign->version + 1, 'updated_by' => $request->user()->id]);
                $this->auditLogger->record($request, 'promotion.campaign.updated', $campaign, [
                    'organization_id' => (int) $organization->id,
                    'before' => $campaignBefore,
                    'after' => $campaign->only(['name', 'description', 'eligibility_group', 'venue_id', 'court_id', 'starts_at', 'ends_at', 'usage_limit', 'min_spend', 'can_combine_with_membership', 'version']),
                ]);
            }

            if ($locked->promotion_campaign_id && $campaignFields === []) {
                $campaign = PromotionCampaign::query()->where('organization_id', $organization->id)
                    ->whereKey($locked->promotion_campaign_id)->firstOrFail();
                $newCouponVenueId = array_key_exists('venue_id', $couponFields) ? $couponFields['venue_id'] : $locked->venue_id;
                $newCouponCourtId = array_key_exists('court_id', $couponFields) ? $couponFields['court_id'] : $locked->court_id;
                $this->assertLocationScope($organization, $newCouponVenueId, $newCouponCourtId);
                $this->assertCouponScopeWithinCampaign($newCouponVenueId, $newCouponCourtId, $campaign->venue_id, $campaign->court_id);
            }

            if (! $locked->promotion_campaign_id) {
                $newCouponVenueId = array_key_exists('venue_id', $couponFields) ? $couponFields['venue_id'] : $locked->venue_id;
                $newCouponCourtId = array_key_exists('court_id', $couponFields) ? $couponFields['court_id'] : $locked->court_id;
                $this->assertLocationScope($organization, $newCouponVenueId, $newCouponCourtId);
            }

            if (array_key_exists('code', $couponFields)) {
                $couponFields['code'] = strtoupper(trim((string) $couponFields['code']));
            }
            if (array_key_exists('min_spend', $couponFields) && $couponFields['min_spend'] === null) {
                $couponFields['min_spend'] = 0;
            }
            if (array_key_exists('can_combine_with_membership', $couponFields) && $couponFields['can_combine_with_membership'] === null) {
                $couponFields['can_combine_with_membership'] = true;
            }
            $couponBefore = $locked->only(['code', 'venue_id', 'court_id', 'discount_type', 'discount_value', 'max_discount', 'min_spend', 'usage_limit', 'starts_at', 'ends_at', 'can_combine_with_membership', 'status', 'version']);
            $locked->update([...$couponFields, 'version' => $locked->version + 1, 'updated_by' => $request->user()->id]);
            $this->auditLogger->record($request, 'promotion.coupon.updated', $locked, [
                'organization_id' => (int) $organization->id,
                'promotion_campaign_id' => $locked->promotion_campaign_id,
                'before' => $couponBefore,
                'after' => $locked->only(['code', 'venue_id', 'court_id', 'discount_type', 'discount_value', 'max_discount', 'min_spend', 'usage_limit', 'starts_at', 'ends_at', 'can_combine_with_membership', 'status', 'version']),
            ]);

            return response()->json(['coupon' => $locked->fresh('campaign')]);
        });
    }

    public function activateCoupon(Request $request, int $coupon)
    {
        return $this->transitionCoupon($request, $coupon, Coupon::STATUS_ACTIVE);
    }

    public function suspendCoupon(Request $request, int $coupon)
    {
        return $this->transitionCoupon($request, $coupon, Coupon::STATUS_SUSPENDED);
    }

    public function retireCoupon(Request $request, int $coupon)
    {
        return $this->transitionCoupon($request, $coupon, Coupon::STATUS_RETIRED);
    }

    private function transitionCampaign(Request $request, int $id, string $target): mixed
    {
        $organization = $this->selectedOrganization($request);
        $this->authorizePromotionManager($request, $organization, requireEntitlement: true);

        return DB::transaction(function () use ($request, $organization, $id, $target) {
            $campaign = PromotionCampaign::query()->where('organization_id', $organization->id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($campaign->status === $target) {
                return response()->json(['campaign' => $campaign->loadCount('coupons')]);
            }
            $previousStatus = $campaign->status;
            abort_unless($campaign->status !== PromotionCampaign::STATUS_RETIRED, 409, 'A retired campaign cannot change state.');
            if ($target === PromotionCampaign::STATUS_SUSPENDED) {
                abort_unless($campaign->status === PromotionCampaign::STATUS_ACTIVE, 409, 'Only an active campaign can be suspended.');
            }
            if ($target === PromotionCampaign::STATUS_ACTIVE) {
                abort_unless(in_array($campaign->status, [PromotionCampaign::STATUS_DRAFT, PromotionCampaign::STATUS_SUSPENDED], true), 409, 'This campaign cannot be activated from its current state.');
                abort_if($campaign->ends_at?->isPast(), 422, 'An expired campaign cannot be activated.');
            }

            $now = now();
            $campaign->forceFill([
                'status' => $target,
                'version' => $campaign->version + 1,
                'updated_by' => $request->user()->id,
                'activated_at' => $target === PromotionCampaign::STATUS_ACTIVE ? ($campaign->activated_at ?? $now) : $campaign->activated_at,
                'suspended_at' => $target === PromotionCampaign::STATUS_SUSPENDED ? $now : null,
                'retired_at' => $target === PromotionCampaign::STATUS_RETIRED ? $now : null,
            ])->save();

            if ($target === PromotionCampaign::STATUS_RETIRED) {
                Coupon::query()->where('promotion_campaign_id', $campaign->id)->where('status', '!=', Coupon::STATUS_RETIRED)->get()->each(function (Coupon $coupon) use ($request, $organization, $now): void {
                    $previousStatus = $coupon->status;
                    $coupon->forceFill([
                        'status' => Coupon::STATUS_RETIRED,
                        'is_active' => false,
                        'retired_at' => $now,
                        'version' => $coupon->version + 1,
                        'updated_by' => $request->user()->id,
                    ])->save();
                    $this->auditLogger->record($request, 'promotion.coupon.retired', $coupon, [
                        'organization_id' => (int) $organization->id,
                        'promotion_campaign_id' => (int) $coupon->promotion_campaign_id,
                        'previous_status' => $previousStatus,
                        'reason' => 'campaign_retired',
                    ]);
                });
            }

            $this->auditLogger->record($request, 'promotion.campaign.'.$target, $campaign, [
                'organization_id' => (int) $organization->id,
                'previous_status' => $previousStatus,
                'status' => $campaign->status,
                'version' => (int) $campaign->version,
            ]);

            return response()->json(['campaign' => $campaign->fresh()->loadCount('coupons')]);
        });
    }

    private function transitionCoupon(Request $request, int $id, string $target): mixed
    {
        $organization = $this->selectedOrganization($request);
        $this->authorizePromotionManager($request, $organization, requireEntitlement: true);

        return DB::transaction(function () use ($request, $organization, $id, $target) {
            $coupon = Coupon::query()->where('organization_id', $organization->id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($coupon->status === $target) {
                return response()->json(['coupon' => $coupon->load('campaign')]);
            }
            $previousStatus = $coupon->status;
            abort_unless($coupon->status !== Coupon::STATUS_RETIRED, 409, 'A retired coupon cannot change state.');
            if ($target === Coupon::STATUS_SUSPENDED) {
                abort_unless($coupon->status === Coupon::STATUS_ACTIVE, 409, 'Only an active coupon can be suspended.');
            }
            if ($target === Coupon::STATUS_ACTIVE) {
                abort_unless(in_array($coupon->status, [Coupon::STATUS_DRAFT, Coupon::STATUS_SUSPENDED], true), 409, 'This coupon cannot be activated from its current state.');
                abort_if($coupon->ends_at?->isPast(), 422, 'An expired coupon cannot be activated.');
                if ($coupon->promotion_campaign_id) {
                    $campaign = PromotionCampaign::query()->where('organization_id', $organization->id)->whereKey($coupon->promotion_campaign_id)->lockForUpdate()->firstOrFail();
                    abort_unless($campaign->status === PromotionCampaign::STATUS_ACTIVE, 409, 'Activate the campaign before activating its coupon.');
                }
            }

            $now = now();
            $coupon->forceFill([
                'status' => $target,
                'is_active' => $target === Coupon::STATUS_ACTIVE,
                'version' => $coupon->version + 1,
                'updated_by' => $request->user()->id,
                'activated_at' => $target === Coupon::STATUS_ACTIVE ? ($coupon->activated_at ?? $now) : $coupon->activated_at,
                'suspended_at' => $target === Coupon::STATUS_SUSPENDED ? $now : null,
                'retired_at' => $target === Coupon::STATUS_RETIRED ? $now : null,
            ])->save();
            $this->auditLogger->record($request, 'promotion.coupon.'.$target, $coupon, [
                'organization_id' => (int) $organization->id,
                'promotion_campaign_id' => $coupon->promotion_campaign_id,
                'previous_status' => $previousStatus,
                'status' => $coupon->status,
                'version' => (int) $coupon->version,
            ]);

            return response()->json(['coupon' => $coupon->fresh('campaign')]);
        });
    }

    private function validatedCoupon(Request $request, bool $partial = false, ?Coupon $existing = null): array
    {
        $prefix = $partial ? 'sometimes|' : '';
        $codeRule = $prefix.'required|string|max:40';
        if ($partial && $existing) {
            $codeRule .= '|unique:coupons,code,'.$existing->id;
        } else {
            $codeRule .= '|unique:coupons,code';
        }

        return $request->validate([
            'code' => $codeRule,
            'discount_type' => [$prefix.'required', Rule::in(['fixed', 'percent'])],
            'discount_value' => $prefix.'required|integer|min:1',
            'max_discount' => $prefix.'nullable|integer|min:1',
            'min_spend' => $prefix.'nullable|integer|min:0',
            'usage_limit' => $prefix.'nullable|integer|min:1',
            'starts_at' => $prefix.'nullable|date',
            'ends_at' => $prefix.'nullable|date',
            'venue_id' => $prefix.'nullable|integer',
            'court_id' => $prefix.'nullable|integer',
            'can_combine_with_membership' => $prefix.'boolean',
            'metadata' => $prefix.'nullable|array',
            'promotion_campaign_id' => $prefix.'nullable|integer',
            'campaign_venue_id' => $prefix.'nullable|integer',
            'campaign_court_id' => $prefix.'nullable|integer',
            'campaign_name' => $prefix.'nullable|string|max:120',
            'campaign_description' => $prefix.'nullable|string|max:4000',
            'eligibility_group' => [$prefix.'nullable', Rule::in([Coupon::GROUP_ALL, Coupon::GROUP_FIRST_TIME, Coupon::GROUP_RETURNING])],
            'campaign_starts_at' => $prefix.'nullable|date',
            'campaign_ends_at' => $prefix.'nullable|date',
            'campaign_usage_limit' => $prefix.'nullable|integer|min:1',
            'campaign_min_spend' => $prefix.'nullable|integer|min:0',
            'campaign_can_combine_with_membership' => $prefix.'nullable|boolean',
        ]);
    }

    private function validatedCampaign(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : '';

        return $request->validate([
            'name' => $prefix.'required|string|max:120',
            'description' => $prefix.'nullable|string|max:4000',
            'eligibility_group' => [$prefix.'nullable', Rule::in([Coupon::GROUP_ALL, Coupon::GROUP_FIRST_TIME, Coupon::GROUP_RETURNING])],
            'starts_at' => $prefix.'nullable|date',
            'ends_at' => $prefix.'nullable|date',
            'venue_id' => $prefix.'nullable|integer',
            'court_id' => $prefix.'nullable|integer',
            'usage_limit' => $prefix.'nullable|integer|min:1',
            'min_spend' => $prefix.'nullable|integer|min:0',
            'can_combine_with_membership' => $prefix.'nullable|boolean',
        ]);
    }

    private function assertLocationScope(
        Organization $organization,
        ?int $venueId,
        ?int $courtId,
        string $venueField = 'venue_id',
        string $courtField = 'court_id',
    ): void {
        if ($venueId !== null && ! Venue::query()->where('organization_id', $organization->id)->whereKey($venueId)->exists()) {
            throw ValidationException::withMessages([$venueField => 'The selected venue does not belong to this organization.']);
        }

        if ($courtId === null) {
            return;
        }

        $court = Court::query()->with('venue')->whereKey($courtId)->first();
        if (! $court || ! $court->venue || (int) $court->venue->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages([$courtField => 'The selected court does not belong to this organization.']);
        }

        if ($venueId !== null && (int) $court->venue_id !== $venueId) {
            throw ValidationException::withMessages([$courtField => 'The selected court does not belong to the selected venue.']);
        }
    }

    private function assertCouponScopeWithinCampaign(
        ?int $couponVenueId,
        ?int $couponCourtId,
        ?int $campaignVenueId,
        ?int $campaignCourtId,
    ): void {
        if ($campaignVenueId !== null && $couponVenueId !== null && $couponVenueId !== $campaignVenueId) {
            throw ValidationException::withMessages(['venue_id' => 'A coupon cannot target a venue outside its campaign scope.']);
        }

        if ($campaignCourtId !== null && $couponCourtId !== null && $couponCourtId !== $campaignCourtId) {
            throw ValidationException::withMessages(['court_id' => 'A coupon cannot target a court outside its campaign scope.']);
        }

        if ($couponCourtId !== null && $campaignVenueId !== null
            && (int) Court::query()->whereKey($couponCourtId)->value('venue_id') !== $campaignVenueId) {
            throw ValidationException::withMessages(['court_id' => 'A coupon court must remain inside its campaign venue.']);
        }

        if ($couponVenueId !== null && $campaignCourtId !== null
            && (int) Court::query()->whereKey($campaignCourtId)->value('venue_id') !== $couponVenueId) {
            throw ValidationException::withMessages(['venue_id' => 'A coupon venue must contain its campaign court.']);
        }
    }

    private function selectedOrganization(Request $request): Organization
    {
        $id = $request->header('X-Organization-ID')
            ?? $request->header('X-Organization-Id')
            ?? $request->query('organization_id')
            ?? $request->input('organization_id');
        $organization = $id
            ? Organization::query()->find($id)
            : ($this->tenantContext->getOrganization() ?? $request->user()?->organizations()->where('organizations.status', 'active')->first());
        abort_unless($organization && $organization->status === 'active', 400, 'Organization context required.');

        return $organization;
    }

    private function authorizePromotionManager(Request $request, Organization $organization, bool $requireEntitlement = false): void
    {
        $user = $request->user();
        // BR-21: staff hold a fixed permission set without pricing, so promotions stay with the owner.
        abort_unless($user && $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]), 403, 'Pricing permission is required to manage promotions.');

        if ($requireEntitlement) {
            $entitlement = app(EvaluatePlatformEntitlement::class)->execute((int) $organization->id);
            abort_unless($entitlement['is_valid'] && $entitlement['can_take_bookings'], 403, 'An active platform subscription is required to manage promotions.');
        }
    }

    private function assertRange(?string $startsAt, ?string $endsAt, string $field): void
    {
        if ($startsAt && $endsAt && Carbon::parse($endsAt)->lt(Carbon::parse($startsAt))) {
            throw ValidationException::withMessages([$field => 'The end date must be on or after the start date.']);
        }
    }
}
