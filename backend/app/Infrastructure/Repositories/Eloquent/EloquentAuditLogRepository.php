<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    public function paginate(?string $action = null, int $perPage = 50): LengthAwarePaginator
    {
        return AuditLog::query()
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
