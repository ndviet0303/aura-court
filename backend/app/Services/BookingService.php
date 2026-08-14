<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function create(int $userId, int $courtId, array $startsAt): Booking
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
            $booking = DB::transaction(function () use ($userId, $court, $local) {
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

                $booking = Booking::create([
                    'code' => 'BK'.strtoupper(Str::random(12)),
                    'user_id' => $userId,
                    'court_id' => $court->id,
                    'total_amount' => $resolved->sum(fn ($row) => $row[1]),
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

                return $booking->load(['court.venue', 'slots']);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'booking_slots_active_unique')
                || (str_contains(strtolower($exception->getMessage()), 'unique constraint') && str_contains($exception->getMessage(), 'booking_slots'))
            ) {
                abort(409, 'Một hoặc nhiều slot vừa được người khác đặt.');
            }
            throw $exception;
        }

        return $booking;
    }

    public function expirePending(): int
    {
        return DB::transaction(function () {
            $bookings = Booking::query()->where('status', 'pending_payment')
                ->where('payment_expires_at', '<', now())->lockForUpdate()->get();
            foreach ($bookings as $booking) {
                $booking->update(['status' => 'expired']);
                $booking->slots()->where('status', 'held')->update(['status' => 'expired']);
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

        return DB::transaction(function () use ($booking, $reason) {
            $newStatus = $booking->status === 'confirmed' ? 'refund_pending' : 'cancelled';
            $booking->update(['status' => $newStatus, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $booking->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => 'cancelled']);
            if ($newStatus === 'refund_pending') {
                $booking->refunds()->firstOrCreate(['status' => 'pending'], ['amount' => $booking->total_amount]);
            }

            return $booking->fresh(['court', 'slots', 'refunds']);
        });
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

        return DB::transaction(function () use ($booking, $slots, $reason) {
            $amount = $slots->sum('unit_price');
            $slots->each->update(['status' => 'cancelled']);
            $booking->refunds()->create(['amount' => $amount, 'status' => 'pending', 'note' => $reason]);

            return $booking->fresh(['court', 'slots', 'refunds']);
        });
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
}
