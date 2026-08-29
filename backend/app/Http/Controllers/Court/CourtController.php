<?php

namespace App\Http\Controllers\Court;

use App\Http\Controllers\Controller;

use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourtController extends Controller
{
    public function index(Request $request)
    {
        return Court::where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('is_active', true))
            ->when($request->query('sport_type'), function ($q, $sportType) {
                $q->whereHas('sportZone', fn ($zone) => $zone->where('sport_type', $sportType)->where('is_active', true));
            })
            ->when($request->query('venue_id'), function ($q, $venueId) {
                $q->where('venue_id', $venueId);
            })
            ->when($request->query('sport_zone_id'), function ($q, $zoneId) {
                $q->where('sport_zone_id', $zoneId);
            })
            ->with(['priceSlots', 'venue', 'sportZone'])
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('is_published', true)])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->where('is_published', true)], 'rating')
            ->orderBy('id')
            ->get();
    }

    public function adminIndex(Request $request)
    {
        return Court::with(['priceSlots', 'venue', 'sportZone', 'blocks' => fn ($query) => $query->latest('starts_at')])
            ->when($request->query('venue_id'), fn ($q, $venueId) => $q->where('venue_id', $venueId))
            ->when($request->query('sport_zone_id'), fn ($q, $zoneId) => $q->where('sport_zone_id', $zoneId))
            ->when($request->query('sport_type'), fn ($q, $sportType) => $q->whereHas('sportZone', fn ($z) => $z->where('sport_type', $sportType)))
            ->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'sport_zone_id' => ['nullable', 'integer', Rule::exists('sport_zones', 'id')->where('is_active', true)],
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
        ]);

        return response()->json(Court::create($data)->load(['venue', 'sportZone']), 201);
    }

    public function update(Request $request, Court $court)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'venue_id' => ['sometimes', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'sport_zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sport_zones', 'id')->where('is_active', true)],
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
        ]);
        $court->update($data);
        abort_if($court->is_active && ! $court->venue()->where('is_active', true)->exists(), 422, 'Không thể mở sân tại địa điểm đang ngừng hoạt động.');

        return $court->fresh(['venue', 'sportZone']);
    }


    public function destroy(Court $court)
    {
        $court->update(['is_active' => false]);

        return ['message' => 'Đã ngừng mở sân.'];
    }
}
