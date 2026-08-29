<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\CourtPriceSlot;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Venue;
use App\Services\Infrastructure\AuditLogger;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\PaymentService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audits,
        private readonly PaymentService $paymentsService,
        private readonly FinancialPostingService $financials,
    ) {}

    public function venues(Request $request)
    {
        return Venue::withCount('courts')
            ->with(['sportZones' => fn ($q) => $q->where('is_active', true)->withCount('courts')])
            ->when($request->query('sport_type'), function ($q, $sportType) {
                $q->whereHas('sportZones', fn ($z) => $z->where('sport_type', $sportType)->where('is_active', true));
            })
            ->orderBy('name')
            ->get();
    }

    public function storeVenue(Request $request)
    {
        $data = $this->validatedVenue($request);
        $venue = Venue::create($data + ['slug' => $this->uniqueSlug($data['name'])]);
        $this->audits->record($request, 'venue.created', $venue);

        // Automatically create a default sport zone for the new venue
        $venue->sportZones()->create([
            'name' => 'Khu Cầu Lông',
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        return response()->json($venue->load('sportZones'), 201);
    }

    public function updateVenue(Request $request, Venue $venue)
    {
        if ($request->hasAny(['name', 'address', 'google_maps_url'])) {
            $data = $this->validatedVenue($request);
            $venue->update($data);
        }
        if ($request->has('is_active')) {
            $venue->update($request->validate(['is_active' => 'boolean']));
        }
        $this->audits->record($request, 'venue.updated', $venue, ['fields' => array_keys($request->all())]);

        return $venue->fresh('sportZones');
    }

    public function destroyVenue(Venue $venue)
    {
        $venue->update(['is_active' => false]);
        $this->audits->record(request(), 'venue.deactivated', $venue);

        return ['message' => 'Đã ngừng hoạt động địa điểm.'];
    }

    public function showVenue(string $slug)
    {
        return Venue::where('slug', $slug)
            ->where('is_active', true)
            ->with(['sportZones' => fn ($q) => $q->where('is_active', true)->with(['courts' => fn ($c) => $c->where('is_active', true)->with('priceSlots')])])
            ->firstOrFail();
    }


    private function validatedVenue(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'address' => 'required|string|max:255',
            'google_maps_url' => 'required|url|max:2048',
            'is_active' => 'boolean',
        ]);

        if (! $this->isGoogleMapsUrl($data['google_maps_url'])) {
            throw ValidationException::withMessages([
                'google_maps_url' => 'Chỉ chấp nhận liên kết Google Maps HTTPS hợp lệ.',
            ]);
        }

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $suffix = 2;
        while (Venue::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function isGoogleMapsUrl(string $value): bool
    {
        $parts = parse_url($value);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        if ($host === 'maps.app.goo.gl') {
            return true;
        }
        if ($host === 'goo.gl') {
            return str_starts_with($path, '/maps');
        }

        return (bool) preg_match('/^(?:(?:www|maps)\.)?google\.(?:[a-z]{2,3}|(?:com|co)\.[a-z]{2})$/', $host)
            && (bool) preg_match('#^/maps(?:/|$)#', $path);
    }

    public function bookings(Request $request)
    {
        return Booking::with(['user', 'court', 'slots', 'refunds'])->when($request->status, fn ($q, $status) => $q->where('status', $status))->when($request->date, fn ($q, $date) => $q->whereHas('slots', fn ($s) => $s->whereDate('starts_at', $date)))->latest()->paginate(30);
    }

    public function prices(Request $request, Court $court)
    {
        $data = $request->validate(['weekday' => 'required|integer|min:1|max:7', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i', 'price' => 'required|integer|min:0', 'is_active' => 'boolean']);
        abort_if($data['ends_at'] !== now()->setTimeFromTimeString($data['starts_at'])->addHour()->format('H:i'), 422, 'Khung giá phải dài đúng 60 phút.');

        $price = $court->priceSlots()->updateOrCreate(['weekday' => $data['weekday'], 'starts_at' => $data['starts_at']], $data);
        $this->audits->record($request, 'price.updated', $price, ['court_id' => $court->id]);

        return response()->json($price, 201);
    }

    public function bulkPrices(Request $request, Court $court)
    {
        $data = $request->validate([
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => 'integer|min:1|max:7',
            'start_hour' => 'required|integer|min:0|max:23',
            'end_hour' => 'required|integer|gt:start_hour|max:24',
            'price' => 'required|integer|min:0',
        ]);

        $createdOrUpdated = [];
        foreach ($data['weekdays'] as $weekday) {
            for ($hour = $data['start_hour']; $hour < $data['end_hour']; $hour++) {
                $startsAt = sprintf('%02d:00', $hour);
                $endsAt = sprintf('%02d:00', $hour + 1);
                $slot = $court->priceSlots()->updateOrCreate(
                    ['weekday' => $weekday, 'starts_at' => $startsAt],
                    ['ends_at' => $endsAt, 'price' => $data['price'], 'is_active' => true]
                );
                $createdOrUpdated[] = $slot;
            }
        }

        $this->audits->record($request, 'price.bulk_updated', null, [
            'court_id' => $court->id,
            'weekdays' => $data['weekdays'],
            'start_hour' => $data['start_hour'],
            'end_hour' => $data['end_hour'],
            'price' => $data['price'],
            'count' => count($createdOrUpdated),
        ]);

        return response()->json([
            'message' => 'Đã cập nhật khung giá hàng loạt.',
            'slots' => $court->priceSlots()->orderBy('weekday')->orderBy('starts_at')->get(),
        ]);
    }

    public function blocks(Request $request, Court $court)
    {
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'reason' => 'nullable|string|max:255']);

        $block = $court->blocks()->create(['starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'reason' => $data['reason'] ?? null]);
        $this->audits->record($request, 'court_block.created', $block, ['court_id' => $court->id]);

        return response()->json($block, 201);
    }

    public function updatePrice(Request $request, Court $court, CourtPriceSlot $price)
    {
        abort_unless($price->court_id === $court->id, 404);
        $data = $request->validate(['weekday' => 'sometimes|integer|min:1|max:7', 'starts_at' => 'sometimes|date_format:H:i', 'ends_at' => 'sometimes|date_format:H:i', 'price' => 'sometimes|integer|min:0', 'is_active' => 'boolean']);
        $merged = array_merge($price->only(['weekday', 'starts_at', 'ends_at']), $data);
        abort_if($merged['ends_at'] !== now()->setTimeFromTimeString($merged['starts_at'])->addHour()->format('H:i'), 422, 'Khung giá phải dài đúng 60 phút.');
        $price->update($data);
        $this->audits->record($request, 'price.updated', $price, ['court_id' => $court->id, 'fields' => array_keys($data)]);

        return $price->fresh();
    }

    public function destroyPrice(Court $court, CourtPriceSlot $price)
    {
        abort_unless($price->court_id === $court->id, 404);
        $price->delete();
        $this->audits->record(request(), 'price.deleted', $price, ['court_id' => $court->id]);

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
        $this->audits->record(request(), 'court_block.deleted', $block, ['court_id' => $court->id]);

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
            $this->paymentsService->notifyConfirmed($payment->booking->fresh(['user', 'court']));
        }
        $this->audits->record($request, 'payment.reviewed', $payment, ['status' => $data['status']]);

        return $payment->fresh(['booking']);
    }

    public function noShow(Request $request, Booking $booking)
    {
        abort_unless($booking->status === 'confirmed', 409, 'Booking không ở trạng thái có thể đánh dấu no-show.');
        abort_unless($booking->slots()->where('starts_at', '>', now())->doesntExist(), 409, 'Chưa kết thúc toàn bộ khung giờ.');
        $booking->update(['status' => 'no_show']);
        $this->audits->record($request, 'booking.no_show', $booking);

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
            $organizationId = $refund->booking->loadMissing('court.venue')->court?->venue?->organization_id;
            if ($organizationId) {
                $this->financials->postRefund($organizationId, $refund->booking->code, (int) $refund->amount, (string) $refund->id, ['legacy_refund_id' => $refund->id]);
            }
        } else {
            $hasActiveSlots = $refund->booking->slots()->whereIn('status', ['held', 'confirmed'])->exists();
            $refund->booking()->update(['status' => $hasActiveSlots ? 'confirmed' : 'cancelled']);
        }
        $this->audits->record($request, 'refund.processed', $refund, ['status' => $data['status']]);

        return $refund->fresh(['booking']);
    }

    public function report(Request $request)
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $base = Booking::whereBetween('created_at', [$from, $to]);
        $confirmedSlots = BookingSlot::query()->where('status', 'confirmed')->whereBetween('starts_at', [$from, $to])->get();
        $capacity = 0;
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()) as $day) {
            $capacity += CourtPriceSlot::query()->where('is_active', true)->where('weekday', $day->dayOfWeekIso)->count();
        }
        $byHour = $confirmedSlots->groupBy(fn (BookingSlot $slot) => $slot->starts_at->setTimezone(config('app.timezone'))->format('H:00'))->map(fn ($items, $hour) => ['hour' => $hour, 'bookings' => $items->count()])->values();

        return ['from' => $from, 'to' => $to, 'bookings' => (clone $base)->count(), 'confirmed' => (clone $base)->where('status', 'confirmed')->count(), 'revenue' => (clone $base)->where('status', 'confirmed')->sum('total_amount'), 'pendingRefunds' => Refund::where('status', 'pending')->whereBetween('created_at', [$from, $to])->sum('amount'), 'occupancyRate' => $capacity > 0 ? round($confirmedSlots->count() / $capacity * 100, 1) : 0, 'byHour' => $byHour, 'byCourt' => (clone $base)->where('status', 'confirmed')->select('court_id', DB::raw('count(*) as bookings'), DB::raw('sum(total_amount) as revenue'))->groupBy('court_id')->with('court:id,name')->get()];
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

    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,webp,avif,gif|max:10240',
        ]);

        $file = $request->file('image');
        $filename = 'courts/' . Str::uuid() . '.' . $file->getClientOriginalExtension();

        $disk = config('filesystems.disks.r2.key') ? 'r2' : (config('filesystems.disks.s3.key') ? 's3' : 'public');

        try {
            Storage::disk($disk)->putFileAs('', $file, $filename, 'public');
            $url = Storage::disk($disk)->url($filename);
        } catch (\Throwable $e) {
            $path = Storage::disk('public')->putFileAs('courts', $file, Str::uuid() . '.' . $file->getClientOriginalExtension(), 'public');
            $url = Storage::disk('public')->url($path);
        }

        return response()->json([
            'url' => $url,
            'filename' => $filename,
        ]);
    }
}
