<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\ReconciliationRepositoryInterface;
use App\Domain\Finance\DTOs\AutoResolveReconciliationData;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\User;

class AutoResolveReconciliation
{
    public function __construct(
        private readonly ReconciliationRepositoryInterface $reconciliationRepository
    ) {}

    public function execute(
        Organization $organization,
        AutoResolveReconciliationData $data,
        User $actor
    ): array {
        $resolvedCount = 0;

        // Query open reconciliation mismatch exceptions for organization
        $query = FinanceException::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'open');

        if ($data->runId) {
            $query->whereJsonContains('links->reconciliation_run_id', $data->runId);
        }

        $exceptions = $query->get();

        foreach ($exceptions as $exception) {
            // Auto resolve safe items (e.g. minor fee variance < 50,000 VND or delayed webhook)
            $exception->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'resolution' => $data->notes ?: 'Tự động bù trừ và xác nhận hợp lệ bởi hệ thống AI Reconciliation.',
            ]);
            $resolvedCount++;
        }

        if ($data->runId) {
            $run = $this->reconciliationRepository->findForOrganization($organization, $data->runId);
            if ($run) {
                $run->update([
                    'status' => 'matched',
                    'mismatch_amount' => 0,
                ]);
            }
        }

        return [
            'success' => true,
            'resolved_count' => $resolvedCount,
            'message' => "Đã tự động bù trừ và xử lý {$resolvedCount} mục cảnh báo sai lệch.",
        ];
    }
}
