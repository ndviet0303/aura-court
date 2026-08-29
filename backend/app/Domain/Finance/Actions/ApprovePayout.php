<?php

namespace App\Domain\Finance\Actions;

use App\Models\Payout;
use App\Services\Finance\PayoutService;

class ApprovePayout
{
    public function __construct(
        private readonly PayoutService $payouts,
    ) {}

    public function execute(Payout $payout, int $actorId): Payout
    {
        return $this->payouts->approvePayout($payout, $actorId);
    }
}
