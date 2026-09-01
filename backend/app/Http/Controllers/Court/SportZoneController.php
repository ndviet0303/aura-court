<?php

namespace App\Http\Controllers\Court;

use App\Http\Controllers\Controller;

use App\Models\SportZone;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SportZoneController extends Controller
{
    public function index(Request $request)
    {
        $version = Cache::get('sport_zones_version', 1);
        $cacheKey = "sport_zones:v{$version}:" . md5(json_encode($request->only(['venue_id', 'sport_type'])));

        $zones = Cache::remember($cacheKey, 300, function () use ($request) {
            return SportZone::query()
                ->when($request->query('venue_id'), fn ($q, $venueId) => $q->where('venue_id', $venueId))
                ->when($request->query('sport_type'), fn ($q, $sportType) => $q->where('sport_type', $sportType))
                ->withCount('courts')
                ->with(['venue'])
                ->orderBy('id')
                ->get()
                ->toArray();
        });

        return response()->json($zones);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'name' => 'required|array',
            'name.*' => 'string|max:120',
            'sport_type' => 'required|string|in:badminton,football_5v5,football_7v7,tennis,pickleball,basketball,volleyball,table_tennis',
            'slot_duration' => 'required|integer|in:30,45,60,90,120',
            'description' => 'nullable|array',
            'description.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $zone = SportZone::create($data);
        Cache::increment('sport_zones_version');

        return response()->json($zone->load('venue'), 201);
    }

    public function show(SportZone $sportZone)
    {
        return $sportZone->load(['venue', 'courts.priceSlots']);
    }

    public function update(Request $request, SportZone $sportZone)
    {
        $data = $request->validate([
            'name' => 'sometimes|array',
            'name.*' => 'string|max:120',
            'sport_type' => 'sometimes|string|in:badminton,football_5v5,football_7v7,tennis,pickleball,basketball,volleyball,table_tennis',
            'slot_duration' => 'sometimes|integer|in:30,45,60,90,120',
            'description' => 'nullable|array',
            'description.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $sportZone->update($data);
        Cache::increment('sport_zones_version');

        return $sportZone->fresh(['venue']);
    }

    public function destroy(SportZone $sportZone)
    {
        $sportZone->update(['is_active' => false]);
        Cache::increment('sport_zones_version');

        return response()->json(['message' => 'Đã ngừng hoạt động khu vực thể thao.']);
    }
}
