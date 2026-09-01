<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Models\RecurringSeries;

class CreateAdminContract
{
    public function __construct(
        private readonly RecurringContractRepositoryInterface $contracts,
    ) {}

    public function execute(array $data): RecurringSeries
    {
        return $this->contracts->createContract($data);
    }
}
