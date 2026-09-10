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

    public function findByCode(string $code): ?PlatformPlan
    {
        return PlatformPlan::query()->where('code', $code)->first();
    }

    public function listActive(): Collection
    {
        return PlatformPlan::query()
            ->where('is_active', true)
            ->orderBy('price', 'asc')
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
