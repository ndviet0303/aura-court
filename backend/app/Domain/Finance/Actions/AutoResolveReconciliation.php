<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\ReconciliationRepositoryInterface;
use App\Domain\Finance\DTOs\AutoResolveReconciliationData;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Services\Finance\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AutoResolveReconciliation
{
    public function __construct(
        private readonly ReconciliationRepositoryInterface $reconciliationRepository,
        private readonly ReconciliationService $reconciliationService,
    ) {}

    public function execute(
        Organization $organization,
        AutoResolveReconciliationData $data,
        User $actor
    ): array {
        // Strictly enforce D47: Note-only input or absence of verified evidence / correction
        // MUST NOT resolve the exception, MUST NOT zero mismatch_amount, and MUST NOT mark the run as matched.
        $evidenceRef = trim((string) ($data->evidenceReference ?? ''));
        if ($evidenceRef === '') {
            throw ValidationException::withMessages([
                'evidence_reference' => ['Cannot auto-resolve reconciliation mismatch without verified correction evidence and an auditable rerun.'],
            ]);
        }

        // 1. Locate target run to rerun
        $run = null;
        if ($data->runId) {
            $run = $this->reconciliationRepository->findForOrganization($organization, $data->runId)
                ?? ReconciliationRun::where('organization_id', $organization->id)->find($data->runId);
        }

        if (! $run) {
            $openException = FinanceException::query()
                ->where('organization_id', $organization->id)
                ->where('type', 'reconciliation_mismatch')
                ->where('status', 'open')
                ->first();

            $runId = $openException?->links['reconciliation_run_id'] ?? null;
            if ($runId) {
                $run = ReconciliationRun::where('organization_id', $organization->id)->find($runId);
            }
        }

        if (! $run) {
            $run = ReconciliationRun::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('run_date')
                ->first();
        }

        $runDate = $run?->run_date ? Carbon::parse($run->run_date) : Carbon::today();

        // 2. Trigger auditable rerun
        $rerun = $this->reconciliationService->run($organization, $runDate);

        // 3. If rerun still has mismatch, exceptions and run remain in mismatch
        if ($rerun->mismatch_amount > 0 || $rerun->status === 'mismatch') {
            return [
                'success' => false,
                'resolved_count' => 0,
                'message' => "Auditable rerun #{$rerun->id} still detected mismatch ({$rerun->mismatch_amount} VND). Mismatch remains open.",
                'remaining_mismatch' => $rerun->mismatch_amount,
                'rerun_id' => $rerun->id,
            ];
        }

        // 4. Only if rerun confirms zero mismatch, resolve the exception(s)
        $query = FinanceException::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'open');

        if ($data->runId || $run) {
            $targetRunId = $data->runId ?: $run?->id;
            $query->where(function ($q) use ($targetRunId, $runDate) {
                $q->whereJsonContains('links->reconciliation_run_id', $targetRunId)
                    ->orWhere('reference', "reconciliation:{$runDate->toDateString()}");
            });
        }

        $exceptions = $query->get();
        $resolvedCount = 0;

        $resolution = sprintf(
            'Resolved with verified evidence [%s] (correction: %s) and confirmed by auditable rerun #%d. %s',
            $evidenceRef,
            $data->correctionType ?: 'manual_correction',
            $rerun->id,
            $data->notes ? "Notes: {$data->notes}" : ''
        );

        foreach ($exceptions as $exception) {
            $exception->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'resolution' => trim($resolution),
                'metadata' => array_merge($exception->metadata ?? [], [
                    'evidence_reference' => $evidenceRef,
                    'correction_type' => $data->correctionType,
                    'correction_details' => $data->correctionDetails,
                    'rerun_run_id' => $rerun->id,
                    'cleared_at' => now()->toIso8601String(),
                ]),
            ]);
            $resolvedCount++;
        }

        return [
            'success' => true,
            'resolved_count' => $resolvedCount,
            'message' => "Đã đối soát lại thành công và xử lý {$resolvedCount} cảnh báo sai lệch với bằng chứng xác minh.",
            'rerun_id' => $rerun->id,
        ];
    }
}
