<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenueCustomerRepositoryInterface;
use App\Domain\Customer\Rules\CustomerQuarter;
use App\Models\BookingAttendance;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentVenueCustomerRepository implements VenueCustomerRepositoryInterface
{
    public function paginate(int $venueId, CustomerQuarter $quarter, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->customers($venueId, $quarter)
            ->when($search, fn (Builder $query, string $term) => $query->where(fn (Builder $match) => $match
                ->where('u.name', 'ilike', "%{$term}%")
                ->orWhere('u.phone', 'ilike', "%{$term}%")))
            ->orderByDesc('completed')
            ->orderByRaw('last_used_at desc nulls last')
            ->orderBy('u.id')
            ->paginate($perPage);
    }

    public function find(int $venueId, int $userId, CustomerQuarter $quarter): ?object
    {
        return $this->customers($venueId, $quarter)->where('u.id', $userId)->first();
    }

    public function history(int $venueId, int $userId, int $limit): Collection
    {
        return Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->with(['court.venue', 'user', 'attendance.checkedInBy', 'attendance.checkedOutBy', 'attendance.noShowBy', 'slots' => fn ($query) => $query->withoutGlobalScope('tenant_isolation')])
            ->where('user_id', $userId)
            ->whereNotNull('confirmed_at')
            ->whereHas('court', fn ($court) => $court->where('venue_id', $venueId))
            ->orderByDesc('confirmed_at')
            ->limit($limit)
            ->get();
    }

    /** BR-141, BR-142: H by checked_out_at and N by no_show_at inside the quarter; amount and time of H only. */
    private function customers(int $venueId, CustomerQuarter $quarter): Builder
    {
        $done = BookingAttendance::STATUS_CHECKED_OUT;
        $inQuarter = fn (string $column) => "ba.{$column} >= ? and ba.{$column} < ?";
        $range = [$quarter->from, $quarter->to];
        $minutes = DB::table('reservation_slots')
            ->selectRaw('reservation_id, sum(extract(epoch from (ends_at - starts_at)) / 60) as minutes')
            ->where('status', '<>', 'cancelled')
            ->groupBy('reservation_id');

        return DB::table('reservations as r')
            ->join('courts as c', 'c.id', '=', 'r.court_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('booking_attendances as ba', 'ba.reservation_id', '=', 'r.id')
            ->leftJoinSub($minutes, 'sm', 'sm.reservation_id', '=', 'r.id')
            ->where('c.venue_id', $venueId)
            ->whereNotNull('r.confirmed_at')
            ->groupBy('u.id', 'u.name', 'u.phone', 'u.email', 'u.avatar_url')
            ->select('u.id', 'u.name', 'u.phone', 'u.email', 'u.avatar_url')
            ->selectRaw("count(*) filter (where ba.status = ? and {$inQuarter('checked_out_at')}) as completed", [$done, ...$range])
            ->selectRaw("count(*) filter (where ba.status = ? and {$inQuarter('no_show_at')}) as no_show", [BookingAttendance::STATUS_NO_SHOW, ...$range])
            ->selectRaw("coalesce(sum(sm.minutes) filter (where ba.status = ? and {$inQuarter('checked_out_at')}), 0) as used_minutes", [$done, ...$range])
            ->selectRaw("coalesce(sum(r.total_amount) filter (where ba.status = ? and {$inQuarter('checked_out_at')}), 0) as completed_amount", [$done, ...$range])
            ->selectRaw('max(ba.checked_out_at) filter (where ba.status = ?) as last_used_at', [$done]);
    }
}
