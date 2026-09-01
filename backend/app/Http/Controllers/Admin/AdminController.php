<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Repositories\RefundRepositoryInterface;
use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Contracts\Storage\FileStorageServiceInterface;
use App\Domain\Admin\Actions\CreateVenue;
use App\Domain\Admin\Actions\ManageCourtBlocks;
use App\Domain\Admin\Actions\ManageCourtPrices;
use App\Domain\Admin\Actions\ProcessRefund;
use App\Domain\Admin\Actions\ReviewPayment;
use App\Domain\Admin\Actions\UpdateVenue;
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
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private readonly VenueRepositoryInterface $venues,
        private readonly CourtBlockRepositoryInterface $courtBlocks,
        private readonly BookingRepositoryInterface $bookings,
        private readonly PaymentRepositoryInterface $payments,
        private readonly RefundRepositoryInterface $refunds,
        private readonly AuditLogger $audits,
        private readonly FileStorageServiceInterface $fileStorage,
        private readonly CreateVenue $createVenueAction,
        private readonly UpdateVenue $updateVenueAction,
        private readonly ManageCourtPrices $manageCourtPricesAction,
        private readonly ManageCourtBlocks $manageCourtBlocksAction,
        private readonly ReviewPayment $reviewPaymentAction,
        private readonly ProcessRefund $processRefundAction,
    ) {}

    public function venues(Request $request)
    {
        return $this->venues->getActiveVenuesWithCourts($request->query('sport_type'));
    }

    public function storeVenue(Request $request)
    {
        $data = $this->validatedVenue($request);
        $venue = $this->createVenueAction->execute($data);
        $this->audits->record($request, 'venue.created', $venue);

        return response()->json($venue, 201);
    }

    public function updateVenue(Request $request, Venue $venue)
    {
        if ($request->hasAny(['name', 'address', 'google_maps_url'])) {
            $data = $this->validatedVenue($request);
            $this->updateVenueAction->execute($venue, $data);
        }
        if ($request->has('is_active')) {
            $this->updateVenueAction->execute($venue, $request->validate(['is_active' => 'boolean']));
        }
        $this->audits->record($request, 'venue.updated', $venue, ['fields' => array_keys($request->all())]);

        return $venue->fresh('sportZones');
    }

    public function destroyVenue(Venue $venue)
    {
        $this->updateVenueAction->execute($venue, ['is_active' => false]);
        $this->audits->record(request(), 'venue.deactivated', $venue);

        return ['message' => 'Đã ngừng hoạt động địa điểm.'];
    }

    public function showVenue(string $slug)
    {
        $venue = $this->venues->findBySlug($slug);
        abort_unless($venue, 404);

        return $venue;
    }

    private function validatedVenue(Request $request): array
    {
        $rawName = $request->input('name');
        if (is_string($rawName)) {
            $request->merge(['name' => ['vi' => $rawName, 'en' => $rawName]]);
        }

        $data = $request->validate([
            'name' => 'required|array',
            'name.*' => 'string|max:120',
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
        return $this->bookings->paginateAdminBookings([
            'status' => $request->status,
            'date' => $request->date,
        ], 30);
    }

    public function prices(Request $request, Court $court)
    {
        $data = $request->validate([
            'weekday' => 'required|integer|min:1|max:7',
            'starts_at' => 'required|date_format:H:i',
            'ends_at' => 'required|date_format:H:i',
            'price' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $price = $this->manageCourtPricesAction->createOrUpdatePrice($court, $data);
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

        $result = $this->manageCourtPricesAction->bulkUpdate($court, $data);

        $this->audits->record($request, 'price.bulk_updated', null, [
            'court_id' => $court->id,
            'weekdays' => $data['weekdays'],
            'start_hour' => $data['start_hour'],
            'end_hour' => $data['end_hour'],
            'price' => $data['price'],
            'count' => $result['count'],
        ]);

        return response()->json([
            'message' => 'Đã cập nhật khung giá hàng loạt.',
            'slots' => $result['slots'],
        ]);
    }

    public function blocks(Request $request, Court $court)
    {
        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        $block = $this->manageCourtBlocksAction->createBlock($court, $data);
        $this->audits->record($request, 'court_block.created', $block, ['court_id' => $court->id]);

        return response()->json($block, 201);
    }

    public function updatePrice(Request $request, Court $court, CourtPriceSlot $price)
    {
        $data = $request->validate([
            'weekday' => 'sometimes|integer|min:1|max:7',
            'starts_at' => 'sometimes|date_format:H:i',
            'ends_at' => 'sometimes|date_format:H:i',
            'price' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $updated = $this->manageCourtPricesAction->updatePrice($court, $price, $data);
        $this->audits->record($request, 'price.updated', $updated, ['court_id' => $court->id, 'fields' => array_keys($data)]);

        return $updated;
    }

    public function destroyPrice(Court $court, CourtPriceSlot $price)
    {
        $this->manageCourtPricesAction->deletePrice($court, $price);
        $this->audits->record(request(), 'price.deleted', $price, ['court_id' => $court->id]);

        return ['message' => 'Đã xóa khung giá.'];
    }

    public function blocksIndex(Court $court)
    {
        return $this->courtBlocks->paginateForCourt($court, 50);
    }

    public function destroyBlock(Court $court, CourtBlock $block)
    {
        $this->manageCourtBlocksAction->deleteBlock($court, $block);
        $this->audits->record(request(), 'court_block.deleted', $block, ['court_id' => $court->id]);

        return ['message' => 'Đã mở khóa lịch sân.'];
    }

    public function refunds(Request $request)
    {
        return $this->refunds->paginatePending(30);
    }

    public function payments(Request $request)
    {
        return $this->payments->paginateManualReview(30);
    }

    public function reviewPayment(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'status' => 'required|in:paid,rejected',
            'note' => 'nullable|string|max:1000',
        ]);

        $updated = $this->reviewPaymentAction->execute($payment, $data, $request->user());
        $this->audits->record($request, 'payment.reviewed', $updated, ['status' => $data['status']]);

        return $updated;
    }

    public function noShow(Request $request, Booking $booking)
    {
        abort_unless($booking->status === 'confirmed', 409, 'Booking không ở trạng thái có thể đánh dấu no-show.');
        abort_unless($booking->slots()->where('starts_at', '>', now())->doesntExist(), 409, 'Chưa kết thúc toàn bộ khung giờ.');
        $this->bookings->updateStatus($booking, 'no_show');
        $this->audits->record($request, 'booking.no_show', $booking);

        return $booking->fresh(['court', 'slots']);
    }

    public function processRefund(Request $request, Refund $refund)
    {
        $data = $request->validate([
            'status' => 'required|in:processed,rejected',
            'note' => 'nullable|string|max:1000',
        ]);

        $updated = $this->processRefundAction->execute($refund, $data, $request->user());
        $this->audits->record($request, 'refund.processed', $updated, ['status' => $data['status']]);

        return $updated;
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

        return [
            'from' => $from,
            'to' => $to,
            'bookings' => (clone $base)->count(),
            'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
            'revenue' => (clone $base)->where('status', 'confirmed')->sum('total_amount'),
            'pendingRefunds' => Refund::where('status', 'pending')->whereBetween('created_at', [$from, $to])->sum('amount'),
            'occupancyRate' => $capacity > 0 ? round($confirmedSlots->count() / $capacity * 100, 1) : 0,
            'byHour' => $byHour,
            'byCourt' => (clone $base)->where('status', 'confirmed')->select('court_id', DB::raw('count(*) as bookings'), DB::raw('sum(total_amount) as revenue'))->groupBy('court_id')->with('court:id,name')->get(),
        ];
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

        $result = $this->fileStorage->store($request->file('image'), 'courts');

        return response()->json($result);
    }
}
