<?php

namespace App\Domain\Finance\DTOs;

readonly class CreateAuditLogData
{
    public function __construct(
        public ?int $userId,
        public string $action,
        public ?string $auditableType = null,
        public ?int $auditableId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?array $metadata = null,
    ) {}
}
