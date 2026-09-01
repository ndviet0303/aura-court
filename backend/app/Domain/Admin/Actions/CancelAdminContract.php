<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Models\RecurringSeries;

class CancelAdminContract
{
    public function __construct(
        private readonly RecurringContractRepositoryInterface $contracts,
    ) {}

    public function execute(int $id, ?string $reason = null): RecurringSeries
    {
        return $this->contracts->cancelContract($id, $reason);
    }
}
