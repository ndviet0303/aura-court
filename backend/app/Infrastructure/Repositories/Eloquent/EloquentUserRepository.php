<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByIdWithLock(int $id): ?User
    {
        return User::query()->lockForUpdate()->find($id);
    }

    public function update(User $user, array $attributes): User
    {
        $user->forceFill($attributes)->save();

        return $user->fresh();
    }

    /** @return LengthAwarePaginator<int, User> */
    public function paginateForAdmin(?string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->select(['id', 'name', 'email', 'role', 'status', 'created_at'])
            ->when($search, function ($query, string $search) {
                $term = '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%';
                $query->where(fn ($q) => $q->whereRaw('lower(email) like ?', [$term])->orWhereRaw('lower(name) like ?', [$term]));
            })
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** @return list<int> */
    public function activeOwnerIds(?int $organizationId = null): array
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('organizationMembers', fn ($query) => $query
                ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)
                ->where('status', 'active')
                ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId)))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function activeVenueOperatorIds(int $organizationId, int $venueId): array
    {
        $staffIds = OrganizationMember::query()
            ->where('organization_id', $organizationId)
            ->where('role', OrganizationMember::ROLE_STAFF)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->filter(fn (OrganizationMember $member) => in_array($venueId, $member->assignedVenueIds(), true))
            ->map(fn (OrganizationMember $member) => (int) $member->user_id);

        return collect($this->activeOwnerIds($organizationId))->merge($staffIds)->unique()->sort()->values()->all();
    }
}
