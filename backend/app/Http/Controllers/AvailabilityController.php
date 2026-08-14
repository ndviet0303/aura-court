<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['court_id' => 'required|integer|exists:courts,id', 'date' => 'required|date_format:Y-m-d']);
        $date = Carbon::createFromFormat('Y-m-d', $data['date'], config('app.timezone'))->startOfDay();
        $court = Court::whereKey($data['court_id'])
            ->where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('is_active', true))
            ->with(['priceSlots' => fn ($q) => $q->where('weekday', $date->dayOfWeekIso)->where('is_active', true)->orderBy('starts_at'), 'blocks'])
            ->firstOrFail();
        $existing = $court->bookings()->whereIn('status', ['pending_payment', 'confirmed', 'refund_pending'])->with('slots')->get()->flatMap->slots;

        return $court->priceSlots->map(function ($slot) use ($date, $existing, $court) {
            $start = Carbon::parse($date->format('Y-m-d').' '.$slot->starts_at, config('app.timezone'));
            $end = $start->copy()->addHour();
            $blocked = $court->blocks->contains(fn ($block) => $block->starts_at->lt($end->copy()->utc()) && $block->ends_at->gt($start->copy()->utc()));
            $booked = $existing->contains(fn ($item) => $item->starts_at->equalTo($start->copy()->utc()) && in_array($item->status, ['held', 'confirmed']));

            return ['startsAt' => $start->toIso8601String(), 'endsAt' => $end->toIso8601String(), 'price' => (int) $slot->price, 'available' => ! $blocked && ! $booked && $start->isFuture()];
        })->values();
    }
}
