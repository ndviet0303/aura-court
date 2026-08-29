<?php

namespace App\Domain\Finance\Actions;

use App\Models\Payout;
use App\Services\Finance\PayoutService;

class CreatePayout
{
    public function __construct(
        private readonly PayoutService $payouts,
    ) {}

    public function execute(int $organizationId, int $beneficiaryId, int $amount, ?string $note, int $actorId): Payout
    {
        return $this->payouts->requestPayout($organizationId, $beneficiaryId, $amount, $note, $actorId);
    }
}
