<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Domain\Finance\DTOs\ResolveFinanceExceptionData;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\ReconciliationRun;
use App\Services\Finance\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveFinanceException
{
    public function __construct(
        private readonly FinanceExceptionRepositoryInterface $exceptions,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly ReconciliationService $reconciliationService,
    ) {}

    public function execute(Organization $organization, ResolveFinanceExceptionData $data): FinanceException
    {
        $exception = $this->exceptions->findForOrganization($organization, $data->exceptionId);

        if (! $exception) {
            throw new NotFoundHttpException('Ngoại lệ tài chính không tồn tại trong tổ chức.');
        }

        $rerun = null;
        if ($exception->type === 'reconciliation_mismatch') {
            $evidence = trim((string) ($data->evidenceReference ?? ''));
            if ($evidence === '') {
                throw ValidationException::withMessages([
                    'evidence_reference' => ['Cannot resolve reconciliation mismatch without verified correction evidence and an auditable rerun.'],
                ]);
            }

            $run = null;
            $runId = $exception->links['reconciliation_run_id'] ?? null;
            if ($runId) {
                $run = ReconciliationRun::where('organization_id', $organization->id)->find($runId);
            }
            if (! $run && str_starts_with((string) $exception->reference, 'reconciliation:')) {
                $datePart = explode(':', (string) $exception->reference, 2)[1] ?? null;
                if ($datePart) {
                    $run = ReconciliationRun::where('organization_id', $organization->id)->whereDate('run_date', $datePart)->first();
                }
            }
            if (! $run) {
                $run = ReconciliationRun::query()
                    ->where('organization_id', $organization->id)
                    ->orderByDesc('run_date')
                    ->first();
            }

            $runDate = $run?->run_date ? Carbon::parse($run->run_date) : Carbon::today();

            $rerun = $this->reconciliationService->run($organization, $runDate);

            if ($rerun->mismatch_amount > 0 || $rerun->status === 'mismatch') {
                throw ValidationException::withMessages([
                    'rerun' => ["Auditable rerun #{$rerun->id} still detected mismatch ({$rerun->mismatch_amount} VND). Mismatch remains open."],
                ]);
            }

            if ($rerun->status !== 'matched' || $rerun->mismatch_amount !== 0) {
                throw ValidationException::withMessages([
                    'rerun' => ["Auditable rerun #{$rerun->id} is not matched. Mismatch remains open."],
                ]);
            }

            $rerun->update([
                'status' => 'matched',
                'mismatch_amount' => 0,
            ]);

            if ($run && $run->id !== $rerun->id) {
                $run->update([
                    'status' => 'matched',
                    'mismatch_amount' => 0,
                ]);
            }
        }

        return DB::transaction(function () use ($exception, $data, $rerun) {
            $attributes = [
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $data->userId,
                'resolution' => $data->resolution,
            ];

            if ($rerun) {
                $attributes['metadata'] = array_merge($exception->metadata ?? [], [
                    'evidence_reference' => $data->evidenceReference,
                    'rerun_run_id' => $rerun->id,
                    'cleared_at' => now()->toIso8601String(),
                ]);
            }

            $updated = $this->exceptions->update($exception, $attributes);

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
                    'evidence_reference' => $data->evidenceReference,
                    'audit_hash' => $hash,
                ],
            ]);

            return $updated;
        });
    }
}
