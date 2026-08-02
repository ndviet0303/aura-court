<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\CourtPriceSlot;
use App\Models\Payment;
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
        abort_if($data['ends_at'] !== now()->setTimeFromTimeString($data['starts_at'])->addHour()->format('H:i'), 422, 'Khung giá phải dài đúng 60 phút.');

        return response()->json($court->priceSlots()->updateOrCreate(['weekday' => $data['weekday'], 'starts_at' => $data['starts_at']], $data), 201);
    }

    public function blocks(Request $request, Court $court)
    {
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'reason' => 'nullable|string|max:255']);

        return response()->json($court->blocks()->create(['starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'reason' => $data['reason'] ?? null]), 201);
    }

    public function updatePrice(Request $request, Court $court, CourtPriceSlot $price)
    {
        abort_unless($price->court_id === $court->id, 404);
        $data = $request->validate(['weekday' => 'sometimes|integer|min:1|max:7', 'starts_at' => 'sometimes|date_format:H:i', 'ends_at' => 'sometimes|date_format:H:i', 'price' => 'sometimes|integer|min:0', 'is_active' => 'boolean']);
        $merged = array_merge($price->only(['weekday', 'starts_at', 'ends_at']), $data);
        abort_if($merged['ends_at'] !== now()->setTimeFromTimeString($merged['starts_at'])->addHour()->format('H:i'), 422, 'Khung giá phải dài đúng 60 phút.');
        $price->update($data);

        return $price->fresh();
    }

    public function destroyPrice(Court $court, CourtPriceSlot $price)
    {
        abort_unless($price->court_id === $court->id, 404);
        $price->delete();

        return ['message' => 'Đã xóa khung giá.'];
    }

    public function blocksIndex(Court $court)
    {
        return $court->blocks()->latest('starts_at')->paginate(50);
    }

    public function destroyBlock(Court $court, CourtBlock $block)
    {
        abort_unless($block->court_id === $court->id, 404);
        $block->delete();

        return ['message' => 'Đã mở khóa lịch sân.'];
    }

    public function refunds(Request $request)
    {
        return Refund::with(['booking.user', 'booking.court'])->where('status', 'pending')->latest()->paginate(30);
    }

    public function payments(Request $request)
    {
        return Payment::with(['booking.user', 'booking.court'])->where('status', 'manual_review')->latest()->paginate(30);
    }

    public function reviewPayment(Request $request, Payment $payment)
    {
        $data = $request->validate(['status' => 'required|in:paid,rejected', 'note' => 'nullable|string|max:1000']);
        abort_unless($payment->status === 'manual_review', 409, 'Giao dịch đã được xử lý.');
        $payment->update(['status' => $data['status'], 'payload' => array_merge($payment->payload ?? [], ['review_note' => $data['note'] ?? null, 'reviewed_at' => now()->toIso8601String(), 'reviewed_by' => $request->user()->id])]);
        if ($data['status'] === 'paid' && $payment->booking && $payment->booking->status === 'pending_payment' && $payment->amount >= $payment->booking->total_amount && $payment->booking->payment_expires_at?->isFuture()) {
            $payment->booking()->update(['status' => 'confirmed']);
            $payment->booking->slots()->where('status', 'held')->update(['status' => 'confirmed']);
        }

        return $payment->fresh(['booking']);
    }

    public function noShow(Request $request, Booking $booking)
    {
        abort_unless($booking->status === 'confirmed', 409, 'Booking không ở trạng thái có thể đánh dấu no-show.');
        abort_unless($booking->slots()->where('starts_at', '>', now())->doesntExist(), 409, 'Chưa kết thúc toàn bộ khung giờ.');
        $booking->update(['status' => 'no_show']);

        return $booking->fresh(['court', 'slots']);
    }

    public function processRefund(Request $request, Refund $refund)
    {
        $data = $request->validate(['status' => 'required|in:processed,rejected', 'note' => 'nullable|string|max:1000']);
        abort_unless($refund->status === 'pending', 409, 'Yêu cầu hoàn tiền đã được xử lý.');
        $refund->update(['status' => $data['status'], 'note' => $data['note'] ?? null, 'processed_by' => $request->user()->id, 'processed_at' => now()]);
        if ($data['status'] === 'processed') {
            $hasActiveSlots = $refund->booking->slots()->whereIn('status', ['held', 'confirmed'])->exists();
            $refund->booking()->update(['status' => $hasActiveSlots ? 'confirmed' : 'refunded']);
        } else {
            $hasActiveSlots = $refund->booking->slots()->whereIn('status', ['held', 'confirmed'])->exists();
            $refund->booking()->update(['status' => $hasActiveSlots ? 'confirmed' : 'cancelled']);
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

    public function reportCsv(Request $request)
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $rows = Booking::with(['user', 'court'])->whereBetween('created_at', [$from, $to])->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['code', 'customer', 'court', 'status', 'amount', 'created_at']);
            foreach ($rows as $booking) {
                fputcsv($output, [$booking->code, $booking->user?->email, $booking->court?->name, $booking->status, $booking->total_amount, $booking->created_at?->toIso8601String()]);
            }
            fclose($output);
        }, 'booking-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
