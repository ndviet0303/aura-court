<?php

namespace App\Contracts\Repositories;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuditLogRepositoryInterface
{
    public function paginate(?string $action = null, int $perPage = 50): LengthAwarePaginator;

    public function create(array $attributes): AuditLog;
}
