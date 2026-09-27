<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Collection;

class EloquentPlatformPlanRepository implements PlatformPlanRepositoryInterface
{
    public function findById(int $id): ?PlatformPlan
    {
        return PlatformPlan::query()->find($id);
    }

    public function findByIdForUpdate(int $id): ?PlatformPlan
    {
        return PlatformPlan::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function findByCode(string $code): ?PlatformPlan
    {
        return PlatformPlan::query()
            ->where('code', $code)
            ->orderByDesc('is_active')
            ->orderByDesc('version')
            ->first();
    }

    public function findLatestByCodeForUpdate(string $code): ?PlatformPlan
    {
        return PlatformPlan::query()
            ->where('code', $code)
            ->orderByDesc('version')
            ->lockForUpdate()
            ->first();
    }

    public function listByCodeForUpdate(string $code): Collection
    {
        return PlatformPlan::query()
            ->where('code', $code)
            ->orderByDesc('version')
            ->lockForUpdate()
            ->get();
    }

    public function listActive(): Collection
    {
        return PlatformPlan::query()
            ->where('status', 'published')
            ->where('is_active', true)
            ->orderBy('price', 'asc')
            ->get();
    }

    public function listAll(): Collection
    {
        return PlatformPlan::query()
            ->orderBy('code')
            ->orderByDesc('version')
            ->get();
    }

    public function create(array $attributes): PlatformPlan
    {
        return PlatformPlan::create($attributes);
    }

    public function update(PlatformPlan $plan, array $attributes): PlatformPlan
    {
        $plan->update($attributes);

        return $plan->fresh();
    }
}
