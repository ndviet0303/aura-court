<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenueSuspensionRepositoryInterface;
use App\Models\SuspensionRemediation;
use App\Models\Venue;
use App\Models\VenueSuspension;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentVenueSuspensionRepository implements VenueSuspensionRepositoryInterface
{
    private const ACTORS = ['suspendedBy:id,name', 'liftedBy:id,name', 'remediation:id,review_note'];

    public function venue(int $venueId, bool $lock = false): Venue
    {
        return Venue::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($venueId);
    }

    public function active(int $venueId, bool $lock = false): ?VenueSuspension
    {
        return VenueSuspension::query()->where('venue_id', $venueId)->active()->when($lock, fn ($q) => $q->lockForUpdate())->first();
    }

    public function create(array $attributes): VenueSuspension
    {
        return VenueSuspension::create($attributes);
    }

    public function find(int $id, bool $lock = false): VenueSuspension
    {
        return VenueSuspension::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    public function forVenue(int $venueId): Collection
    {
        return VenueSuspension::query()->with(self::ACTORS)
            ->select('venue_suspensions.*')
            ->selectSub(fn ($latest) => $latest->from('suspension_remediations')->select('status')
                ->whereColumn('venue_suspension_id', 'venue_suspensions.id')->orderByDesc('id')->limit(1), 'remediation_status')
            ->where('venue_id', $venueId)->orderByDesc('suspended_at')->orderByDesc('id')->get();
    }

    public function detail(int $id): VenueSuspension
    {
        return VenueSuspension::query()
            ->with([...self::ACTORS, 'venue:id,name,organization_id', 'venue.organization:id,name', 'remediations.submitter:id,name', 'remediations.reviewer:id,name'])
            ->findOrFail($id);
    }

    public function lift(VenueSuspension $suspension, array $attributes): void
    {
        $suspension->update($attributes);
    }

    public function latestRemediation(int $suspensionId): ?SuspensionRemediation
    {
        return SuspensionRemediation::query()->where('venue_suspension_id', $suspensionId)->orderByDesc('id')->first();
    }

    public function createRemediation(array $attributes): SuspensionRemediation
    {
        return SuspensionRemediation::create($attributes);
    }

    public function remediation(int $id, bool $lock = false): SuspensionRemediation
    {
        return SuspensionRemediation::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    public function review(SuspensionRemediation $remediation, array $attributes): void
    {
        $remediation->update($attributes);
    }

    public function paginateRequests(?string $status, int $perPage): LengthAwarePaginator
    {
        return SuspensionRemediation::query()
            ->with(['suspension.venue:id,name,organization_id', 'suspension.venue.organization:id,name', 'submitter:id,name'])
            ->select('suspension_remediations.*')
            ->selectSub(fn ($rows) => $rows->from('suspension_remediations as all_rows')->selectRaw('count(*)')
                ->whereColumn('all_rows.venue_suspension_id', 'suspension_remediations.venue_suspension_id'), 'submission_count')
            ->whereNotExists(fn ($newer) => $newer->from('suspension_remediations as newer')
                ->whereColumn('newer.venue_suspension_id', 'suspension_remediations.venue_suspension_id')
                ->whereColumn('newer.id', '>', 'suspension_remediations.id'))
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('submitted_at')->orderBy('id')
            ->paginate($perPage);
    }
}
