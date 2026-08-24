<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Notifications\AuraBookingNotification;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private readonly DiscountService $discounts,
        private readonly WaitlistService $waitlists,
    ) {}

    public function create(int $userId, int $courtId, array $startsAt, ?string $couponCode = null): Booking
    {
        if (count($startsAt) < 1 || count($startsAt) > 12) {
            throw ValidationException::withMessages(['slots' => 'Chọn từ 1 đến 12 slot.']);
        }

        $zone = config('app.timezone');
        $local = collect($startsAt)->map(function (string $value) use ($zone) {
            try {
                $time = Carbon::parse($value)->setTimezone($zone);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['slots' => 'Thời gian slot không hợp lệ.']);
            }

            if ($time->minute !== 0 || $time->second !== 0) {
                throw ValidationException::withMessages(['slots' => 'Slot phải bắt đầu đúng giờ.']);
            }
            if ($time->isPast()) {
                throw ValidationException::withMessages(['slots' => 'Không thể đặt slot trong quá khứ.']);
            }

            return $time;
        })->sort()->values();
        if ($local->unique(fn (Carbon $time) => $time->toIso8601String())->count() !== $local->count()) {
            throw ValidationException::withMessages(['slots' => 'Không được chọn slot trùng nhau.']);
        }
        for ($i = 1; $i < $local->count(); $i++) {
            if (! $local[$i]->equalTo($local[$i - 1]->copy()->addHour())) {
                throw ValidationException::withMessages(['slots' => 'Các slot phải liên tiếp trên cùng sân.']);
            }
        }

        $court = Court::query()->whereKey($courtId)->where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();
        $booking = null;
        try {
            $booking = DB::transaction(function () use ($userId, $court, $local, $couponCode) {
                $prices = $court->priceSlots()->where('is_active', true)->get();
                $blocks = $court->blocks()->where(function ($query) use ($local) {
                    $query->where('starts_at', '<', $local->last()->copy()->addHour()->utc())
                        ->where('ends_at', '>', $local->first()->copy()->utc());
                })->exists();
                abort_if($blocks, 409, 'Sân đang bị khóa trong khoảng thời gian đã chọn.');

                $resolved = $local->map(function (Carbon $time) use ($prices) {
                    $price = $prices->first(fn ($slot) => $slot->weekday === $time->dayOfWeekIso
                        && substr($slot->starts_at, 0, 5) === $time->format('H:i')
                        && substr($slot->ends_at, 0, 5) === $time->copy()->addHour()->format('H:i'));
                    if (! $price) {
                        throw ValidationException::withMessages(['slots' => "Slot {$time->format('H:i')} chưa mở bán."]);
                    }

                    return [$time, (int) $price->price];
                });

                $subtotal = $resolved->sum(fn ($row) => $row[1]);
                $discount = $this->discounts->reserve($userId, $subtotal, $couponCode);
                $booking = Booking::create([
                    'code' => 'BK'.strtoupper(Str::random(12)),
                    'user_id' => $userId,
                    'court_id' => $court->id,
                    'coupon_id' => $discount['coupon']?->id,
                    'total_amount' => $discount['total'],
                    'discount_amount' => $discount['discount'],
                    'membership_discount_amount' => $discount['membership_discount'],
                    'status' => 'pending_payment',
                    'payment_expires_at' => now()->addMinutes(15),
                ]);

                foreach ($resolved as [$time, $price]) {
                    BookingSlot::create([
                        'booking_id' => $booking->id,
                        'court_id' => $court->id,
                        'starts_at' => $time->copy()->utc(),
                        'ends_at' => $time->copy()->addHour()->utc(),
                        'unit_price' => $price,
                        'status' => 'held',
                    ]);
                }

                return $booking->load(['user', 'court.venue', 'slots', 'coupon']);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'booking_slots_active_unique')
                || (str_contains(strtolower($exception->getMessage()), 'unique constraint') && str_contains($exception->getMessage(), 'booking_slots'))
            ) {
                abort(409, 'Một hoặc nhiều slot vừa được người khác đặt.');
            }
            throw $exception;
        }

        $booking->user->notify(new AuraBookingNotification(
            'booking_created',
            'Lịch đặt sân đã được giữ',
            "Booking {$booking->code} đã được giữ. Vui lòng hoàn tất thanh toán trước ".($booking->payment_expires_at?->setTimezone(config('app.timezone'))->format('H:i d/m')).'.',
            '/payment/'.$booking->code,
        ));

        return $booking;
    }

    public function applyCoupon(Booking $booking, ?string $couponCode): Booking
    {
        abort_unless($booking->status === 'pending_payment', 422, 'Booking không ở trạng thái chờ thanh toán.');
        abort_if($booking->payment_expires_at?->isPast(), 422, 'Phiên thanh toán đã hết hạn.');

        return DB::transaction(function () use ($booking, $couponCode) {
            $booking->loadMissing(['slots', 'coupon']);
            $subtotal = (int) $booking->slots->sum('unit_price');

            if ($booking->coupon_id) {
                $this->discounts->release($booking->coupon);
            }

            $discount = $this->discounts->reserve($booking->user_id, $subtotal, $couponCode);

            $booking->update([
                'coupon_id' => $discount['coupon']?->id,
                'total_amount' => $discount['total'],
                'discount_amount' => $discount['discount'],
                'membership_discount_amount' => $discount['membership_discount'],
            ]);

            return $booking->fresh(['user', 'court.venue', 'slots', 'coupon']);
        });
    }

    public function expirePending(): int
    {
        $result = DB::transaction(function () {
            $bookings = Booking::query()->where('status', 'pending_payment')
                ->where('payment_expires_at', '<', now())->lockForUpdate()->get();
            $slots = [];
            foreach ($bookings as $booking) {
                $booking->loadMissing(['user', 'coupon', 'slots']);
                foreach ($booking->slots->where('status', 'held') as $slot) {
                    $slots[] = [$slot->court_id, $slot->starts_at];
                }
                $booking->update(['status' => 'expired']);
                $booking->slots()->where('status', 'held')->update(['status' => 'expired']);
                if ($booking->coupon_id && ! $booking->coupon_usage_released_at) {
                    $this->discounts->release($booking->coupon);
                    $booking->update(['coupon_usage_released_at' => now()]);
                }
            }

            return ['count' => $bookings->count(), 'bookings' => $bookings, 'slots' => $slots];
        });

        foreach ($result['bookings'] as $booking) {
            $booking->user->notify(new AuraBookingNotification(
                'booking_expired',
                'Phiên giữ sân đã hết hạn',
                "Booking {$booking->code} đã hết thời gian thanh toán và được giải phóng.",
                '/booking',
            ));
        }
        $this->notifyWaitlist($result['slots']);

        return $result['count'];
    }

    public function notifyExpiring(): int
    {
        return DB::transaction(function () {
            $bookings = Booking::query()->with('user')->where('status', 'pending_payment')
                ->whereNull('payment_expiring_notified_at')
                ->whereBetween('payment_expires_at', [now(), now()->addMinutes(5)])
                ->lockForUpdate()->get();
            foreach ($bookings as $booking) {
                $booking->update(['payment_expiring_notified_at' => now()]);
                $booking->user->notify(new AuraBookingNotification(
                    'booking_expiring',
                    'Sắp hết thời gian giữ sân',
                    "Booking {$booking->code} sẽ hết hạn thanh toán trong khoảng 5 phút.",
                    '/payment/'.$booking->code,
                ));
            }

            return $bookings->count();
        });
    }

    public function cancel(Booking $booking, ?string $reason = null): Booking
    {
        if (in_array($booking->status, ['cancelled', 'expired', 'refunded'], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking đã kết thúc.']);
        }
        $firstSlot = $booking->slots()->orderBy('starts_at')->firstOrFail();
        if ($booking->status === 'confirmed' && now()->greaterThan($firstSlot->starts_at->copy()->subHours(2))) {
            abort(409, 'Booking chỉ được hủy trước giờ chơi ít nhất 2 giờ.');
        }

        $slotRefs = $booking->slots()->whereIn('status', ['held', 'confirmed'])->get()->map(fn (BookingSlot $slot) => [$slot->court_id, $slot->starts_at])->all();
        $wasPending = $booking->status === 'pending_payment';
        $result = DB::transaction(function () use ($booking, $reason, $wasPending) {
            $newStatus = $booking->status === 'confirmed' ? 'refund_pending' : 'cancelled';
            $booking->update(['status' => $newStatus, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $booking->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => 'cancelled']);
            if ($newStatus === 'refund_pending') {
                $booking->refunds()->firstOrCreate(['status' => 'pending'], ['amount' => $booking->total_amount]);
            }
            if ($wasPending && $booking->coupon_id && ! $booking->coupon_usage_released_at) {
                $this->discounts->release($booking->coupon);
                $booking->update(['coupon_usage_released_at' => now()]);
            }

            return $booking->fresh(['court', 'slots', 'refunds']);
        });
        $this->notifyWaitlist($slotRefs);

        return $result;
    }

    public function cancelSlots(Booking $booking, array $slotIds, ?string $reason = null): Booking
    {
        $slots = $booking->slots()->whereIn('id', $slotIds)->whereIn('status', ['held', 'confirmed'])->get();
        if ($slots->isEmpty()) {
            throw ValidationException::withMessages(['slots' => 'Không có slot hợp lệ để hủy.']);
        }
        if ($slots->count() === $booking->slots()->whereIn('status', ['held', 'confirmed'])->count()) {
            return $this->cancel($booking, $reason);
        }
        if ($booking->status !== 'confirmed') {
            throw ValidationException::withMessages(['booking' => 'Chỉ booking đã xác nhận mới được hủy từng slot.']);
        }
        foreach ($slots as $slot) {
            if (now()->greaterThan($slot->starts_at->copy()->subHours(2))) {
                abort(409, 'Slot chỉ được hủy trước giờ chơi ít nhất 2 giờ.');
            }
        }

        $slotRefs = $slots->map(fn (BookingSlot $slot) => [$slot->court_id, $slot->starts_at])->all();
        $result = DB::transaction(function () use ($booking, $slots, $reason) {
            $amount = $slots->sum('unit_price');
            $slots->each->update(['status' => 'cancelled']);
            $booking->refunds()->create(['amount' => $amount, 'status' => 'pending', 'note' => $reason]);

            return $booking->fresh(['court', 'slots', 'refunds']);
        });
        $this->notifyWaitlist($slotRefs);

        return $result;
    }

    public function createRecurring(int $userId, int $courtId, array $startsAt, int $weeks): array
    {
        if ($weeks < 2 || $weeks > 12) {
            throw ValidationException::withMessages(['weeks' => 'Số tuần phải từ 2 đến 12.']);
        }
        $base = collect($startsAt)->map(fn (string $value) => Carbon::parse($value))->sort()->values();
        if ($base->isEmpty()) {
            throw ValidationException::withMessages(['slots' => 'Cần ít nhất một slot.']);
        }
        $series = collect();

        DB::transaction(function () use ($userId, $courtId, $base, $weeks, &$series) {
            for ($week = 0; $week < $weeks; $week++) {
                $series->push($this->create($userId, $courtId, $base->map(fn (Carbon $time) => $time->copy()->addWeeks($week)->toIso8601String())->all()));
            }
        });

        return $series->all();
    }

    public function reschedule(Booking $booking, int $courtId, array $startsAt): Booking
    {
        if (! in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking không thể đổi lịch.']);
        }
        $firstSlot = $booking->slots()->orderBy('starts_at')->first();
        if ($booking->status === 'confirmed' && $firstSlot && now()->greaterThan($firstSlot->starts_at->copy()->subHours(2))) {
            abort(409, 'Booking chỉ được đổi trước giờ chơi ít nhất 2 giờ.');
        }

        return DB::transaction(function () use ($booking, $courtId, $startsAt) {
            $replacement = $this->create($booking->user_id, $courtId, $startsAt);
            $this->cancel($booking, 'Đổi lịch sang booking '.$replacement->code);

            return $replacement;
        });
    }

    private function notifyWaitlist(array $slots): void
    {
        foreach ($slots as [$courtId, $startsAt]) {
            $this->waitlists->notifyForSlot($courtId, $startsAt);
        }
    }
}
