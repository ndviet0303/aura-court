<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\CourtPriceSlotRepositoryInterface;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use Illuminate\Database\Eloquent\Collection;

class EloquentCourtPriceSlotRepository implements CourtPriceSlotRepositoryInterface
{
    public function getActiveForCourt(Court $court): Collection
    {
        return $court->priceSlots()->where('is_active', true)->get();
    }

    public function updateOrCreate(Court $court, array $attributes, array $values): CourtPriceSlot
    {
        return $court->priceSlots()->updateOrCreate($attributes, $values);
    }

    public function update(CourtPriceSlot $slot, array $attributes): bool
    {
        return $slot->update($attributes);
    }

    public function delete(CourtPriceSlot $slot): bool
    {
        return $slot->delete();
    }

    public function getOrderedForCourt(Court $court): Collection
    {
        return $court->priceSlots()->orderBy('weekday')->orderBy('starts_at')->get();
    }
}
