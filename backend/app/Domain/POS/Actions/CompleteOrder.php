<?php

namespace App\Domain\POS\Actions;

use App\Models\PosOrder;
use App\Services\POS\PosService;

class CompleteOrder
{
    public function __construct(
        private readonly PosService $pos,
    ) {}

    public function execute(int $organizationId, int $userId, array $data): PosOrder
    {
        return $this->pos->createOrder($organizationId, $userId, $data);
    }
}
