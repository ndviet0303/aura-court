<?php

namespace App\Services\Booking;

use App\Models\Court;
use App\Models\ReservationSlot;
use App\Models\WaitlistEntry;
use App\Notifications\AuraBookingNotification;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class WaitlistService
{
    public function join(int $userId, int $courtId, string $startsAt): WaitlistEntry
    {
        try {
            $start = Carbon::parse($startsAt)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['starts_at' => 'Thời gian không hợp lệ.']);
        }
        if ($start->minute !== 0 || $start->second !== 0 || $start->isPast()) {
            throw ValidationException::withMessages(['starts_at' => 'Giờ đăng ký chờ phải bắt đầu tròn giờ và chưa qua.']);
        }

        $court = Court::query()->whereKey($courtId)->where('status', Court::STATUS_ACTIVE)->firstOrFail();
        $startUtc = $start->copy()->utc();
        $occupied = ReservationSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', $startUtc)
            ->whereIn('status', ['held', 'confirmed'])
            ->exists();
        if (! $occupied) {
            throw ValidationException::withMessages(['starts_at' => 'Giờ này đang còn trống, bạn có thể đặt ngay.']);
        }

        try {
            return WaitlistEntry::create([
                'user_id' => $userId,
                'court_id' => $court->id,
                'starts_at' => $startUtc,
                'ends_at' => $startUtc->copy()->addHour(),
                'status' => 'waiting',
            ])->load('court');
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'waitlist_entries')) {
                throw ValidationException::withMessages(['starts_at' => 'Bạn đã có tên trong danh sách chờ của giờ này.']);
            }
            throw $exception;
        }
    }

    public function notifyForSlot(int $courtId, Carbon $startsAt): int
    {
        $startsAtUtc = $startsAt->copy()->utc();
        $entries = WaitlistEntry::query()->with('user')->where('court_id', $courtId)
            ->where('starts_at', $startsAtUtc)
            ->where('status', 'waiting')->get();
        foreach ($entries as $entry) {
            $entry->update(['status' => 'notified', 'notified_at' => now()]);
            $entry->user->notify(new AuraBookingNotification(
                'waitlist_available',
                'Giờ bạn chờ đã trống',
                "{$entry->court->name} lúc ".($entry->starts_at?->setTimezone(config('app.timezone'))->format('d/m H:i')).' vừa có người hủy. Hãy đặt ngay trước khi người khác giữ giờ này.',
                '/courts/'.$entry->court_id.'/schedule',
            ));
        }

        return $entries->count();
    }
}
