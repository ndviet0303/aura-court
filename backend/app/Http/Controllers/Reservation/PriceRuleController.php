<?php

namespace App\Http\Controllers\Reservation;

use App\Domain\Subscription\Actions\EvaluatePlatformEntitlement;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PriceRule;
use App\Services\Infrastructure\IdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PriceRuleController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function index(Request $request)
    {
        $organization = $this->organization($request);

        return response()->json(['rules' => PriceRule::query()
            ->where('organization_id', $organization->id)
            ->when($request->query('venue_id'), fn ($q, $venueId) => $q->where('venue_id', $venueId))
            ->when($request->query('sport_zone_id'), fn ($q, $zoneId) => $q->where('sport_zone_id', $zoneId))
            ->when($request->query('court_id'), fn ($q, $courtId) => $q->where('court_id', $courtId))
            ->with(['venue', 'sportZone', 'court'])
            ->latest()
            ->get()]);
    }

    public function store(Request $request)
    {
        $organization = $this->organization($request);
        abort_unless($this->isOrganizationOperator($request, $organization), 403);
        $this->assertActivePlatformEntitlement($request, $organization);
        $data = $this->validated($request);
        $this->assertEffectiveRange($data);

        return $this->idempotency->run($request, function () use ($organization, $data) {
            $rule = PriceRule::create(array_merge($data, ['organization_id' => $organization->id, 'version' => 1]));

            return response()->json(['rule' => $rule->load(['venue', 'sportZone', 'court'])], 201);
        });
    }

    public function update(Request $request, string $rule)
    {
        $organization = $this->organization($request);
        abort_unless($this->isOrganizationOperator($request, $organization), 403);
        $this->assertActivePlatformEntitlement($request, $organization);
        $record = PriceRule::query()->where('organization_id', $organization->id)->whereKey($rule)->firstOrFail();
        $data = $this->validated($request, true);
        $this->assertEffectiveRange($data, $record);

        return $this->idempotency->run($request, function () use ($record, $data) {
            $record->update(array_merge($data, ['version' => $record->version + 1]));

            return response()->json(['rule' => $record->fresh(['venue', 'sportZone', 'court'])]);
        });
    }

    public function destroy(Request $request, string $rule)
    {
        $organization = $this->organization($request);
        abort_unless($this->isOrganizationOperator($request, $organization), 403);
        $record = PriceRule::query()->where('organization_id', $organization->id)->whereKey($rule)->firstOrFail();

        return $this->idempotency->run($request, function () use ($record) {
            $record->update(['is_active' => false, 'version' => $record->version + 1]);

            return response()->json(['message' => 'Price rule deactivated.']);
        });
    }

    /**
     * D29: activating or updating a pricing policy is "new business" and requires a
     * valid platform subscription entitlement. Having the right role is not enough —
     * an expired or missing plan is denied, and the response names the actual missing
     * entitlement so the UI can explain it. Platform admins act on the owner's behalf.
     */
    private function assertActivePlatformEntitlement(Request $request, Organization $organization): void
    {
        if ($request->user()->isPlatformAdmin()) {
            return;
        }

        $entitlement = app(EvaluatePlatformEntitlement::class)->execute((int) $organization->id);

        if (! $entitlement['is_valid']) {
            abort(response()->json([
                'message' => 'An active platform subscription is required to manage pricing policy.',
                'code' => $entitlement['reason'] ?? 'SUBSCRIPTION_REQUIRED',
            ], 403));
        }
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : '';

        return $request->validate([
            'venue_id' => $prefix.'nullable|integer',
            'sport_zone_id' => $prefix.'nullable|integer',
            'court_id' => $prefix.'nullable|integer',
            'type' => $prefix.'required|in:base,weekly,peak,date_exception,holiday,standard,special_event,bad_weather,surge',
            'weekday' => $prefix.'nullable|integer|min:1|max:7',
            'exception_date' => $prefix.'nullable|date',
            'starts_at' => $prefix.'nullable|date_format:H:i',
            'ends_at' => $prefix.'nullable|date_format:H:i|after:starts_at',
            'price' => $prefix.'required|integer|min:0',
            'priority' => $prefix.'nullable|integer|min:0|max:10000',
            'effective_from' => $prefix.'nullable|date_format:Y-m-d',
            'effective_until' => $prefix.'nullable|date_format:Y-m-d',
            'is_active' => $prefix.'boolean',
            'metadata' => $prefix.'nullable|array',
        ]);
    }

    private function assertEffectiveRange(array $data, ?PriceRule $existing = null): void
    {
        $from = array_key_exists('effective_from', $data)
            ? $data['effective_from']
            : $existing?->effective_from?->toDateString();
        $until = array_key_exists('effective_until', $data)
            ? $data['effective_until']
            : $existing?->effective_until?->toDateString();

        if ($from !== null && $until !== null && $until < $from) {
            throw ValidationException::withMessages([
                'effective_until' => 'The effective end date must be on or after the effective start date.',
            ]);
        }
    }
}
