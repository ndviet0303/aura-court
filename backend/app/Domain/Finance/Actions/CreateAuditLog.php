<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Domain\Finance\DTOs\CreateAuditLogData;
use App\Models\AuditLog;

class CreateAuditLog
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    public function execute(CreateAuditLogData $data): AuditLog
    {
        return $this->auditLogs->create([
            'user_id' => $data->userId,
            'action' => $data->action,
            'auditable_type' => $data->auditableType,
            'auditable_id' => $data->auditableId,
            'ip_address' => $data->ipAddress,
            'user_agent' => $data->userAgent,
            'metadata' => $data->metadata,
        ]);
    }
}
