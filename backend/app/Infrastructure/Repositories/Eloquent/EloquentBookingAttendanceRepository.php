<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Models\BookingAttendance;
use App\Models\BookingTransferItem;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\OperationalStop;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EloquentBookingAttendanceRepository implements BookingAttendanceRepositoryInterface
{
    private const ACTORS = ['checkedInBy:id,name', 'checkedOutBy:id,name', 'noShowBy:id,name'];

    public function findByCode(string $code, int $organizationId, bool $lock = false): ?Reservation
    {
        $code = trim(Str::chopStart(trim($code), 'AURA_BOOKING_'));
        if ($code === '') {
            return null;
        }
        // Booking codes are upper case; a code typed by hand may not be. A transfer issues a new check-in code and
        // retires the old one, the V1 code included (module 08.2 D5, BR-188).
        $id = Reservation::query()->where('organization_id', $organizationId)->where('check_in_code', Str::upper($code))->value('id')
            ?? LegacyBookingMapping::query()->where('organization_id', $organizationId)
                ->whereIn('legacy_code', array_unique([$code, Str::upper($code)]))
                ->whereNotExists(fn ($transferred) => $transferred->from('booking_transfer_items')
                    ->whereColumn('booking_transfer_items.reservation_id', 'legacy_booking_mappings.reservation_id')
                    ->where('booking_transfer_items.status', BookingTransferItem::STATUS_COMPLETED))
                ->value('reservation_id');

        return $id === null ? null : $this->withDetails(Reservation::query()->whereKey($id))
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
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

        $attendance->load(self::ACTORS);
        $reservation->setRelation('attendance', $attendance);

        return $attendance;
    }

    public function clear(Reservation $reservation): void
    {
        $reservation->attendance?->delete();
        $metadata = (array) ($reservation->metadata ?? []);
        unset($metadata['operational_status']);
        $reservation->forceFill(['metadata' => $metadata])->save();
        $reservation->setRelation('attendance', null);
    }

    public function daySchedule(int $venueId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        // Slot columns hold UTC and the grammar binds a Carbon without converting it.
        return $this->atVenue($venueId)
            ->whereHas('slots', fn (Builder $slots) => $slots
                ->where('starts_at', '<', $to->copy()->utc())
                ->where('ends_at', '>', $from->copy()->utc()))
            ->get()
            ->sortBy(fn (Reservation $reservation) => $reservation->starts_at)
            ->values();
    }

    public function inUse(int $venueId): Collection
    {
        return $this->atVenue($venueId)
            ->whereHas('attendance', fn (Builder $attendance) => $attendance->where('status', BookingAttendance::STATUS_CHECKED_IN))
            ->get()
            ->sortBy(fn (Reservation $reservation) => $reservation->ends_at)
            ->values();
    }

    public function inUseEndingBetween(CarbonInterface $since, CarbonInterface $until): Collection
    {
        // The booked end is the end of the last slot: none ends after $until and one ends after $since.
        return $this->withDetails(Reservation::query())
            ->where('status', 'confirmed')
            ->whereHas('attendance', fn (Builder $attendance) => $attendance->where('status', BookingAttendance::STATUS_CHECKED_IN))
            ->whereDoesntHave('slots', fn (Builder $slots) => $slots->where('ends_at', '>', $until->copy()->utc()))
            ->whereHas('slots', fn (Builder $slots) => $slots->where('ends_at', '>', $since->copy()->utc()))
            ->get();
    }

    public function courtInService(Court $court, CarbonInterface $at): bool
    {
        return $court->status === Court::STATUS_ACTIVE
            && ! OperationalStop::query()->affectingCourt($court, (int) $court->venue_id)->overlapping($at, $at->copy()->addSecond())->exists();
    }

    /**
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    private function withDetails(Builder $query): Builder
    {
        return $query->with(['court.venue', 'slots', 'user:id,name,phone', 'attendance' => fn ($attendance) => $attendance->with(self::ACTORS)]);
    }

    /** @return Builder<Reservation> */
    private function atVenue(int $venueId): Builder
    {
        return $this->withDetails(Reservation::query())
            ->where('status', 'confirmed')
            ->whereHas('court', fn (Builder $court) => $court->where('venue_id', $venueId));
    }
}
