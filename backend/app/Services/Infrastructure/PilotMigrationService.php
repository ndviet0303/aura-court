<?php

namespace App\Services\Infrastructure;

use App\Services\Reservation\LegacyBookingBackfillService;

use App\Models\Booking;
use App\Models\Organization;
use App\Models\PilotMigrationRun;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class PilotMigrationService
{
    public function __construct(private readonly LegacyBookingBackfillService $backfill) {}

    public function run(array $organizationIds, bool $rehearsal = false): PilotMigrationRun
    {
        $organizationIds = array_values(array_unique(array_map('intval', $organizationIds)));
        abort_if($organizationIds === [], 422, 'At least one pilot organization is required.');
        if (! $rehearsal) {
            abort_unless(count($organizationIds) === 5, 422, 'Controlled Phase 1 migration requires the five approved pilot organizations.');
        }

        $run = PilotMigrationRun::create([
            'mode' => $rehearsal ? 'rehearsal' : 'apply',
            'status' => 'running',
            'organization_ids' => $organizationIds,
            'started_at' => now(),
        ]);
        try {
            $results = [];
            foreach ($organizationIds as $organizationId) {
                Organization::findOrFail($organizationId);
                $source = $this->source($organizationId);
                $result = $rehearsal ? $this->backfill->validate($organizationId) : $this->backfill->backfill($organizationId);
                $target = $this->target($organizationId);
                $results[$organizationId] = ['source' => $source, 'target' => $target, 'backfill' => $result];
                abort_unless($source['booking_count'] === $target['reservation_count'] && $source['amount'] === $target['amount'], 409, "Migration checksum mismatch for organization {$organizationId}.");
            }

            $source = $this->aggregate(array_column($results, 'source'));
            $target = $this->aggregate(array_column($results, 'target'));
            $run->update([
                'status' => 'passed',
                'source_booking_count' => $source['booking_count'],
                'target_reservation_count' => $target['reservation_count'],
                'source_amount' => $source['amount'],
                'target_amount' => $target['amount'],
                'source_checksum' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR)),
                'target_checksum' => hash('sha256', json_encode($target, JSON_THROW_ON_ERROR)),
                'results' => $results,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $run->update(['status' => 'blocked', 'failure_reason' => $exception->getMessage(), 'completed_at' => now()]);
            throw $exception;
        }

        return $run->fresh();
    }

    private function source(int $organizationId): array
    {
        $bookings = Booking::query()->whereHas('court.venue', fn ($query) => $query->where('organization_id', $organizationId))->get();
        return ['booking_count' => $bookings->count(), 'amount' => (int) $bookings->sum('total_amount')];
    }

    private function target(int $organizationId): array
    {
        $reservations = Reservation::query()->where('organization_id', $organizationId)->whereHas('legacyMapping')->get();
        return ['reservation_count' => $reservations->count(), 'amount' => (int) $reservations->sum('total_amount')];
    }

    private function aggregate(array $rows): array
    {
        return [
            'booking_count' => array_sum(array_column($rows, 'booking_count')),
            'reservation_count' => array_sum(array_column($rows, 'reservation_count')),
            'amount' => array_sum(array_column($rows, 'amount')),
        ];
    }
}
