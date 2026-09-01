<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\PriceRule;
use App\Services\Infrastructure\IdempotencyService;
use Illuminate\Http\Request;

class V2PriceRuleController extends Controller
{
    use ResolvesV2Organization;

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
        $data = $this->validated($request);

        return $this->idempotency->run($request, function () use ($organization, $data) {
            $rule = PriceRule::create(array_merge($data, ['organization_id' => $organization->id, 'version' => 1]));

            return response()->json(['rule' => $rule->load(['venue', 'sportZone', 'court'])], 201);
        });
    }

    public function update(Request $request, string $rule)
    {
        $organization = $this->organization($request);
        abort_unless($this->isOrganizationOperator($request, $organization), 403);
        $record = PriceRule::query()->where('organization_id', $organization->id)->whereKey($rule)->firstOrFail();
        $data = $this->validated($request, true);

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
            'is_active' => $prefix.'boolean',
            'metadata' => $prefix.'nullable|array',
        ]);
    }
}

