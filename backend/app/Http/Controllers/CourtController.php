<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Illuminate\Http\Request;

class CourtController extends Controller
{
    public function index()
    {
        return Court::where('is_active', true)->with('priceSlots')->orderBy('id')->get();
    }

    public function adminIndex()
    {
        return Court::with(['priceSlots', 'blocks' => fn ($query) => $query->latest('starts_at')])->orderBy('id')->get();
    }

    public function store(Request $request)
    {
        return response()->json(Court::create($request->validate(['name' => 'required|string|max:120', 'description' => 'nullable|string', 'image_url' => 'nullable|url', 'is_active' => 'boolean'])), 201);
    }

    public function update(Request $request, Court $court)
    {
        $court->update($request->validate(['name' => 'sometimes|string|max:120', 'description' => 'nullable|string', 'image_url' => 'nullable|url', 'is_active' => 'boolean']));

        return $court->fresh();
    }

    public function destroy(Court $court)
    {
        $court->update(['is_active' => false]);

        return ['message' => 'Đã ngừng mở sân.'];
    }
}
