<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog;
use App\Models\FinanceException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    public function paginateFinanceExceptionLogs(int $organizationId, ?string $action = null, int $perPage = 50): LengthAwarePaginator
    {
        // audit_logs has no tenant column; scope through the organization's finance exceptions.
        return AuditLog::query()
            ->where('auditable_type', FinanceException::class)
            ->whereIn('auditable_id', FinanceException::query()->where('organization_id', $organizationId)->select('id'))
            ->with(['user:id,name,email'])
            ->when($action, fn ($query, $act) => $query->where('action', $act))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $attributes): AuditLog
    {
        return AuditLog::create($attributes);
    }
}
