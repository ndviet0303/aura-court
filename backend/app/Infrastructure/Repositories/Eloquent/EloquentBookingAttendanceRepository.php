<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\OperationalStop;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class EloquentBookingAttendanceRepository implements BookingAttendanceRepositoryInterface
{
    public function findByCode(string $code, int $organizationId, bool $lock = false): ?Reservation
    {
        $code = trim(Str::chopStart(trim($code), 'AURA_BOOKING_'));
        if ($code === '') {
            return null;
        }
        // Booking codes are upper case; a code typed by hand may not be.
        $id = Reservation::query()->where('organization_id', $organizationId)->where('code', Str::upper($code))->value('id')
            ?? LegacyBookingMapping::query()->where('organization_id', $organizationId)
                ->whereIn('legacy_code', array_unique([$code, Str::upper($code)]))->value('reservation_id');

        return $id === null ? null : Reservation::query()
            ->whereKey($id)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->with(['court.venue', 'slots', 'user:id,name,phone', 'attendance' => fn ($query) => $query->with([
                'checkedInBy:id,name', 'checkedOutBy:id,name', 'noShowBy:id,name',
            ])])
            ->first();
    }

    public function record(Reservation $reservation, array $attributes): BookingAttendance
    {
        $attendance = $reservation->attendance ?? new BookingAttendance([
            'organization_id' => $reservation->organization_id,
            'reservation_id' => $reservation->id,
            'legacy_booking_id' => LegacyBookingMapping::query()->where('reservation_id', $reservation->id)->value('legacy_booking_id'),
        ]);
        $attendance->fill($attributes)->save();

        $metadata = (array) ($reservation->metadata ?? []);
        $metadata['operational_status'] = BookingAttendance::OPERATIONAL_STATUS[$attendance->status];
        $reservation->forceFill(['metadata' => $metadata])->save();

        $attendance->load(['checkedInBy:id,name', 'checkedOutBy:id,name', 'noShowBy:id,name']);
        $reservation->setRelation('attendance', $attendance);

        return $attendance;
    }

    public function courtInService(Court $court, CarbonInterface $at): bool
    {
        return $court->status === Court::STATUS_ACTIVE
            && ! OperationalStop::query()->affectingCourt($court, (int) $court->venue_id)->overlapping($at, $at->copy()->addSecond())->exists();
    }
}
