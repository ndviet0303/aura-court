<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\AffectedReservationCaseRepositoryInterface;
use App\Models\AffectedReservationCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentAffectedReservationCaseRepository implements AffectedReservationCaseRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $live = $status === null || in_array($status, AffectedReservationCase::OPEN_STATUSES, true);
        $reservation = fn ($query) => $query->withoutGlobalScope('tenant_isolation');

        return AffectedReservationCase::query()
            ->with([
                'reservation' => fn ($query) => $reservation($query)->with([
                    'user:id,name,phone',
                    'court:id,code,venue_id',
                    'slots' => fn ($slots) => $slots->withoutGlobalScope('tenant_isolation'),
                ]),
                'venue:id,name',
                'handledBy:id,name',
                'stop',
                'suspension',
            ])
            ->whereIn('venue_id', $filters['venue_ids'])
            ->when($status, fn (Builder $query) => $query->where('status', $status), fn (Builder $query) => $query->open())
            ->when($filters['source_type'] ?? null, fn (Builder $query, string $type) => $query->where('source_type', $type))
            ->when($filters['court_id'] ?? null, fn (Builder $query, int $courtId) => $query->where('court_id', $courtId))
            ->when($live, fn (Builder $query) => $query->whereHas('reservation', fn ($r) => $reservation($r)
                ->where(fn ($s) => $s->whereIn('status', ['confirmed', 'in_use'])
                    ->orWhere(fn ($held) => $held->where('status', 'held')
                        ->where(fn ($deadline) => $deadline->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))))
                ->where(fn ($done) => $done->whereNull('metadata->operational_status')
                    ->orWhereNotIn('metadata->operational_status', ['completed', 'no_show']))))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id, bool $lock = false): ?AffectedReservationCase
    {
        return AffectedReservationCase::query()->whereKey($id)->when($lock, fn (Builder $query) => $query->lockForUpdate())->first();
    }

    public function update(AffectedReservationCase $case, array $attributes): AffectedReservationCase
    {
        $case->forceFill($attributes)->save();

        return $case;
    }
}
