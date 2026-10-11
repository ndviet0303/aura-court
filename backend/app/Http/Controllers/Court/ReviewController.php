<?php

namespace App\Http\Controllers\Court;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Court $court)
    {
        return $court->reviews()->where('is_published', true)->with('user:id,name')->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['booking_code' => 'required|string', 'rating' => 'required|integer|min:1|max:5', 'body' => 'nullable|string|max:2000']);
        $booking = Booking::with('slots')->where('code', $data['booking_code'])->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless(in_array($booking->status, ['confirmed', 'no_show'], true), 422, 'Chỉ có thể đánh giá booking đã hoàn tất.');
        abort_unless($booking->slots->isNotEmpty() && $booking->slots->every(fn ($slot) => $slot->ends_at->isPast()), 422, 'Chỉ có thể đánh giá sau khi đã chơi xong.');
        abort_if($booking->review()->exists(), 409, 'Booking này đã được đánh giá.');

        return response()->json(Review::create([
            'user_id' => $request->user()->id,
            'court_id' => $booking->court_id,
            'booking_id' => $booking->id,
            'rating' => $data['rating'],
            'body' => $data['body'] ?? null,
        ])->load('user:id,name'), 201);
    }
}
