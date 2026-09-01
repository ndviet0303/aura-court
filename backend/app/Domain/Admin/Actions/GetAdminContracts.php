<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;

class GetAdminContracts
{
    public function __construct(
        private readonly RecurringContractRepositoryInterface $contracts,
    ) {}

    public function execute(array $filters = [], int $perPage = 20): array
    {
        $paginator = $this->contracts->getAllPaginated($filters, $perPage);
        $venueId = ! empty($filters['venue_id']) ? (int) $filters['venue_id'] : null;
        $stats = $this->contracts->getStats($venueId);

        return [
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'stats' => $stats,
        ];
    }
}
