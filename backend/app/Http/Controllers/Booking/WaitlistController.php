<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;

use App\Services\Booking\WaitlistService;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function __construct(private readonly WaitlistService $waitlists) {}

    public function index(Request $request)
    {
        return $request->user()->waitlistEntries()->with('court.venue')->latest()->paginate(30);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['court_id' => 'required|integer|exists:courts,id', 'starts_at' => 'required|date']);

        return response()->json($this->waitlists->join($request->user()->id, $data['court_id'], $data['starts_at']), 201);
    }

    public function destroy(Request $request, int $waitlist)
    {
        $entry = $request->user()->waitlistEntries()->whereKey($waitlist)->firstOrFail();
        $entry->update(['status' => 'cancelled']);

        return ['message' => 'Đã rời danh sách chờ.'];
    }
}
