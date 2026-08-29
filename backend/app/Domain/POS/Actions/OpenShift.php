<?php

namespace App\Domain\POS\Actions;

use App\Models\PosShift;
use App\Services\POS\PosService;

class OpenShift
{
    public function __construct(
        private readonly PosService $pos,
    ) {}

    public function execute(int $organizationId, int $userId, int $startingCash, ?string $note = null): PosShift
    {
        return $this->pos->openShift($organizationId, $userId, $startingCash, $note);
    }
}
