<?php

namespace App\Domain\POS\Actions;

use App\Models\PosShift;
use App\Services\POS\PosService;

class CloseShift
{
    public function __construct(
        private readonly PosService $pos,
    ) {}

    public function execute(PosShift $shift, int $actualCash, ?string $note = null): PosShift
    {
        return $this->pos->closeShift($shift, $actualCash, $note);
    }
}
