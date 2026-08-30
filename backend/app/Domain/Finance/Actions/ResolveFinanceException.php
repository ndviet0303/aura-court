<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Domain\Finance\DTOs\ResolveFinanceExceptionData;
use App\Models\FinanceException;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveFinanceException
{
    public function __construct(
        private readonly FinanceExceptionRepositoryInterface $exceptions,
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    public function execute(Organization $organization, ResolveFinanceExceptionData $data): FinanceException
    {
        $exception = $this->exceptions->findForOrganization($organization, $data->exceptionId);

        if (! $exception) {
            throw new NotFoundHttpException('Ngoại lệ tài chính không tồn tại trong tổ chức.');
        }

        return DB::transaction(function () use ($exception, $data) {
            $updated = $this->exceptions->update($exception, [
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $data->userId,
                'resolution' => $data->resolution,
            ]);

            // Append immutable audit log
            $hash = '0x' . strtoupper(substr(hash('sha256', "exception_{$exception->id}_user_{$data->userId}_" . now()->toIso8601String()), 0, 8));

            $this->auditLogs->create([
                'user_id' => $data->userId,
                'action' => 'finance_exception.resolve',
                'auditable_type' => FinanceException::class,
                'auditable_id' => $exception->id,
                'ip_address' => $data->ipAddress,
                'user_agent' => $data->userAgent,
                'metadata' => [
                    'action_type' => $data->actionType,
                    'reference' => $exception->reference,
                    'amount' => $exception->amount,
                    'resolution' => $data->resolution,
                    'audit_hash' => $hash,
                ],
            ]);

            return $updated;
        });
    }
}
