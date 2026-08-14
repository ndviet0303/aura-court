<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourtController extends Controller
{
    public function index()
    {
        return Court::where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('is_active', true))
            ->with(['priceSlots', 'venue'])
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('is_published', true)])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->where('is_published', true)], 'rating')
            ->orderBy('id')
            ->get();
    }

    public function adminIndex()
    {
        return Court::with(['priceSlots', 'venue', 'blocks' => fn ($query) => $query->latest('starts_at')])->orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
        ]);

        return response()->json(Court::create($data)->load('venue'), 201);
    }

    public function update(Request $request, Court $court)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'venue_id' => ['sometimes', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
        ]);
        $court->update($data);
        abort_if($court->is_active && ! $court->venue()->where('is_active', true)->exists(), 422, 'Không thể mở sân tại địa điểm đang ngừng hoạt động.');

        return $court->fresh('venue');
    }

    public function destroy(Court $court)
    {
        $court->update(['is_active' => false]);

        return ['message' => 'Đã ngừng mở sân.'];
    }
}
