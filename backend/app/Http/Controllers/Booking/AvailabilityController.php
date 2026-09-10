<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;

use App\Models\Court;
use App\Models\ReservationSlot;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request, \App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility $eligibility)
    {
        $data = $request->validate(['court_id' => 'required|integer|exists:courts,id', 'date' => 'required|date_format:Y-m-d']);
        $court = $eligibility->assertEligible((int) $data['court_id'], publicFacing: true);
        $court->loadMissing(['priceSlots', 'blocks', 'sportZone']);

        $zone = $court->venue?->organization?->timezone ?: config('app.timezone', 'Asia/Ho_Chi_Minh');
        $date = Carbon::createFromFormat('Y-m-d', $data['date'], $zone)->startOfDay();

        $activeSlots = $court->priceSlots->filter(fn ($s) => (int) $s->weekday === $date->dayOfWeekIso && $s->is_active)->sortBy('starts_at');

        $dayStartUtc = $date->copy()->startOfDay()->utc();
        $dayEndUtc = $date->copy()->endOfDay()->utc();

        $v1Slots = $court->bookings()->whereIn('status', ['pending_payment', 'confirmed', 'refund_pending'])->with('slots')->get()->flatMap->slots;
        $v2Slots = ReservationSlot::query()
            ->where('court_id', $court->id)
            ->whereHas('reservation', fn ($r) => $r->whereIn('status', ['held', 'confirmed']))
            ->where('starts_at', '>=', $dayStartUtc->copy()->subHours(24))
            ->where('starts_at', '<=', $dayEndUtc->copy()->addHours(24))
            ->get();
        $duration = (int) ($court->sportZone?->slot_duration ?: 60);
        $toUtcCarbon = function ($dateTime): Carbon {
            if ($dateTime instanceof \DateTimeInterface) {
                return Carbon::createFromFormat('Y-m-d H:i:s', $dateTime->format('Y-m-d H:i:s'), 'UTC');
            }
            return Carbon::parse((string) $dateTime, 'UTC');
        };

        return $activeSlots->map(function ($slot) use ($date, $zone, $v1Slots, $v2Slots, $court, $duration, $toUtcCarbon) {
            $start = Carbon::parse($date->format('Y-m-d').' '.$slot->starts_at, $zone);
            $end = $start->copy()->addMinutes($duration);
            $startUtc = $start->copy()->utc();
            $endUtc = $end->copy()->utc();
            $startTs = $startUtc->timestamp;
            $endTs = $endUtc->timestamp;

            $blocked = $court->blocks->contains(fn ($block) => $toUtcCarbon($block->starts_at)->timestamp < $endTs && $toUtcCarbon($block->ends_at)->timestamp > $startTs);
            $bookedV1 = $v1Slots->contains(fn ($item) => $toUtcCarbon($item->starts_at)->timestamp === $startTs && in_array($item->status, ['held', 'confirmed']));
            $bookedV2 = $v2Slots->contains(fn ($item) => $toUtcCarbon($item->starts_at)->timestamp === $startTs);

            return [
                'startsAt' => $start->toIso8601String(),
                'endsAt' => $end->toIso8601String(),
                'price' => (int) $slot->price,
                'available' => ! $blocked && ! $bookedV1 && ! $bookedV2 && $start->isFuture(),
            ];
        })->values();
    }
}

