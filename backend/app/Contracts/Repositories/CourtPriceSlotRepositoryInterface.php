<?php

namespace App\Contracts\Repositories;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use Illuminate\Database\Eloquent\Collection;

interface CourtPriceSlotRepositoryInterface
{
    public function getActiveForCourt(Court $court): Collection;

    public function updateOrCreate(Court $court, array $attributes, array $values): CourtPriceSlot;

    public function update(CourtPriceSlot $slot, array $attributes): bool;

    public function delete(CourtPriceSlot $slot): bool;

    public function getOrderedForCourt(Court $court): Collection;
}
