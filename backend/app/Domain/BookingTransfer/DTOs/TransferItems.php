<?php

namespace App\Domain\BookingTransfer\DTOs;

/** The validated items list as integers. */
final readonly class TransferItems
{
    /**
     * @param  array<int, array{reservation_id: mixed, price: mixed}>  $items
     * @return list<array{reservation_id: int, price: int}>
     */
    public static function from(array $items): array
    {
        return array_values(array_map(fn (array $item): array => ['reservation_id' => (int) $item['reservation_id'], 'price' => (int) $item['price']], $items));
    }
}
