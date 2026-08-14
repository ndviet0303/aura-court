<?php

namespace App\Services;

use App\Models\Court;
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
            throw ValidationException::withMessages(['starts_at' => 'Slot phải là một giờ trong tương lai.']);
        }

        $court = Court::query()->whereKey($courtId)->where('is_active', true)->firstOrFail();
        $startUtc = $start->copy()->utc();
        $startDbValues = array_values(array_unique([$startUtc->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]));
        $occupied = $court->bookings()->whereIn('status', ['pending_payment', 'confirmed', 'refund_pending'])
            ->whereHas('slots', fn ($query) => $query->whereIn('starts_at', $startDbValues)->whereIn('status', ['held', 'confirmed']))
            ->exists();
        if (! $occupied) {
            throw ValidationException::withMessages(['starts_at' => 'Slot đang còn trống, bạn có thể đặt ngay.']);
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
                throw ValidationException::withMessages(['starts_at' => 'Bạn đã có tên trong danh sách chờ của slot này.']);
            }
            throw $exception;
        }
    }

    public function notifyForSlot(int $courtId, Carbon $startsAt): int
    {
        $startDbValues = array_values(array_unique([$startsAt->format('Y-m-d H:i:s'), $startsAt->copy()->utc()->format('Y-m-d H:i:s')]));
        $entries = WaitlistEntry::query()->with('user')->where('court_id', $courtId)
            ->whereIn('starts_at', $startDbValues)
            ->where('status', 'waiting')->get();
        foreach ($entries as $entry) {
            $entry->update(['status' => 'notified', 'notified_at' => now()]);
            $entry->user->notify(new AuraBookingNotification(
                'waitlist_available',
                'Slot bạn chờ đã mở',
                "{$entry->court->name} lúc ".($entry->starts_at?->setTimezone(config('app.timezone'))->format('d/m H:i')).' vừa có người hủy. Hãy đặt ngay trước khi slot được giữ bởi người khác.',
                '/courts/'.$entry->court_id.'/schedule',
            ));
        }

        return $entries->count();
    }
}
