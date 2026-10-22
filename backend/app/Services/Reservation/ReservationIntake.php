<?php

namespace App\Services\Reservation;

use App\Domain\Pricing\DTOs\RentalQuote;
use App\Domain\Pricing\DTOs\VoucherChoice;
use App\Models\Court;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The checks every new booking passes before it is inserted, shared by checkout and hold (design D3–D7):
 * booker phone, release of overdue holds on the locked court, the amount the customer agreed to, the payment
 * deadline and the booker snapshot.
 */
class ReservationIntake
{
    /** BR-79: a payable hold lasts at most ten minutes, whatever the venue policy says. */
    public const PAYMENT_WINDOW_MINUTES = 10;

    public function __construct(private readonly HeldReservationRelease $release) {}

    /** Refuse an account without a valid Vietnamese phone before anything is locked or held. */
    public function assertBookerPhone(User $user): void
    {
        if (preg_match(User::PHONE_PATTERN, (string) $user->phone) !== 1) {
            abort(response()->json([
                'message' => 'Vui lòng cập nhật số điện thoại trước khi đặt sân.',
                'code' => 'PHONE_REQUIRED',
            ], 422));
        }
    }

    /**
     * The one interval a customer books (design D14): whole or half hours of venue time within one play date, the
     * end at most its closing midnight, at least 30 minutes, not started yet. Anything else is 422 INVALID_BOOKING_TIME.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bookingInterval(Court $court, mixed $startsAt, mixed $endsAt): array
    {
        $zone = $court->loadMissing('venue')->venue?->timezone ?: config('app.timezone');
        try {
            $start = CarbonImmutable::parse((string) $startsAt, $zone)->setTimezone($zone);
            $end = CarbonImmutable::parse((string) $endsAt, $zone)->setTimezone($zone);
        } catch (\Throwable) {
            $this->refuseBookingTime();
        }
        $onGrid = fn (CarbonImmutable $time): bool => $time->minute % 30 === 0 && $time->second === 0 && $time->microsecond === 0;
        if (! $onGrid($start) || ! $onGrid($end) || $end->getTimestamp() - $start->getTimestamp() < 1800
            || $end->greaterThan($start->startOfDay()->addDay()) || $start->isPast()) {
            $this->refuseBookingTime();
        }

        return [$start, $end];
    }

    /** Re-check a quoted interval, first start to last end, under the allocation lock (design D14). */
    public function recheckBookingTime(Court $court, PriceQuote $quote): void
    {
        $slots = collect((array) $quote->slot_snapshot);
        $this->bookingInterval($court, $slots->first()['starts_at'] ?? null, $slots->last()['ends_at'] ?? null);
    }

    /** Also the answer to a request that lists start times instead of one interval. */
    public function refuseBookingTime(): never
    {
        abort(response()->json([
            'message' => 'Giờ đặt phải theo mốc 30 phút, dài ít nhất 30 phút, trong cùng một ngày và chưa bắt đầu.',
            'code' => 'INVALID_BOOKING_TIME',
        ], 422));
    }

    /** The voucher a booking request names (design D17); the request rules refuse naming both. */
    public function voucherChoice(array $data): ?VoucherChoice
    {
        if (! empty($data['voucher_program_id'])) {
            return VoucherChoice::program((int) $data['voucher_program_id']);
        }
        $code = trim((string) ($data['voucher_code'] ?? ''));

        return $code === '' ? null : VoucherChoice::code($code);
    }

    /** The applied voucher's end on the app clock, which bounds the booking's payment deadline (design D17); null without one. */
    public function voucherEndsAt(?RentalQuote $applied): ?Carbon
    {
        $endsAt = $applied?->snapshot['voucher']['ends_at'] ?? null;

        return $endsAt === null ? null : Carbon::parse($endsAt)->setTimezone(config('app.timezone'));
    }

    /** @return array{name: string, phone: string, email: string} */
    public function booker(User $user): array
    {
        return ['name' => (string) $user->name, 'phone' => (string) $user->phone, 'email' => (string) $user->email];
    }

