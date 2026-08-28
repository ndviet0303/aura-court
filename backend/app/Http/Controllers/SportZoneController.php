<?php

namespace App\Http\Controllers;

use App\Models\SportZone;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SportZoneController extends Controller
{
    public function index(Request $request)
    {
        return SportZone::query()
            ->when($request->query('venue_id'), fn ($q, $venueId) => $q->where('venue_id', $venueId))
            ->when($request->query('sport_type'), fn ($q, $sportType) => $q->where('sport_type', $sportType))
            ->withCount('courts')
            ->with(['venue', 'courts'])
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'name' => 'required|string|max:120',
            'sport_type' => 'required|string|in:badminton,football_5v5,football_7v7,tennis,pickleball,basketball,volleyball,table_tennis',
            'slot_duration' => 'required|integer|in:30,45,60,90,120',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $zone = SportZone::create($data);

        return response()->json($zone->load('venue'), 201);
    }

    public function show(SportZone $sportZone)
    {
        return $sportZone->load(['venue', 'courts.priceSlots']);
    }

    public function update(Request $request, SportZone $sportZone)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'sport_type' => 'sometimes|string|in:badminton,football_5v5,football_7v7,tennis,pickleball,basketball,volleyball,table_tennis',
            'slot_duration' => 'sometimes|integer|in:30,45,60,90,120',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $sportZone->update($data);

        return $sportZone->fresh(['venue', 'courts']);
    }

    public function destroy(SportZone $sportZone)
    {
        $sportZone->update(['is_active' => false]);

        return response()->json(['message' => 'Đã ngừng hoạt động khu vực thể thao.']);
    }
}
