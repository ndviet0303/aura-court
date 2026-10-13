<?php

namespace App\Services\Booking;

use App\Models\Court;
use App\Models\PointLedger;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Models\WalletAccount;
use App\Services\Finance\FinancialPostingService;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\ObservabilityService;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\Loyalty\LoyaltyPolicyService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Reservation\SlotSelectionPolicy;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrdinaryCheckoutService
{
    public function __construct(
        private readonly WalletPaymentService $walletPayments,
        private readonly FinancialPostingService $financials,
        private readonly PriceQuoteService $quotes,
        private readonly QuoteCouponService $quoteCoupons,
        private readonly SlotSelectionPolicy $slotPolicy,
        private readonly LoyaltyPolicyService $loyaltyPolicyService,
        private readonly LoyaltyPointService $loyaltyPointService,
    ) {}

    /**
     * Ordinary checkout from a price quote. A direct-owner organization gets a held
     * reservation awaiting owner payment; a legacy wallet cohort is debited and
     * confirmed atomically with balanced financial postings.
     */
    public function checkout(User $user, int $organizationId, array $data, ?User $actor = null): Reservation
    {
        try {
            return DB::transaction(function () use ($user, $organizationId, $data, $actor): Reservation {
                if ($user->isRestricted()) {
                    abort(403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
                }
                if ($actor === null && ! $user->hasVerifiedEmail()) {
                    abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/auth/email/verification-notification.');
                }
                $quote = $this->resolveQuote($user->id, $organizationId, $data);
                if ($quote->status !== 'open') {
                    abort(409, 'Price quote has already been accepted.');
                }
                if ($quote->expires_at?->isPast()) {
                    abort(409, 'Price quote has expired.');
                }
                $court = Court::query()->withoutGlobalScope('tenant_isolation')->whereKey($quote->court_id)->lockForUpdate()->firstOrFail();
                $lockedVenue = Venue::query()->whereKey($court->venue_id)->lockForUpdate()->firstOrFail();
                $couponUsage = $this->quoteCoupons->reserve($quote, $user, $court);
                $directOwnerPayments = app(FeatureFlagService::class)
                    ->enabled('direct_owner_payments', (int) $lockedVenue->organization_id);
                $bookingPolicySnapshot = null;
                $holdExpiresAt = null;
                if ($directOwnerPayments) {
                    ['snapshot' => $bookingPolicySnapshot, 'expires_at' => $holdExpiresAt] = $this->captureBookingPolicy($lockedVenue);
                }
                $payableBeforeLoyalty = (int) $quote->total_amount;

                // Lock user for point and wallet balance serialization
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                // Loyalty points handling
                $loyaltyPoints = ! empty($data['loyalty_points']) ? (int) $data['loyalty_points'] : 0;
                $loyaltyPolicyVersion = ! empty($data['loyalty_policy_version']) ? (int) $data['loyalty_policy_version'] : null;
                $loyaltySnapshot = null;
                $loyaltyDiscount = 0;
                $netPayable = $payableBeforeLoyalty;
                $policy = null;
                $currentPointBalance = 0;

                if ($loyaltyPoints > 0) {
                    $policy = $this->loyaltyPolicyService->getActiveBasePolicy($organizationId);
                    if (! $policy) {
                        abort(422, 'Tổ chức này chưa cấu hình chính sách đổi điểm.');
                    }

                    if ($loyaltyPolicyVersion !== null && (int) $policy->version !== (int) $loyaltyPolicyVersion) {
                        abort(422, 'Chính sách đổi điểm đã thay đổi. Vui lòng tải lại xem trước.');
                    }

                    $currentPointBalance = (int) PointLedger::query()
                        ->where('organization_id', $organizationId)
                        ->where('user_id', $user->id)
                        ->sum('delta');

                    // Only unexpired lots are spendable; the raw running total above
                    // is retained solely for the append-only balance_after column.
                    $availablePoints = $this->loyaltyPointService->availableBalance($organizationId, (int) $user->id);
                    if ($availablePoints < $loyaltyPoints) {
                        abort(422, 'Số dư điểm không đủ để thực hiện quy đổi.');
                    }

                    $ratio = $this->loyaltyPolicyService->extractConversionRatio($policy);
                    $discountCalc = $this->loyaltyPolicyService->calculateDiscount(
                        $loyaltyPoints,
                        $ratio['points_redeemed'],
                        $ratio['reward_value_vnd'],
                        $payableBeforeLoyalty,
                        $availablePoints
                    );

                    $loyaltyDiscount = (int) $discountCalc['discount_vnd'];
                    $netPayable = (int) $discountCalc['remaining_payable_vnd'];

                    $loyaltySnapshot = [
                        'rule_id' => $policy->id,
                        'rule_version' => $policy->version,
                        'points_redeemed' => $ratio['points_redeemed'],
                        'reward_value_vnd' => $ratio['reward_value_vnd'],
                        'points_spent' => $loyaltyPoints,
                        'discount_vnd' => $loyaltyDiscount,
                    ];
                }

                // Direct-cohort holds reserve loyalty points at hold time: the negative
                // PointLedger entry below blocks concurrent spend, is consumed on verified
                // confirmation, and is released on expiry/cancellation. Partial redemption
                // alongside a pending transfer is therefore supported.

                // Get and lock wallet
                $wallet = null;
                if (! $directOwnerPayments) {
                    $wallet = $this->walletPayments->getOrCreateWallet($user->id, $organizationId);
                    $wallet = WalletAccount::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

                    if ($netPayable > 0 && (int) $wallet->balance < $netPayable) {
                        abort(422, 'Số dư ví không đủ để thanh toán lịch đặt. Vui lòng nạp thêm tiền vào ví.');
                    }
                }

                // Check court blocks
                $slotSnapshots = (array) $quote->slot_snapshot;
                if (! empty($slotSnapshots)) {
                    $this->slotPolicy->assertAllowed(
                        $court,
                        collect($slotSnapshots)->map(fn (array $slotSnapshot): array => [
                            'start' => $slotSnapshot['starts_at'],
                            'end' => $slotSnapshot['ends_at'],
                        ])->all(),
                    );
                }

                $metadata = (array) ($data['metadata'] ?? []);
                if ($actor !== null && $actor->id !== $user->id) {
                    $metadata['walk_in'] = true;
                    $metadata['booked_by_staff_id'] = $actor->id;
                    $metadata['booked_by_staff_name'] = $actor->name;
                    $metadata['staff_actor_role'] = $actor->roleInOrganization($organizationId) ?? 'staff';
                }

                $metadata['loyalty_discount_amount'] = $loyaltyDiscount;
                $metadata['loyalty_points_redeemed'] = $loyaltyPoints;
                if ($quote->benefit_snapshot) {
                    $metadata['benefit_snapshot'] = $quote->benefit_snapshot;
                }
                if ($loyaltySnapshot) {
                    $metadata['loyalty_snapshot'] = $loyaltySnapshot;
                }
                if ($bookingPolicySnapshot !== null) {
                    $metadata['booking_policy_snapshot'] = $bookingPolicySnapshot;
                }
                if ($directOwnerPayments && $netPayable === 0) {
                    $metadata['zero_payable'] = true;
                }
                if ($couponUsage !== null) {
                    $metadata['coupon_usage'] = $couponUsage;
                }

                $reservation = Reservation::create([
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'court_id' => $quote->court_id,
                    'price_quote_id' => $quote->id,
                    'code' => $this->uniqueReservationCode(),
                    'status' => $directOwnerPayments && $netPayable > 0 ? 'held' : 'confirmed',
                    'currency' => $quote->currency,
                    'total_amount' => $netPayable,
                    'hold_expires_at' => $directOwnerPayments && $netPayable > 0 ? $holdExpiresAt : null,
                    'confirmed_at' => $directOwnerPayments && $netPayable > 0 ? null : now(),
                    'metadata' => $metadata,
                    'payment_model' => $directOwnerPayments ? 'direct_owner_v1' : 'legacy_wallet_v1',
                ]);

                foreach ($slotSnapshots as $slotSnapshot) {
                    $slot = ReservationSlot::create([
                        'organization_id' => $organizationId,
                        'reservation_id' => $reservation->id,
                        'court_id' => $quote->court_id,
                        'starts_at' => $slotSnapshot['starts_at'],
                        'ends_at' => $slotSnapshot['ends_at'],
                        'unit_price' => $slotSnapshot['unit_price'],
                        'status' => $directOwnerPayments && $netPayable > 0 ? 'held' : 'confirmed',
                        'snapshot' => $slotSnapshot,
                    ]);

                    ReservationEntitlement::create([
                        'organization_id' => $organizationId,
                        'reservation_id' => $reservation->id,
                        'reservation_slot_id' => $slot->id,
                        'user_id' => $user->id,
                        'court_id' => $quote->court_id,
                        'starts_at' => $slot->starts_at,
                        'ends_at' => $slot->ends_at,
                        'amount' => $slot->unit_price,
                        'status' => $directOwnerPayments && $netPayable > 0 ? 'held' : 'active',
                    ]);
                }

                // Debit PointLedger if points redeemed
                if ($loyaltyPoints > 0 && isset($policy)) {
                    PointLedger::create([
                        'organization_id' => $organizationId,
                        'user_id' => $user->id,
                        'loyalty_rule_id' => $policy->id,
                        'delta' => -$loyaltyPoints,
                        'balance_after' => $currentPointBalance - $loyaltyPoints,
                        'source_type' => 'reservation',
                        'source_id' => $reservation->id,
                        'metadata' => [
                            'reservation_code' => $reservation->code,
                            'rule_version' => $policy->version,
                            'points_spent' => $loyaltyPoints,
                            'discount_vnd' => $loyaltyDiscount,
                        ],
                    ]);
                }

                // Debit wallet if netPayable > 0
                if ($netPayable > 0 && ! $directOwnerPayments) {
                    $walletTx = $this->walletPayments->debit(
                        $wallet,
                        $netPayable,
                        'reservation_payment',
                        $reservation->id,
                        $reservation->code
                    );

                    // Post balanced financial posting
                    $this->financials->postWalletReservationPayment($reservation, $netPayable, $wallet->id, $walletTx->id);
                }

                // Update quote to accepted
                $quote->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'accepted_snapshot' => [
                        'quote_token' => $quote->quote_token,
                        'rule_version' => $quote->rule_version,
                        'slot_snapshot' => $quote->slot_snapshot,
                        'subtotal' => $quote->subtotal,
                        'total_amount' => $quote->total_amount,
                        'benefit_snapshot' => $quote->benefit_snapshot,
                        'loyalty_snapshot' => $loyaltySnapshot,
                        'net_payable' => $netPayable,
                    ],
                ]);

                if ($directOwnerPayments && $netPayable > 0) {
                    app(ReservationPaymentService::class)->initiate($reservation, $user);
                }

                return $reservation->load(['court.venue', 'quote', 'slots', 'entitlements']);
            });
        } catch (QueryException $exception) {
            $msg = strtolower($exception->getMessage());
            if (Str::contains($msg, ['reservation_entitlements_active_unique', 'reservation_slots_no_overlap'])
                || (str_contains($msg, 'reservation_entitlements') && str_contains($msg, 'unique'))
            ) {
                app(ObservabilityService::class)->metric('reservation.overbooking_conflict');
                abort(409, 'The selected court-time is no longer available.');
            }
            throw $exception;
        }
    }

    private function resolveQuote(int $userId, int $organizationId, array $data): PriceQuote
    {
        $query = PriceQuote::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('user_id', $userId)->where('organization_id', $organizationId)
            ->where('status', 'open');

        if (! empty($data['quote_token'])) {
            $query->where('quote_token', $data['quote_token']);
        } elseif (! empty($data['quote_id'])) {
            $query->whereKey($data['quote_id']);
        } else {
            $quote = $this->quotes->create(
                $userId,
                $organizationId,
                (int) ($data['court_id'] ?? 0),
                (array) ($data['slots'] ?? []),
                $data['coupon_code'] ?? null,
            );

            return PriceQuote::query()->withoutGlobalScope('tenant_isolation')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
        }

        return $query->lockForUpdate()->firstOrFail();
    }

    private function uniqueReservationCode(): string
    {
        do {
            $code = 'RSV'.strtoupper(Str::random(13));
        } while (Reservation::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @return array{snapshot: array, expires_at: Carbon}
     */
    private function captureBookingPolicy(Venue $venue): array
    {
        $policy = $venue->currentBookingPolicy()->first();
        abort_unless($policy instanceof VenueBookingPolicy, 409, 'A captured venue booking policy is required for direct payment.');
        $settings = (array) $policy->settings;
        $holdMinutes = (int) data_get($settings, 'booking.hold_ttl_minutes', 0);
        abort_unless($holdMinutes >= 1 && $holdMinutes <= 1440, 409, 'The venue payment hold duration is invalid.');

        return [
            'snapshot' => [
                'version' => (int) $policy->version,
                'captured_at' => now()->toIso8601String(),
                'settings' => $settings,
            ],
            'expires_at' => now()->addMinutes($holdMinutes),
        ];
    }
}