    /**
     * Expire held bookings past their payment deadline that overlap the selection on the locked court, exactly as
     * the expiry job would, so neither the overlap check nor the store constraint counts them.
     *
     * @param  array<int, array{start: \DateTimeInterface|string, end: \DateTimeInterface|string}>  $intervals
     */
    public function releaseOverdueHolds(Court $court, array $intervals): void
    {
        $candidates = Reservation::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('status', 'held')
            ->where('hold_expires_at', '<=', now())
            ->whereHas('slots', fn (Builder $slots) => $slots
                ->withoutGlobalScope('tenant_isolation')
                ->where('court_id', $court->id)
                ->where('status', 'held')
                ->where(function (Builder $overlap) use ($intervals): void {
                    foreach ($intervals as $interval) {
                        $overlap->orWhere(fn (Builder $slot) => $slot
                            ->where('starts_at', '<', Carbon::parse($interval['end'])->utc())
                            ->where('ends_at', '>', Carbon::parse($interval['start'])->utc()));
                    }
                }))
            ->orderBy('id')
            ->get(['id', 'code']);

        foreach ($candidates as $candidate) {
            $locked = $this->release->lock($candidate);
            if ($locked && $locked->hold_expires_at?->lessThanOrEqualTo(now()) && $this->release->releaseHeld($locked, 'expired', 'payment_deadline')) {
                DB::afterCommit(fn () => $this->release->notifyPaymentDeadlinePassed($locked));
            }
        }
    }

    /**
     * Refuse an amount payable, recalculated at submission, above what the customer was shown (409 PRICE_CHANGED
     * with the new amounts). An equal or lower amount goes ahead at the recalculated value.
     */
    public function assertAgreedAmount(int $expectedTotal, int $rental, int $total): void
    {
        if ($total > $expectedTotal) {
            abort(response()->json([
                'message' => 'Giá đã thay đổi. Vui lòng xem lại số tiền mới trước khi đặt.',
                'code' => 'PRICE_CHANGED',
                'rental' => $rental,
                'discount' => $rental - $total,
                'total' => $total,
            ], 409));
        }
    }

    /**
     * Earliest of the hold time plus ten minutes, the booking start (design D3) and the applied voucher's end (module 3
     * D17); 422 PAYMENT_WINDOW_CLOSED when that leaves no time to pay.
     */
    public function paymentDeadline(PriceQuote $quote, ?RentalQuote $applied = null): Carbon
    {
        $deadline = now()->addMinutes(self::PAYMENT_WINDOW_MINUTES);
        $startsAt = collect((array) $quote->slot_snapshot)->map(fn (array $slot): Carbon => Carbon::parse($slot['starts_at']))->min();
        if ($startsAt !== null && $startsAt->lessThan($deadline)) {
            // The datetime cast stores the clock as-is, so keep the app timezone (slot times come back in UTC).
            $deadline = Carbon::instance($startsAt)->setTimezone($deadline->getTimezone());
        }
        $voucherEndsAt = $this->voucherEndsAt($applied);
        if ($voucherEndsAt !== null && $voucherEndsAt->lessThan($deadline)) {
            $deadline = $voucherEndsAt;
        }
        if ($deadline->lessThanOrEqualTo(now())) {
            abort(response()->json([
                'message' => 'Đã hết thời gian thanh toán cho khung giờ này. Vui lòng chọn giờ khác.',
                'code' => 'PAYMENT_WINDOW_CLOSED',
            ], 422));
        }

        return $deadline;
    }

    /** Venue policy recorded on a direct-payment booking; it no longer sets the payment deadline (design D3). */
    public function bookingPolicySnapshot(Venue $venue): array
    {
        $policy = $venue->currentBookingPolicy()->first();
        abort_unless($policy instanceof VenueBookingPolicy, 409, 'A captured venue booking policy is required for direct payment.');

        return [
            'version' => (int) $policy->version,
            'captured_at' => now()->toIso8601String(),
            'settings' => (array) $policy->settings,
        ];
    }
}
