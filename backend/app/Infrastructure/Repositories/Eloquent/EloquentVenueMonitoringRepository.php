<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenueMonitoringRepositoryInterface;
use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentVenueMonitoringRepository implements VenueMonitoringRepositoryInterface
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $keyword = isset($filters['keyword']) ? '%'.addcslashes($filters['keyword'], '%_\\').'%' : null;

        return Venue::query()
            ->with(['organization:id,name', 'activeSuspension'])
            ->withCount('courts')
            ->when($filters['organization_id'] ?? null, fn (Builder $q, int $id) => $q->where('organization_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when(array_key_exists('suspended', $filters), fn (Builder $q) => $filters['suspended']
                ? $q->whereHas('activeSuspension')
                : $q->whereDoesntHave('activeSuspension'))
            ->when($keyword, fn (Builder $q, string $like) => $q->where(fn (Builder $match) => $match
                ->whereRaw('CAST(venues.name AS TEXT) ILIKE ?', [$like])
                ->orWhere('address', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhereHas('organization', fn (Builder $org) => $org->where('name', 'ilike', $like))))
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function detail(int $id): Venue
    {
        return Venue::query()->with([
            'organization:id,name',
            'organization.members' => fn ($q) => $q->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)
                ->where('status', 'active')->with('user:id,name,email,phone'),
            'operatingSchedules',
            'courts' => fn ($q) => $q->orderBy('id'),
            'suspensions.suspendedBy:id,name',
            'suspensions.liftedBy:id,name',
            'suspensions.remediation:id,review_note',
        ])->findOrFail($id);
    }
}
