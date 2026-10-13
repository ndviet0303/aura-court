<?php

namespace App\Services\Booking;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Domain\Booking\DTOs\CreateBookingData;
use App\Domain\Partner\Services\PartnerRolloutService;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\IdempotencyRecord;
use App\Models\Organization;
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
use App\Services\Reservation\LegacyBookingBackfillService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Reservation\SlotSelectionPolicy;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrdinaryCheckoutService
{
    public function __construct(
        private readonly CourtRepositoryInterface $courts,
        private readonly CourtBlockRepositoryInterface $courtBlocks,
        private readonly BookingRepositoryInterface $bookings,
        private readonly DiscountService $discounts,
        private readonly WaitlistService $waitlists,
        private readonly WalletPaymentService $walletPayments,
        private readonly FinancialPostingService $financials,
        private readonly LegacyBookingBackfillService $backfill,
        private readonly PriceQuoteService $quotes,
        private readonly VenueOperatingCalendar $operatingCalendar,
        private readonly SlotSelectionPolicy $slotPolicy,
        private readonly DurableNotificationServiceInterface $durableNotification,
        private readonly LoyaltyPolicyService $loyaltyPolicyService,
        private readonly LoyaltyPointService $loyaltyPointService,
    ) {}

    /**
     * Shared Ordinary V1 Wallet-Funded Checkout.
     * Preserves exact V1 response projection contracts while enforcing
     * atomic inventory, wallet balance verification, debit, and balanced financial posting.
     */
    public function checkoutV1(CreateBookingData $data): Booking
    {
        $startsAt = $data->slots;
        if (count($startsAt) < 1 || count($startsAt) > 12) {
            throw ValidationException::withMessages(['slots' => 'Chọn từ 1 đến 12 giờ.']);
        }

        $calendarCourt = Court::with('venue')->findOrFail($data->courtId);
        $zone = $calendarCourt->venue?->timezone ?: config('app.timezone');
        $local = collect($startsAt)->map(function (string $value) use ($zone) {
            try {
                $time = Carbon::parse($value)->setTimezone($zone);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['slots' => 'Giờ bắt đầu không hợp lệ.']);
            }

            if ($time->minute !== 0 || $time->second !== 0) {
                throw ValidationException::withMessages(['slots' => 'Giờ bắt đầu phải tròn giờ, ví dụ 19:00.']);
            }
            if ($time->isPast()) {
                throw ValidationException::withMessages(['slots' => 'Không thể đặt giờ đã qua.']);
            }

            return $time;
        })->sort()->values();

        if ($local->unique(fn (Carbon $time) => $time->toIso8601String())->count() !== $local->count()) {
            throw ValidationException::withMessages(['slots' => 'Không được chọn trùng giờ.']);
        }

        $eligibility = app(VerifyEffectiveBookingEligibility::class);
        $court = $eligibility->assertEligible($data->courtId, publicFacing: true);
        $userId = $data->userId;
        $checkoutUser = User::find($userId);
        if ($checkoutUser && $checkoutUser->isRestricted()) {
            abort(403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        }
        if ($checkoutUser && ! $checkoutUser->hasVerifiedEmail()) {
            abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/v1/auth/email/verification-notification.');
        }
        $couponCode = $data->couponCode;
        $loyaltyPoints = $data->loyaltyPoints;
        $loyaltyPolicyVersion = $data->loyaltyPolicyVersion;
        $idempotencyKey = $data->idempotencyKey;

        if ($loyaltyPoints !== null && $loyaltyPoints > 0 && empty($idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Yêu cầu sử dụng điểm cần cung cấp khóa phân biệt thao tác (Idempotency-Key).',
            ]);
        }

        $orgId = $court->venue?->organization_id;
        $requestHash = null;
        if (! empty($idempotencyKey)) {
            $requestHash = hash('sha256', json_encode([
                'court_id' => $court->id,
                'slots' => $local->map(fn (Carbon $t) => $t->toIso8601String())->all(),
                'coupon_code' => $couponCode,
                'loyalty_points' => $loyaltyPoints,
                'loyalty_policy_version' => $loyaltyPolicyVersion,
            ]));

            $existingRecord = IdempotencyRecord::query()
                ->where('organization_id', $orgId)
                ->where('user_id', $userId)
                ->where('key', $idempotencyKey)
                ->first();

            if ($existingRecord) {
                if (! hash_equals($existingRecord->request_hash, $requestHash)) {
                    abort(409, 'Idempotency-Key was already used with different request content.');
                }
                if ($existingRecord->response_body) {
                    $bookingId = $existingRecord->response_body['id'] ?? null;
                    if ($bookingId) {
                        $existingBooking = Booking::with(['user', 'court.venue', 'slots', 'coupon', 'payments'])->find($bookingId);
                        if ($existingBooking) {
                            return $existingBooking;
                        }
                    }
                }
            }
        }

        try {
            $booking = DB::transaction(function () use (
                $userId, $court, $local, $couponCode, $loyaltyPoints, $loyaltyPolicyVersion, $idempotencyKey, $requestHash, $orgId
            ) {
                Court::query()->whereKey($court->id)->lockForUpdate()->first();
                $lockedVenue = Venue::query()
                    ->whereKey($court->venue_id)
                    ->lockForUpdate()
                    ->first();
                $directOwnerPayments = app(FeatureFlagService::class)
                    ->enabled('direct_owner_payments', (int) $lockedVenue->organization_id);
                $bookingPolicySnapshot = null;
                $holdExpiresAt = null;
                if ($directOwnerPayments) {
                    ['snapshot' => $bookingPolicySnapshot, 'expires_at' => $holdExpiresAt] = $this->captureBookingPolicy($lockedVenue);
                }
                $rollout = app(PartnerRolloutService::class);
                $enforcePublication = $rollout->shouldEnforce($lockedVenue->organization_id, 'publication');

                abort_unless($lockedVenue && $lockedVenue->is_active && ($enforcePublication ? $lockedVenue->isPublished() : true), 409, 'Cơ sở thể thao vừa thay đổi trạng thái hoặc ngừng hoạt động.');

                $lockedOrg = Organization::query()
                    ->whereKey($lockedVenue->organization_id)
                    ->lockForUpdate()
                    ->first();
                abort_unless($lockedOrg && $lockedOrg->status === 'active' && ($enforcePublication ? $lockedOrg->isApprovedPartner() : true), 409, 'Câu lạc bộ vừa bị tạm ngừng hoạt động.');

                $this->slotPolicy->assertAllowed(
                    $court,
                    $local->map(fn (Carbon $time): array => [
                        'start' => $time,
                        'end' => $time->copy()->addHour(),
                    ])->all(),
                );

                // Lock user for point and wallet balance serialization
                $lockedUser = User::whereKey($userId)->lockForUpdate()->firstOrFail();

                $priceSnapshots = $this->quotes->priceSnapshots($court, $local->all(), 60);
                $resolved = $local->map(fn (Carbon $time, int $index) => [$time, (int) $priceSnapshots[$index]['unit_price']]);

                $subtotal = $resolved->sum(fn ($row) => $row[1]);
                $discount = $this->discounts->reserve($userId, $subtotal, $couponCode, $orgId, (int) $court->venue_id, (int) $court->id);
                $payableBeforeLoyalty = (int) $discount['total'];

                // Process Loyalty Points
                $loyaltySnapshot = null;
                $loyaltyDiscount = 0;
                $netPayable = $payableBeforeLoyalty;
                $policy = null;
                $ratio = null;
                $currentPointBalance = 0;

                if ($loyaltyPoints !== null && $loyaltyPoints > 0) {
                    $policy = $this->loyaltyPolicyService->getActiveBasePolicy($orgId);
                    if (! $policy) {
                        throw ValidationException::withMessages([
                            'loyalty_points' => 'Tổ chức này chưa cấu hình chính sách đổi điểm.',
                        ]);
                    }

                    if ($loyaltyPolicyVersion !== null && (int) $policy->version !== (int) $loyaltyPolicyVersion) {
                        throw ValidationException::withMessages([
                            'loyalty_policy_version' => 'Chính sách đổi điểm đã thay đổi. Vui lòng tải lại xem trước.',
                        ]);
                    }

                    $currentPointBalance = (int) PointLedger::query()
                        ->where('organization_id', $orgId)
                        ->where('user_id', $userId)
                        ->sum('delta');

                    // Only unexpired lots are spendable; the raw running total above
                    // is retained solely for the append-only balance_after column.
                    $availablePoints = $this->loyaltyPointService->availableBalance($orgId, (int) $userId);
                    if ($availablePoints < $loyaltyPoints) {
                        throw ValidationException::withMessages([
                            'loyalty_points' => 'Số dư điểm không đủ để thực hiện quy đổi.',
                        ]);
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
                // PointLedger entry above blocks concurrent spend, is consumed on verified
                // confirmation, and is released on expiry/cancellation. Partial redemption
                // alongside a pending transfer is therefore supported.

                // Legacy cohorts still use their wallet; direct cohorts never create or debit one.
                $wallet = null;
                if (! $directOwnerPayments) {
                    $wallet = $this->walletPayments->getOrCreateWallet($userId, $orgId);
                    $wallet = WalletAccount::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

                    if ($netPayable > 0 && (int) $wallet->balance < $netPayable) {
                        throw ValidationException::withMessages([
                            'wallet' => 'Số dư ví không đủ để thanh toán đặt sân. Vui lòng nạp thêm tiền vào ví.',
                            'balance' => 'Số dư hiện tại: '.number_format((int) $wallet->balance, 0, ',', '.').'₫',
                            'shortfall' => 'Còn thiếu: '.number_format($netPayable - (int) $wallet->balance, 0, ',', '.').'₫',
                        ]);
                    }
                }

                $priceSnapshot = $resolved->map(fn ($item, int $index) => [
                    'starts_at' => $item[0]->toIso8601String(),
                    'ends_at' => $item[0]->copy()->addHour()->toIso8601String(),
                    'unit_price' => (int) $item[1],
                    'rule' => $priceSnapshots[$index]['rule'],
                ])->all();

                $policySnapshot = [
                    'policy_version' => 'v1',
                    'captured_at' => now()->toIso8601String(),
                    'membership_snapshot' => $discount['membership_snapshot'],
                    'loyalty_snapshot' => $loyaltySnapshot,
                    'venue_booking_policy' => $bookingPolicySnapshot,
                    'coupon_usage_snapshot' => $discount['coupon'] ? [
                        'coupon_id' => (int) $discount['coupon']->id,
                        'coupon_version' => (int) ($discount['coupon']->version ?? 1),
                        'coupon_starts_at' => $discount['coupon']->starts_at?->toIso8601String(),
                        'coupon_ends_at' => $discount['coupon']->ends_at?->toIso8601String(),
                        'coupon_usage_limit' => $discount['coupon']->usage_limit,
                        'campaign_id' => $discount['coupon']->promotion_campaign_id ? (int) $discount['coupon']->promotion_campaign_id : null,
                        'campaign_version' => (int) ($discount['coupon']->campaign?->version ?? 1),
                        'eligibility_group' => $discount['coupon']->campaign?->eligibility_group ?? $discount['coupon']->eligibility_group ?? Coupon::GROUP_ALL,
                        'campaign_starts_at' => $discount['coupon']->campaign?->starts_at?->toIso8601String(),
                        'campaign_ends_at' => $discount['coupon']->campaign?->ends_at?->toIso8601String(),
                        'campaign_usage_limit' => $discount['coupon']->campaign?->usage_limit,
                        'usage_count_reserved' => true,
                        'release_on_full_cancellation' => true,
                        'discount_amount' => (int) $discount['coupon_discount'],
                    ] : null,
                ];

                $metadata = [
                    'venue_id' => $court->venue_id,
                    'organization_id' => $orgId,
                    'venue_name' => $lockedVenue->name,
                    'loyalty_discount_amount' => $loyaltyDiscount,
                    'loyalty_points_redeemed' => $loyaltyPoints ?? 0,
                    'membership_snapshot' => $discount['membership_snapshot'],
                    'loyalty_snapshot' => $loyaltySnapshot,
                    'payment_model' => $directOwnerPayments ? 'direct_owner_v1' : 'legacy_wallet_v1',
                    'booking_policy_snapshot' => $bookingPolicySnapshot,
                    'coupon_usage_snapshot' => $policySnapshot['coupon_usage_snapshot'],
                    'zero_payable' => $directOwnerPayments && $netPayable === 0,
                ];

                $booking = $this->bookings->create([
                    'code' => 'BK'.strtoupper(Str::random(12)),
                    'user_id' => $userId,
                    'court_id' => $court->id,
                    'coupon_id' => $discount['coupon']?->id,
                    'total_amount' => $netPayable,
                    'discount_amount' => $discount['discount'] + $loyaltyDiscount,
                    'membership_discount_amount' => $discount['membership_discount'],
                    'status' => $directOwnerPayments && $netPayable > 0 ? 'pending_payment' : 'confirmed',
                    'payment_model' => $directOwnerPayments ? 'direct_owner_v1' : 'legacy_wallet_v1',
                    'payment_expires_at' => $directOwnerPayments && $netPayable > 0 ? $holdExpiresAt : null,
                    'policy_snapshot' => $policySnapshot,
                    'price_snapshot' => $priceSnapshot,
                    'metadata' => $metadata,
                ]);

                // Debit PointLedger if loyalty points used
                if ($loyaltyPoints !== null && $loyaltyPoints > 0 && isset($policy)) {
                    PointLedger::create([
                        'organization_id' => $orgId,
                        'user_id' => $userId,
                        'loyalty_rule_id' => $policy->id,
                        'delta' => -$loyaltyPoints,
                        'balance_after' => $currentPointBalance - $loyaltyPoints,
                        'source_type' => 'booking',
                        'source_id' => $booking->id,
                        'metadata' => [
                            'booking_code' => $booking->code,
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
                        'booking_payment',
                        $booking->id,
                        $booking->code
                    );

                    $payment = $booking->payments()->create([
                        'provider' => 'wallet',
                        'external_transaction_id' => "wallet-txn-{$walletTx->id}",
                        'amount' => $netPayable,
                        'transfer_code' => $booking->code,
                        'status' => 'paid',
                        'payload' => [
                            'wallet_account_id' => $wallet->id,
                            'wallet_transaction_id' => $walletTx->id,
                            'loyalty_discount' => $loyaltyDiscount,
                        ],
                        'received_at' => now(),
                    ]);

                    $this->financials->postWalletBookingPayment($booking->fresh(['court.venue']), $payment, $wallet->id);
                } elseif ($netPayable === 0) {
                    // A zero-payable booking is confirmed without inventing a bank receipt.
                    $payment = $booking->payments()->create([
                        'provider' => $directOwnerPayments ? 'zero_payable' : 'loyalty_points',
                        'external_transaction_id' => "zero-payable-{$booking->code}",
                        'amount' => 0,
                        'transfer_code' => $booking->code,
                        'status' => 'paid',
                        'payload' => [
                            'fully_discounted' => true,
                            'payment_model' => $directOwnerPayments ? 'direct_owner_v1' : 'legacy_wallet_v1',
                            'loyalty_points_spent' => $loyaltyPoints,
                            'loyalty_discount' => $loyaltyDiscount,
                        ],
                        'received_at' => now(),
                    ]);
                }

                $slotsData = [];
                foreach ($resolved as [$time, $price]) {
                    $slotsData[] = [
                        'court_id' => $court->id,
                        'starts_at' => $time->copy()->utc(),
                        'ends_at' => $time->copy()->addHour()->utc(),
                        'unit_price' => $price,
                        'status' => $directOwnerPayments && $netPayable > 0 ? 'held' : 'confirmed',
                    ];
                }
                $this->bookings->createSlots($booking, $slotsData);

                // Ensure canonical V2 reservation identity mapping is synced
                $mapping = $this->backfill->backfillBooking($booking);
                if ($directOwnerPayments && $netPayable > 0) {
                    $reservation = Reservation::query()->withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
                    app(ReservationPaymentService::class)->initiate($reservation, User::findOrFail($userId));
                }

                if (! empty($idempotencyKey)) {
                    try {
                        IdempotencyRecord::create([
                            'organization_id' => $orgId,
                            'user_id' => $userId,
                            'key' => $idempotencyKey,
                            'command' => 'POST /api/v1/bookings',
                            'request_hash' => $requestHash,
                            'response_status' => 201,
                            'response_body' => ['id' => $booking->id, 'code' => $booking->code],
                            'expires_at' => now()->addHours(24),
                        ]);
                    } catch (QueryException) {
                        // Handled by concurrency catch
                    }
                }

                return $booking->load(['user', 'court.venue', 'slots', 'coupon', 'payments']);
            });
        } catch (QueryException $exception) {
            $msg = strtolower($exception->getMessage());
            if (str_contains($msg, 'booking_slots_active_unique')
                || (str_contains($msg, 'unique') && str_contains($msg, 'booking_slots'))
                || Str::contains($msg, ['reservation_entitlements_active_unique', 'reservation_slots_no_overlap'])
            ) {
                abort(409, 'Một hoặc nhiều giờ bạn chọn vừa được người khác đặt.');
            }
            throw $exception;
        }

        try {
            $isPendingDirectPayment = $booking->payment_model === 'direct_owner_v1' && $booking->status === 'pending_payment';
            $this->durableNotification->notifyBooking(
                $booking,
                $isPendingDirectPayment ? 'payment_pending' : 'payment_confirmed',
                $isPendingDirectPayment ? 'Đặt sân đang chờ thanh toán' : 'Đặt sân thành công',
                $isPendingDirectPayment
                    ? "Booking {$booking->code} tại {$booking->court->name} đang chờ xác minh chuyển khoản."
                    : "Booking {$booking->code} tại {$booking->court->name} đã được thanh toán thành công.",
                '/booking',
            );
        } catch (\Throwable $secondaryException) {
            Log::warning("Secondary booking notification dispatch encountered failure: {$secondaryException->getMessage()}", [
                'booking_code' => $booking->code,
                'exception' => $secondaryException,
            ]);
        }

        return $booking;
    }

    /**
     * Shared Ordinary V2 Wallet-Funded Checkout.
     * Directly creates and confirms a reservation, verifies wallet balance,
     * debits the wallet atomically, and creates balanced financial postings.
     */
    public function checkoutV2(User $user, int $organizationId, array $data, ?User $actor = null): Reservation
    {
        try {
            return DB::transaction(function () use ($user, $organizationId, $data, $actor): Reservation {
                if ($user->isRestricted()) {
                    abort(403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
                }
                if ($actor === null && ! $user->hasVerifiedEmail()) {
                    abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/v1/auth/email/verification-notification.');
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
                || str_contains($msg, 'booking_slots_active_unique')
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
