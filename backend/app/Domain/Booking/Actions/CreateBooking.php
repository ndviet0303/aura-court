<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\DTOs\CreateBookingData;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Notifications\AuraBookingNotification;
use App\Services\DiscountService;
use App\Services\WaitlistService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public function __construct(
        private readonly DiscountService $discounts,
        private readonly WaitlistService $waitlists,
    ) {}

    public function execute(CreateBookingData $data): Booking
    {
        $startsAt = $data->slots;
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

        $court = Court::query()->whereKey($data->courtId)->where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        $userId = $data->userId;
        $couponCode = $data->couponCode;

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
}
