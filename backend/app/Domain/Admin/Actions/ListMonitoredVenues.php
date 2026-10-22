<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\VenueMonitoringRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** D13 monitoring list: venues of every organization, read-only; each request leaves an audit row with its query. */
class ListMonitoredVenues
{
    public function __construct(
        private readonly VenueMonitoringRepositoryInterface $venues,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function execute(User $admin, array $filters): LengthAwarePaginator
    {
        $page = $this->venues->paginate($filters, (int) ($filters['per_page'] ?? 20));
        $this->notifications->recordAudit($admin->id, 'venue.monitoring.listed', $admin, ['query' => $filters, 'page' => $page->currentPage()]);

        return $page;
    }
}
