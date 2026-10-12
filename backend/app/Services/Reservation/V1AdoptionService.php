<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\User;
use App\Models\V1AdoptionSnapshot;
use Carbon\CarbonInterface;

class V1AdoptionService
{
    public function __construct(private readonly LegacyBookingBackfillService $backfill) {}

    public function capture(CarbonInterface $start, CarbonInterface $end): V1AdoptionSnapshot
    {
        $bookings = Booking::whereBetween('created_at', [$start, $end]);

        return V1AdoptionSnapshot::updateOrCreate(['period_start' => $start->toDateString(), 'period_end' => $end->toDateString()], [
            'legacy_booking_count' => (clone $bookings)->count(),
            'v2_reservation_count' => Reservation::whereBetween('created_at', [$start, $end])->count(),
            'legacy_active_count' => (clone $bookings)->whereIn('status', ['pending_payment', 'confirmed'])->count(),
            'legacy_user_count' => User::whereHas('bookings', fn ($query) => $query->whereBetween('bookings.created_at', [$start, $end]))->count(),
            'evidence' => ['captured_at' => now()->toIso8601String()],
        ]);
    }

    public function retirementReadiness(?string $approvedChangePath): array
    {
        $exists = $approvedChangePath && is_file(base_path('../'.$approvedChangePath.'/proposal.md')) && is_file(base_path('../'.$approvedChangePath.'/tasks.md'));
        $latest = V1AdoptionSnapshot::latest()->first();
        $audit = $this->backfill->audit();

        return [
            'ready' => (bool) ($exists && $latest && $audit['valid']),
            'approved_change_present' => $exists,
            'adoption_snapshot_present' => (bool) $latest,
            'legacy_mapping_valid' => $audit['valid'],
            'unmapped_bookings' => array_merge($audit['orphans'], $audit['collisions'], $audit['pending']),
            'message' => match (true) {
                ! $exists => 'A separate approved v1 retirement change is required before retirement.',
                ! $latest => 'V1 adoption evidence is required before retirement.',
                ! $audit['valid'] => 'Every legacy booking must map to exactly one reservation before retirement.',
                default => 'Separate approved change, adoption evidence and complete legacy mappings found.',
            },
        ];
    }
}
