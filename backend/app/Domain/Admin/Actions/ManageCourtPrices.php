<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\CourtPriceSlotRepositoryInterface;
use App\Models\Court;
use App\Models\CourtPriceSlot;

class ManageCourtPrices
{
    public function __construct(
        private readonly CourtPriceSlotRepositoryInterface $priceSlots,
    ) {}

    public function createOrUpdatePrice(Court $court, array $data): CourtPriceSlot
    {
        abort_if($data['ends_at'] !== now()->setTimeFromTimeString($data['starts_at'])->addHour()->format('H:i'), 422, 'Khung giá phải dài đúng 60 phút.');

        return $this->priceSlots->updateOrCreate(
            $court,
            ['weekday' => $data['weekday'], 'starts_at' => $data['starts_at']],
            $data
        );
    }

    public function bulkUpdate(Court $court, array $data): array
    {
        $createdOrUpdated = [];
        foreach ($data['weekdays'] as $weekday) {
            for ($hour = $data['start_hour']; $hour < $data['end_hour']; $hour++) {
                $startsAt = sprintf('%02d:00', $hour);
                $endsAt = sprintf('%02d:00', $hour + 1);
                $slot = $this->priceSlots->updateOrCreate(
                    $court,
                    ['weekday' => $weekday, 'starts_at' => $startsAt],
                    ['ends_at' => $endsAt, 'price' => $data['price'], 'is_active' => true]
                );
                $createdOrUpdated[] = $slot;
            }
        }

        return [
            'count' => count($createdOrUpdated),
            'slots' => $this->priceSlots->getOrderedForCourt($court),
        ];
    }

    public function updatePrice(Court $court, CourtPriceSlot $price, array $data): CourtPriceSlot
    {
        abort_unless($price->court_id === $court->id, 404);
        $merged = array_merge($price->only(['weekday', 'starts_at', 'ends_at']), $data);
        abort_if($merged['ends_at'] !== now()->setTimeFromTimeString($merged['starts_at'])->addHour()->format('H:i'), 422, 'Khung giá phải dài đúng 60 phút.');

        $this->priceSlots->update($price, $data);

        return $price->fresh();
    }

    public function deletePrice(Court $court, CourtPriceSlot $price): void
    {
        abort_unless($price->court_id === $court->id, 404);
        $this->priceSlots->delete($price);
    }
}
