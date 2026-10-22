<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\OwnerFinanceReportRepositoryInterface;
use App\Domain\Finance\DTOs\OwnerFinanceFilterData;
use App\Models\BookingAttendance;
use App\Models\OwnerPaymentReceipt;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

/**
 * D12: each metric is its own query over recorded rows, so the metrics never add up into a revenue total.
 * ponytail: rows are not paginated (D12 asks for full lists); add paging if year views of large organizations get slow.
 */
class EloquentOwnerFinanceReportRepository implements OwnerFinanceReportRepositoryInterface
{
    private const TENANT_SCOPE = 'tenant_isolation';

    public function metrics(OwnerFinanceFilterData $filter): array
    {
        return [
            'received' => $this->summarize($this->received($filter)),
            'completed' => $this->summarize($this->attended($filter, BookingAttendance::STATUS_CHECKED_OUT, 'checked_out_at')),
            'unfinished' => $this->summarize($this->unfinished($filter)),
            'no_show' => $this->summarize($this->attended($filter, BookingAttendance::STATUS_NO_SHOW, 'no_show_at')),
            'incident' => $this->summarize($this->incident($filter)),
        ];
    }

    /**
     * R: allocated receipts of booking, split-share and recurring-period payments by their transaction time, adding the amount received.
     * A recurring receipt pays several occurrences, so each row carries its occurrence amount and the rows add up to the receipt.
     *
     * @return Builder<Reservation>
     */
    private function received(OwnerFinanceFilterData $filter): Builder
    {
        return $this->bookings($filter, 'pr.event_at', "case when pa.purpose = 'recurring_period' then ro.amount else pr.amount end")
            ->leftJoin('split_groups as sg', 'sg.reservation_id', '=', 'reservations.id')
            ->leftJoin('recurring_occurrences as ro', 'ro.reservation_id', '=', 'reservations.id')
            ->join('owner_payment_allocations as pa', fn (JoinClause $join) => $join
                ->on('pa.organization_id', '=', 'reservations.organization_id')
                // The ':' after the id keeps group or series 1 from matching 12.
                ->where(fn ($target) => $target
                    ->where(fn ($booking) => $booking->where('pa.purpose', 'booking')->whereColumn('pa.purpose_id', 'reservations.code'))
                    ->orWhere(fn ($split) => $split->where('pa.purpose', 'split_share')->whereRaw("pa.purpose_id like 'split_group:' || sg.id || ':%'"))
                    ->orWhere(fn ($recurring) => $recurring->where('pa.purpose', 'recurring_period')->whereRaw("pa.purpose_id like 'rec_series:' || ro.recurring_series_id || ':%'"))))
            ->join('owner_payment_receipts as pr', 'pr.id', '=', 'pa.payment_receipt_id')
            ->where('pr.status', OwnerPaymentReceipt::STATUS_ALLOCATED)
            // A recurring receipt confirmed exactly the occurrences it paid, stamping each confirmed_at with its event_at.
            ->where(fn ($query) => $query->where('pa.purpose', '<>', 'recurring_period')->orWhereColumn('reservations.confirmed_at', 'pr.event_at'));
    }

    /**
     * H (checked out) and N (no-show) by the attendance time; attendance never changes the reservation status.
     *
     * @return Builder<Reservation>
     */
    private function attended(OwnerFinanceFilterData $filter, string $status, string $column): Builder
    {
        return $this->bookings($filter, "ba.{$column}")
            ->join('booking_attendances as ba', 'ba.reservation_id', '=', 'reservations.id')
            ->where('ba.status', $status);
    }

    /**
     * U: paid in the period and, when viewed, still confirmed or in use with no checked-out or no-show attendance.
     *
     * @return Builder<Reservation>
     */
    private function unfinished(OwnerFinanceFilterData $filter): Builder
    {
        return $this->bookings($filter, 'reservations.confirmed_at')
            ->whereIn('reservations.status', ['confirmed', 'in_use'])
            ->whereNotExists(fn ($query) => $query->from('booking_attendances as ba')
                ->whereColumn('ba.reservation_id', 'reservations.id')
                ->whereIn('ba.status', [BookingAttendance::STATUS_CHECKED_OUT, BookingAttendance::STATUS_NO_SHOW]));
    }

    /**
     * S: paid bookings the venue cancelled for an operational incident (module 2 D8), by the cancellation time.
     * Moved bookings and open cases stay confirmed, and a booking cancelled before payment has no confirmed_at.
     *
     * @return Builder<Reservation>
     */
    private function incident(OwnerFinanceFilterData $filter): Builder
    {
        return $this->bookings($filter, 'reservations.cancelled_at')
            ->where('reservations.status', 'cancelled')
            ->whereNotNull('reservations.confirmed_at')
            ->where('reservations.metadata->release_reason', 'operator_affected_booking');
    }

    /**
     * The organization's bookings at the filtered venue and current court whose $at column falls in the period.
     *
     * @return Builder<Reservation>
     */
    private function bookings(OwnerFinanceFilterData $filter, string $at, string $amount = 'reservations.total_amount'): Builder
    {
        return Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->select(['reservations.*', "{$at} as finance_at"])
            ->selectRaw("{$amount} as finance_amount")
            ->withCasts(['finance_at' => 'datetime', 'finance_amount' => 'integer'])
            ->with(['court.venue', 'slots' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE)])
            ->where('reservations.organization_id', $filter->organizationId)
            ->when($filter->venueId, fn ($query, int $venueId) => $query->whereHas('court', fn ($court) => $court->where('courts.venue_id', $venueId)))
            ->when($filter->courtId, fn ($query, int $courtId) => $query->where('reservations.court_id', $courtId))
            ->where($at, '>=', $filter->startsAt)
            ->where($at, '<', $filter->endsAt)
            ->orderBy($at)
            ->orderBy('reservations.id');
    }

    /**
     * @param  Builder<Reservation>  $query
     * @return array{amount: int, count: int, rows: Collection<int, Reservation>}
     */
    private function summarize(Builder $query): array
    {
        $rows = $query->get();

        return [
            'amount' => (int) $rows->sum('finance_amount'),
            'count' => $rows->pluck('code')->unique()->count(),
            'rows' => $rows,
        ];
    }
}
