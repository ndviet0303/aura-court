<?php

namespace App\Contracts\Repositories;

use App\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Collection;

interface PlatformPlanRepositoryInterface
{
    public function findById(int $id): ?PlatformPlan;

    public function findByCode(string $code): ?PlatformPlan;

    /**
     * @return Collection<int, PlatformPlan>
     */
    public function listActive(): Collection;

    public function create(array $attributes): PlatformPlan;

    public function update(PlatformPlan $plan, array $attributes): PlatformPlan;
}
