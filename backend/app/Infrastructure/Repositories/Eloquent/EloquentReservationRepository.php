<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\VoucherUse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EloquentReservationRepository implements ReservationRepositoryInterface
{
    private const TENANT_SCOPE = 'tenant_isolation';

    public function paginateUserReservations(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->where('user_id', $userId)
            ->with([
                'court.venue',
                'quote' => $this->unscoped(),
                'slots' => $this->unscoped(),
                'entitlements' => $this->unscoped(),
                'legacyMapping' => $this->legacyMapping(),
                // BR-147: a deleted review still blocks a new one, so the customer's list shows it too.
                'review' => fn ($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
                'attendance' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE)->select(['id', 'reservation_id', 'status', 'checked_out_at']),
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function paginateAdminReservations(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        $venueIds = $filters['venue_ids'] ?? null;

        return Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->with(['user', 'court', 'slots' => $this->unscoped(), 'legacyMapping' => $this->legacyMapping()])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereHas('slots', fn ($s) => $s->withoutGlobalScope(self::TENANT_SCOPE)->whereDate('starts_at', $date)))
            // An empty list must match nothing, never everything.
            ->when($venueIds !== null, fn ($q) => $q->whereHas('court', fn ($c) => $c->whereIn('venue_id', $venueIds)))
            ->latest()
            ->paginate($perPage);
    }

    public function unreleasedSlots(int $reservationId): Collection
    {
        return ReservationSlot::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->where('reservation_id', $reservationId)
            ->whereIn('status', ['held', 'confirmed'])
            ->orderBy('starts_at')
            ->get();
    }

    public function heldWithVoucherOf(int $programId): Collection
    {
        return Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->where('status', 'held')
            ->whereIn('id', VoucherUse::query()->select('reservation_id')->where('program_id', $programId)->where('status', VoucherUse::STATUS_HELD))
            ->orderBy('id')
            ->get(['id', 'code']);
    }

    private function unscoped(): \Closure
    {
        return fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE);
    }

    private function legacyMapping(): \Closure
    {
        return fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE)->select('id', 'reservation_id', 'legacy_code', 'legacy_status');
    }
}
