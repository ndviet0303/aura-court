<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\RecurringSeriesPreviewRequest;
use App\Models\MarketplaceListing;
use App\Models\MonthlyInvoice;
use App\Models\Organization;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\Reservation;
use App\Models\SplitGroup;
use App\Models\SplitShare;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\Payment\SplitPaymentService;
use App\Services\Social\MarketplaceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdvancedBookingController extends Controller
{
    use ResolvesOrganization;

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
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/auth/email/verification-notification.');
        }
        $organization = $this->organization($request);
        $data = $request->validate([
            'court_id' => 'required|integer',
            'frequency' => 'required|in:weekly,biweekly,monthly',
            'weekdays' => 'sometimes|array',
            'weekdays.*' => 'integer|min:1|max:7',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'until' => 'nullable|date|after_or_equal:starts_at',
            'occurrences' => 'nullable|integer|min:1|max:104',
            'accept_partial' => 'sometimes|boolean',
            'accepted' => 'sometimes|array',
            'accepted.total' => 'required_with:accepted|integer|min:0',
            'accepted.occurrences' => 'required_with:accepted|array|min:1',
            'accepted.occurrences.*.starts_at' => 'required|date',
            'accepted.occurrences.*.price' => 'required|integer|min:0',
            ...RecurringSeriesPreviewRequest::NO_BENEFITS,
        ], RecurringSeriesPreviewRequest::NO_BENEFIT_MESSAGES);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $result = $this->recurring->createAndConfirm($request->user(), $organization->id, $data);
            // A partial-conflict negotiation returns the excluded occurrences for explicit re-accept.
            if (is_array($result)) {
                return response()->json($result, 200);
            }

            return response()->json(['series' => $result], 201);
        });
    }

    public function previewSeries(RecurringSeriesPreviewRequest $request)
    {
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/auth/email/verification-notification.');
        }
        $this->organization($request);

        return response()->json($this->recurring->previewSeries($user, $request->validated()));
    }

    public function invoice(Request $request, string $invoiceKey)
    {
        $invoice = MonthlyInvoice::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where(function ($q) use ($invoiceKey) {
                if (Str::isUuid($invoiceKey)) {
                    $q->where('uuid', $invoiceKey);
                } elseif (is_numeric($invoiceKey)) {
                    $q->where('id', (int) $invoiceKey);
                } else {
                    $q->where('uuid', $invoiceKey);
                }
            })
            ->firstOrFail();

        abort_unless($invoice->series()->withoutGlobalScope('tenant_isolation')->where('user_id', $request->user()->id)->exists(), 404);

        $invoice->load(['series.court.venue', 'series.user']);

        $periodStart = Carbon::parse($invoice->period_start)->startOfDay();
        $periodEnd = Carbon::parse($invoice->period_end)->endOfDay();

        $occurrences = RecurringOccurrence::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('recurring_series_id', $invoice->recurring_series_id)
            ->whereBetween('starts_at', [$periodStart, $periodEnd])
            ->with(['reservation'])
            ->orderBy('starts_at')
            ->get();

        // Direct-owner per-period payment surface: expose this period's recurring_period attempt
        // (or the whole-series attempt for full_series venues) using the shared instructions shape.
        $attemptService = app(OwnerPaymentAttemptService::class);
        $attempt = $attemptService->latestForPurpose('recurring_period', 'rec_series:'.$invoice->recurring_series_id.':invoice:'.$invoice->id)
            ?? $attemptService->latestForPurpose('recurring_period', 'rec_series:'.$invoice->recurring_series_id.':full');

        return response()->json([
            'invoice' => $invoice,
            'occurrences' => $occurrences,
            'payment_attempt' => $attempt ? app(ReservationPaymentService::class)->instructions($attempt) : null,
        ]);
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
        $data = $request->validate(['payment_reference' => 'nullable|string|max:160']);

        return $this->idempotency->run($request, fn () => response()->json(['split' => $this->splits->coverShortfall($split, $request->user(), $data['payment_reference'] ?? null)]));
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
            $query->availableTo($request->user()?->id);
        }

        return $query->paginate(30);
    }

    public function withdrawListing(Request $request, MarketplaceListing $listing)
    {
        $organization = $this->organization($request);
        abort_unless($listing->organization_id === $organization->id, 404);

        return $this->idempotency->run($request, fn () => response()->json(['listing' => $this->marketplace->withdraw($listing, $request->user())]));
    }
}
