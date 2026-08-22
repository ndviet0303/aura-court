<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\User;
use App\Models\V1AdoptionSnapshot;
use Carbon\CarbonInterface;

class V1AdoptionService
{
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
        return ['ready' => (bool) ($exists && $latest), 'approved_change_present' => $exists, 'adoption_snapshot_present' => (bool) $latest, 'message' => $exists ? 'Separate approved change and adoption evidence found.' : 'A separate approved v1 retirement change is required before retirement.'];
    }
}
