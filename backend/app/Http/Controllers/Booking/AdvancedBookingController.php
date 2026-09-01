<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\MarketplaceListing;
use App\Models\MonthlyInvoice;
use App\Models\RecurringSeries;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\SplitGroup;
use App\Models\SplitShare;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Social\MarketplaceService;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Payment\SplitPaymentService;
use Illuminate\Http\Request;

class AdvancedBookingController extends Controller
{
    use ResolvesV2Organization;

    public function __construct(
        private readonly RecurringSeriesService $recurring,
        private readonly SplitPaymentService $splits,
        private readonly MarketplaceService $marketplace,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function series(Request $request)
    {
        return RecurringSeries::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('user_id', $request->user()->id)
            ->with([
                'court.venue',
                'occurrences' => fn ($q) => $q->withoutGlobalScope('tenant_isolation'),
                'invoices' => fn ($q) => $q->withoutGlobalScope('tenant_isolation'),
            ])
            ->latest()
            ->paginate(20);
    }

    public function createSeries(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['court_id' => 'required|integer', 'frequency' => 'required|in:weekly,biweekly,monthly', 'starts_at' => 'required|date', 'ends_at' => 'nullable|date|after:starts_at', 'until' => 'nullable|date|after_or_equal:starts_at', 'occurrences' => 'nullable|integer|min:1|max:104', 'coupon_code' => 'nullable|string|max:40']);
        return $this->idempotency->run($request, fn () => response()->json(['series' => $this->recurring->createAndConfirm($request->user(), $organization->id, $data)], 201));
    }

    public function invoice(Request $request, MonthlyInvoice $invoice)
    {
        abort_unless($invoice->series()->withoutGlobalScope('tenant_isolation')->where('user_id', $request->user()->id)->exists() || $request->user()->isPlatformAdmin(), 404);
        
        $invoice->load(['series.court.venue', 'series.user']);
        
        $periodStart = \Carbon\Carbon::parse($invoice->period_start)->startOfDay();
        $periodEnd = \Carbon\Carbon::parse($invoice->period_end)->endOfDay();

        $occurrences = \App\Models\RecurringOccurrence::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('recurring_series_id', $invoice->recurring_series_id)
            ->whereBetween('starts_at', [$periodStart, $periodEnd])
            ->with(['reservation'])
            ->orderBy('starts_at')
            ->get();
            
        return response()->json([
            'invoice' => $invoice,
            'occurrences' => $occurrences,
        ]);
    }

    public function payInvoice(Request $request, MonthlyInvoice $invoice)
    {
        abort_unless($invoice->series()->withoutGlobalScope('tenant_isolation')->where('user_id', $request->user()->id)->exists() || $request->user()->isPlatformAdmin(), 404);
        $data = $request->validate(['payment_reference' => 'required|string|max:160']);
        return $this->idempotency->run($request, fn () => response()->json(['invoice' => $this->recurring->payInvoice($invoice, $data['payment_reference'])]));
    }

    public function createSplit(Request $request, Reservation $reservation)
    {
        $organization = $this->organization($request);
        abort_unless($reservation->organization_id === $organization->id && $reservation->user_id === $request->user()->id, 404);
        $data = $request->validate(['shares' => 'required|array|min:2|max:8', 'shares.*.amount' => 'required|integer|min:1']);
        return $this->idempotency->run($request, fn () => response()->json(['split' => $this->splits->create($reservation, $request->user(), $data['shares'])], 201));
    }

    public function split(Request $request, SplitGroup $split)
    {
        $organization = $this->organization($request);
        abort_unless($split->organization_id === $organization->id, 404);
        return response()->json(['split' => $split->load('shares', 'reservation')]);
    }

    public function paySplitShare(Request $request, SplitShare $share)
    {
        $organization = $this->organization($request);
        abort_unless($share->organization_id === $organization->id, 404);
        $data = $request->validate(['amount' => 'required|integer|min:1', 'payment_reference' => 'required|string|max:160']);
        return $this->idempotency->run($request, fn () => response()->json(['split' => $this->splits->payShare($share, $request->user(), $data['amount'], $data['payment_reference'])]));
    }

    public function coverSplit(Request $request, SplitGroup $split)
    {
        $organization = $this->organization($request);
        abort_unless($split->organization_id === $organization->id, 404);
        $data = $request->validate(['payment_reference' => 'required|string|max:160']);
        return $this->idempotency->run($request, fn () => response()->json(['split' => $this->splits->coverShortfall($split, $request->user(), $data['payment_reference'])]));
    }

    public function listings(Request $request)
    {
        $organization = null;
        if ($request->user()) {
            try {
                $organization = $this->organization($request);
            } catch (\Throwable $e) {
                // Ignore auth/tenant exception for public browsing
            }
        }

        if (! $organization) {
            $orgId = $request->header('X-Organization-Id') ?? $request->input('organization_id');
            if ($orgId) {
                $organization = Organization::where('id', $orgId)->first();
            }
            if (! $organization) {
                $organization = Organization::whereHas('venues')->first() ?? Organization::first();
            }
        }

        $query = MarketplaceListing::query()
            ->when($organization, fn ($q) => $q->where('organization_id', $organization->id))
            ->with(['entitlement.court.venue', 'seller'])
            ->latest();

        if ($request->boolean('mine')) {
            abort_unless($request->user(), 401, 'Unauthenticated.');
            $query->where('seller_id', $request->user()->id);
        } else {
            $query->where('status', 'listed')->where('cutoff_at', '>', now());
        }

        return $query->paginate(30);
    }

    public function createListing(Request $request, ReservationEntitlement $entitlement)
    {
        $organization = $this->organization($request);
        abort_unless($entitlement->organization_id === $organization->id && $entitlement->user_id === $request->user()->id, 404);
        $data = $request->validate(['asking_price' => 'required|integer|min:1']);
        return $this->idempotency->run($request, fn () => response()->json(['listing' => $this->marketplace->list($entitlement, $request->user(), $data['asking_price'])], 201));
    }

    public function purchaseListing(Request $request, MarketplaceListing $listing)
    {
        $organization = $this->organization($request);
        abort_unless($listing->organization_id === $organization->id, 404);
        $data = $request->validate(['buyer_payment_reference' => 'required|string|max:160', 'seller_refund_reference' => 'nullable|string|max:160', 'seller_refund_accepted' => 'sometimes|boolean']);
        return $this->idempotency->run($request, fn () => response()->json(['transfer' => $this->marketplace->purchase($listing, $request->user(), (string) $request->header('Idempotency-Key'), $data['buyer_payment_reference'], $data['seller_refund_reference'] ?? null, (bool) ($data['seller_refund_accepted'] ?? true))]));
    }

    public function withdrawListing(Request $request, MarketplaceListing $listing)
    {
        $organization = $this->organization($request);
        abort_unless($listing->organization_id === $organization->id, 404);
        return $this->idempotency->run($request, fn () => response()->json(['listing' => $this->marketplace->withdraw($listing, $request->user())]));
    }
}
