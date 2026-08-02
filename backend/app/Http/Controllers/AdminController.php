<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function bookings(Request $request)
    {
        return Booking::with(['user', 'court', 'slots', 'refunds'])->when($request->status, fn ($q, $status) => $q->where('status', $status))->when($request->date, fn ($q, $date) => $q->whereHas('slots', fn ($s) => $s->whereDate('starts_at', $date)))->latest()->paginate(30);
    }

    public function prices(Request $request, Court $court)
    {
        $data = $request->validate(['weekday' => 'required|integer|min:1|max:7', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i', 'price' => 'required|integer|min:0', 'is_active' => 'boolean']);

        return response()->json($court->priceSlots()->updateOrCreate(['weekday' => $data['weekday'], 'starts_at' => $data['starts_at']], $data), 201);
    }

    public function blocks(Request $request, Court $court)
    {
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'reason' => 'nullable|string|max:255']);

        return response()->json($court->blocks()->create(['starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'reason' => $data['reason'] ?? null]), 201);
    }

    public function refunds(Request $request)
    {
        return Refund::with(['booking.user', 'booking.court'])->where('status', 'pending')->latest()->paginate(30);
    }

    public function processRefund(Request $request, Refund $refund)
    {
        $data = $request->validate(['status' => 'required|in:processed,rejected', 'note' => 'nullable|string|max:1000']);
        $refund->update(['status' => $data['status'], 'note' => $data['note'] ?? null, 'processed_by' => $request->user()->id, 'processed_at' => now()]);
        if ($data['status'] === 'processed') {
            $refund->booking()->update(['status' => 'refunded']);
        }

        return $refund->fresh(['booking']);
    }

    public function report(Request $request)
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $base = Booking::whereBetween('created_at', [$from, $to]);

        return ['from' => $from, 'to' => $to, 'bookings' => (clone $base)->count(), 'confirmed' => (clone $base)->where('status', 'confirmed')->count(), 'revenue' => (clone $base)->where('status', 'confirmed')->sum('total_amount'), 'pendingRefunds' => Refund::where('status', 'pending')->whereBetween('created_at', [$from, $to])->sum('amount'), 'byCourt' => (clone $base)->where('status', 'confirmed')->select('court_id', DB::raw('count(*) as bookings'), DB::raw('sum(total_amount) as revenue'))->groupBy('court_id')->with('court:id,name')->get()];
    }
}
