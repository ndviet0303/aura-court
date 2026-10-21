<?php

namespace App\Services\Loyalty;

use App\Models\Court;
use App\Models\LoyaltyRule;
use App\Models\PriceQuote;
use App\Models\User;
use App\Services\Booking\DiscountService;
use App\Services\Reservation\PriceQuoteService;
use Illuminate\Validation\ValidationException;

class LoyaltyPolicyService
{
    public function __construct(
        private readonly DiscountService $discountService,
        private readonly PriceQuoteService $priceQuoteService,
        private readonly LoyaltyPointService $loyaltyPointService
    ) {}

    /**
     * Resolves the active base redemption policy for an organization.
     * Excludes earn multipliers, drafts, and multiplier rules.
     */
    public function getActiveBasePolicy(int $organizationId): ?LoyaltyRule
    {
        return LoyaltyRule::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('metadata->is_multiplier_rule')
                    ->orWhere('metadata->is_multiplier_rule', false);
            })
            ->where('name', 'not like', '%Multiplier%')
            ->where(function ($q) {
                $q->where('redeem_points_per_100_vnd', '>', 0)
                    ->orWhereNotNull('metadata->points_redeemed');
            })
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Extracts trusted exact points-to-VND conversion ratio.
     * Priority:
     * 1. Exact metadata ['points_redeemed', 'reward_value_vnd']
     * 2. Legacy column redeem_points_per_100_vnd
     * Rejects ambiguous, non-positive or conflicting legacy data.
     *
     * @return array{points_redeemed: int, reward_value_vnd: int}
     */
    public function extractConversionRatio(LoyaltyRule $rule): array
    {
        $meta = $rule->metadata ?? [];

        // Check if metadata has exact ratio parameters
        if (isset($meta['points_redeemed']) || isset($meta['reward_value_vnd'])) {
            $pts = (int) ($meta['points_redeemed'] ?? 0);
            $vnd = (int) ($meta['reward_value_vnd'] ?? 0);

            if ($pts <= 0 || $vnd <= 0) {
                throw ValidationException::withMessages([
                    'loyalty_policy' => 'Chính sách đổi điểm chứa dữ liệu tỷ lệ quy đổi không hợp lệ.',
                ]);
            }

            return [
                'points_redeemed' => $pts,
                'reward_value_vnd' => $vnd,
            ];
        }

        // Legacy column fallback
        $ptsPer100 = (int) ($rule->redeem_points_per_100_vnd ?? 0);
        if ($ptsPer100 > 0) {
            return [
                'points_redeemed' => $ptsPer100,
                'reward_value_vnd' => 100,
            ];
        }

        throw ValidationException::withMessages([
            'loyalty_policy' => 'Chính sách đổi điểm không hợp lệ hoặc dữ liệu không nhất quán.',
        ]);
    }

    /**
     * Exact whole-VND floor conversion calculation.
     * Rejects non-positive points, insufficient balance, zero benefit, or excessive discount.
     *
     * @return array{points_selected: int, discount_vnd: int, remaining_payable_vnd: int}
     */
    public function calculateDiscount(
        int $requestedPoints,
        int $pointsRedeemed,
        int $rewardValueVnd,
        int $payableAmount,
        int $userBalance
    ): array {
        if ($requestedPoints <= 0) {
            throw ValidationException::withMessages([
                'points' => 'Số điểm sử dụng phải là số nguyên dương.',
            ]);
        }

        if ($requestedPoints > $userBalance) {
            throw ValidationException::withMessages([
                'points' => 'Số dư điểm không đủ để thực hiện quy đổi.',
            ]);
        }

        if ($pointsRedeemed <= 0 || $rewardValueVnd <= 0) {
            throw ValidationException::withMessages([
                'loyalty_policy' => 'Tỷ lệ quy đổi điểm không hợp lệ.',
            ]);
        }

        // floor(requested_points * reward_value_vnd / points_redeemed) in whole VND
        $discountVnd = (int) floor(($requestedPoints * $rewardValueVnd) / $pointsRedeemed);

        if ($discountVnd <= 0) {
            throw ValidationException::withMessages([
                'points' => 'Số điểm chọn không tạo ra mức giảm giá hợp lệ.',
            ]);
        }

        if ($discountVnd > $payableAmount) {
            throw ValidationException::withMessages([
                'points' => 'Mức giảm giá từ điểm vượt quá số tiền cần thanh toán.',
            ]);
        }

        $remainingPayable = max(0, $payableAmount - $discountVnd);

        return [
            'points_selected' => $requestedPoints,
            'discount_vnd' => $discountVnd,
            'remaining_payable_vnd' => $remainingPayable,
        ];
    }

    /**
     * Computes the maximum points within user balance that does not exceed payable amount.
     */
    public function calculateMaxUsablePoints(
        int $pointsRedeemed,
        int $rewardValueVnd,
        int $payableAmount,
        int $userBalance
    ): int {
        if ($userBalance <= 0 || $payableAmount <= 0 || $pointsRedeemed <= 0 || $rewardValueVnd <= 0) {
            return 0;
        }

        // points * reward_value_vnd / points_redeemed <= payableAmount
        // points <= payableAmount * points_redeemed / reward_value_vnd
        $maxPointsForPayable = (int) floor(($payableAmount * $pointsRedeemed) / $rewardValueVnd);

        return max(0, min($userBalance, $maxPointsForPayable));
    }

    /**
     * Read-only preview for ordinary booking loyalty selection.
     * Does NOT write to PointLedger, wallet, inventory or reservations.
     *
     * @param array{
     *   court_id?: int,
     *   quote_token?: string,
     *   slots?: array,
     *   coupon_code?: string,
     *   points?: int,
     *   expected_policy_version?: int
     * } $params
     */
    public function preview(User $user, array $params): array
    {
        $organizationId = null;
        $payableAmount = 0;
        $venueId = null;
        $courtId = null;

        // 1. Resolve organization and payable amount authoritatively
        if (! empty($params['quote_token'])) {
            $quote = PriceQuote::query()
                ->where('quote_token', $params['quote_token'])
                ->first();

            if (! $quote) {
                throw ValidationException::withMessages([
                    'quote_token' => 'Báo giá không tồn tại.',
                ]);
            }

            if ((int) $quote->user_id !== (int) $user->id) {
                abort(403, 'Không có quyền truy cập báo giá này.');
            }

            if ($quote->expires_at && $quote->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'quote_token' => 'Báo giá đã hết hạn.',
                ]);
            }

            if (! in_array($quote->status, ['open', 'pending_payment'], true)) {
                throw ValidationException::withMessages([
                    'quote_token' => 'Báo giá không còn khả dụng để thanh toán.',
                ]);
            }

            $organizationId = (int) $quote->organization_id;
            $courtId = (int) $quote->court_id;
            $venueId = (int) Court::query()->whereKey($courtId)->value('venue_id');
            $payableAmount = (int) $quote->total_amount;
        } elseif (! empty($params['court_id'])) {
            $court = Court::query()->with(['venue.organization'])->find($params['court_id']);

            if (! $court) {
                throw ValidationException::withMessages([
                    'court_id' => 'Sân không tồn tại.',
                ]);
            }

            if ($court->status !== Court::STATUS_ACTIVE || ! $court->venue?->isActive() || $court->venue->isSuspended() || ! $court->venue->organization) {
                throw ValidationException::withMessages([
                    'court_id' => 'Sân hoặc cơ sở hiện không khả dụng.',
                ]);
            }

            $organizationId = (int) $court->venue->organization_id;
            $venueId = (int) $court->venue_id;
            $courtId = (int) $court->id;

            if (empty($params['slots']) || ! is_array($params['slots'])) {
                throw ValidationException::withMessages([
                    'slots' => 'Cần chọn giờ chơi để tính giá.',
                ]);
            }

            // Authoritative slot price calculation without reservation or hold
            $subtotal = $this->priceQuoteService->calculateSubtotal($court, $params['slots']);

            $payableAmount = $subtotal;
            if (! empty($params['coupon_code'])) {
                $discountResult = $this->discountService->preview($user, $subtotal, $params['coupon_code'], $organizationId, $venueId, $courtId);
                $payableAmount = (int) ($discountResult['total'] ?? $subtotal);
            }
        } else {
            throw ValidationException::withMessages([
                'court_id' => 'Cần cung cấp court_id hoặc quote_token để tính toán đổi điểm.',
            ]);
        }

        // 2. Resolve user's spendable point balance (excludes expired lots)
        $balance = $this->loyaltyPointService->availableBalance($organizationId, (int) $user->id);

        // 3. Resolve active base redemption policy
        $policy = $this->getActiveBasePolicy($organizationId);

        if (! $policy) {
            if (isset($params['points']) && $params['points'] !== null && $params['points'] !== '' && (int) $params['points'] > 0) {
                throw ValidationException::withMessages([
                    'loyalty_policy' => 'Tổ chức này chưa cấu hình chính sách đổi điểm.',
                ]);
            }

            return [
                'organization_id' => $organizationId,
                'balance' => $balance,
                'policy' => null,
                'points_selected' => 0,
                'discount_vnd' => 0,
                'remaining_payable_vnd' => $payableAmount,
                'max_usable_points' => 0,
            ];
        }

        // Check policy version if client sent expected version
        if (isset($params['expected_policy_version']) && $params['expected_policy_version'] !== null) {
            if ((int) $policy->version !== (int) $params['expected_policy_version']) {
                throw ValidationException::withMessages([
                    'expected_policy_version' => 'Chính sách đổi điểm đã thay đổi. Vui lòng tải lại xem trước.',
                ]);
            }
        }

        $ratio = $this->extractConversionRatio($policy);
        $pointsRedeemed = $ratio['points_redeemed'];
        $rewardValueVnd = $ratio['reward_value_vnd'];

        $maxUsablePoints = $this->calculateMaxUsablePoints(
            $pointsRedeemed,
            $rewardValueVnd,
            $payableAmount,
            $balance
        );

        $pointsSelected = 0;
        $discountVnd = 0;
        $remainingPayableVnd = $payableAmount;

        if (isset($params['points']) && $params['points'] !== null && $params['points'] !== '') {
            if (! is_numeric($params['points']) || (int) $params['points'] != $params['points'] || (int) $params['points'] <= 0) {
                throw ValidationException::withMessages([
                    'points' => 'Số điểm sử dụng phải là số nguyên dương.',
                ]);
            }
            $requested = (int) $params['points'];
            $calc = $this->calculateDiscount(
                $requested,
                $pointsRedeemed,
                $rewardValueVnd,
                $payableAmount,
                $balance
            );
            $pointsSelected = $calc['points_selected'];
            $discountVnd = $calc['discount_vnd'];
            $remainingPayableVnd = $calc['remaining_payable_vnd'];
        }

        return [
            'organization_id' => $organizationId,
            'balance' => $balance,
            'policy' => [
                'id' => $policy->id,
                'version' => $policy->version,
                'points_redeemed' => $pointsRedeemed,
                'reward_value_vnd' => $rewardValueVnd,
            ],
            'points_selected' => $pointsSelected,
            'discount_vnd' => $discountVnd,
            'remaining_payable_vnd' => $remainingPayableVnd,
            'max_usable_points' => $maxUsablePoints,
        ];
    }
}
