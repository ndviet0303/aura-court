<?php

namespace App\Services\Booking;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Court;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Services\Finance\FinancialPostingService;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\ObservabilityService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Promotion\VoucherUsageService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Reservation\ReservationIntake;
use App\Services\Reservation\SlotSelectionPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrdinaryCheckoutService
{
    public function __construct(
        private readonly WalletPaymentService $walletPayments,
        private readonly FinancialPostingService $financials,
        private readonly PriceQuoteService $quotes,
        private readonly SlotSelectionPolicy $slotPolicy,
        private readonly ReservationIntake $intake,
        private readonly VoucherUsageService $voucherUses,
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
                abort_if($user->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
                abort_if($actor === null && ! $user->hasVerifiedEmail(), 403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/auth/email/verification-notification.');
                $this->intake->assertBookerPhone($user);
                $quote = $this->resolveQuote($user->id, $organizationId, $data);
                abort_if($quote->status !== 'open', 409, 'Price quote has already been accepted.');
                abort_if((bool) $quote->expires_at?->isPast(), 409, 'Price quote has expired.');
                // Step-14: re-check the quote on the locked rows; lock the subscription before the court, as quote creation does, so bookings cannot deadlock.
                $verifier = app(VerifyEffectiveBookingEligibility::class);
                $verifier->getEntitlement((int) $quote->organization_id);
                $court = Court::query()->withoutGlobalScope('tenant_isolation')->whereKey($quote->court_id)->lockForUpdate()->firstOrFail();
                $lockedVenue = Venue::query()->whereKey($court->venue_id)->lockForUpdate()->firstOrFail();
                // BR-20/21: staff book for a customer only at a venue assigned to them.
                abort_if($actor !== null && ! $actor->canOperateVenue($lockedVenue), 403, 'Bạn không được giao cơ sở này.');
                $eligibility = $verifier->execute($court->setRelation('venue', $lockedVenue));
                abort_unless($eligibility['eligible'], 409, $eligibility['message'] ?? '');
                $this->intake->recheckBookingTime($court, $quote);
                $intervals = fn (): array => collect((array) $quote->slot_snapshot)->map(fn (array $slotSnapshot): array => [
                    'start' => $slotSnapshot['starts_at'],
                    'end' => $slotSnapshot['ends_at'],
                ])->all();
                $this->intake->releaseOverdueHolds($court, $intervals());
                // The client always sends the amount it showed; internal callers have nothing to compare.
                $expectedTotal = isset($data['expected_total']) ? (int) $data['expected_total'] : null;
                // A voucher is locked and judged before the amounts are compared and the deadline is set (D11, D20).
                $voucher = $this->intake->voucherChoice($data);
                $applied = $voucher === null ? null : $this->quotes->repriceWithVoucher($quote, $court, $voucher);
                if ($voucher === null && $expectedTotal !== null) {
                    $this->quotes->reprice($quote, $court);
                }
                $slotSnapshots = (array) $quote->slot_snapshot;
                $directOwnerPayments = app(FeatureFlagService::class)->enabled('direct_owner_payments', (int) $lockedVenue->organization_id);
                $bookingPolicySnapshot = $directOwnerPayments ? $this->intake->bookingPolicySnapshot($lockedVenue) : null;
                // The rental less the voucher, P = T − D: no coupon, membership discount or points (design D21).
                $netPayable = (int) $quote->total_amount;
                if ($expectedTotal !== null) {
                    $this->intake->assertAgreedAmount($expectedTotal, (int) $quote->subtotal, $netPayable);
                }

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
                $this->slotPolicy->assertAllowed($court, $intervals());
                $holdExpiresAt = $directOwnerPayments && $netPayable > 0 ? $this->intake->paymentDeadline($quote, $applied) : null;

                $metadata = (array) ($data['metadata'] ?? []);
                if ($actor !== null && $actor->id !== $user->id) {
                    $metadata['walk_in'] = true;
                    $metadata['booked_by_staff_id'] = $actor->id;
                    $metadata['booked_by_staff_name'] = $actor->name;
                    $metadata['staff_actor_role'] = $actor->roleInOrganization($organizationId) ?? 'staff';
                }

                $metadata['booker'] = $this->intake->booker($user);
                if ($quote->benefit_snapshot) {
                    $metadata['benefit_snapshot'] = $quote->benefit_snapshot;
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
                    'hold_expires_at' => $holdExpiresAt,
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

                if ($voucher !== null) {
                    // Before payment starts; a booking confirmed here (0đ or legacy wallet) uses it at once (D11).
                    $this->voucherUses->hold($reservation, $voucher);
                    if ($reservation->status === 'confirmed') {
                        $this->voucherUses->consume($reservation, now());
                    }
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
            $quote = $this->quotes->create($userId, $organizationId, (int) ($data['court_id'] ?? 0), $data['starts_at'] ?? null, $data['ends_at'] ?? null);

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
}
