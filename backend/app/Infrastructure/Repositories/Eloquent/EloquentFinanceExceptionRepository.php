<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Models\FinanceException;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentFinanceExceptionRepository implements FinanceExceptionRepositoryInterface
{
    public function findById(int $id): ?FinanceException
    {
        return FinanceException::find($id);
    }

    public function findForOrganization(Organization $organization, int $id): ?FinanceException
    {
        return FinanceException::where('organization_id', $organization->id)->find($id);
    }

    public function paginateForOrganization(
        Organization $organization,
        ?string $status = null,
        ?string $severity = null,
        ?string $search = null,
        int $perPage = 30
    ): LengthAwarePaginator {
        return FinanceException::query()
            ->where('organization_id', $organization->id)
            ->when($status, fn ($query, $st) => $query->where('status', $st))
            ->when($severity, fn ($query, $sev) => $query->where('severity', $sev))
            ->when($search, function ($query, $kw) {
                $query->where(function ($q) use ($kw) {
                    $q->where('reference', 'like', "%{$kw}%")
                        ->orWhere('description', 'like', "%{$kw}%")
                        ->orWhere('type', 'like', "%{$kw}%");
                });
            })
            ->with(['resolver:id,name,email'])
            ->latest('detected_at')
            ->paginate($perPage);
    }

    public function paginateOwnerPaymentExceptions(Organization $organization, int $perPage = 30): LengthAwarePaginator
    {
        return FinanceException::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'like', 'owner\_payment\_%')
            ->latest('detected_at')
            ->latest('id')
            ->paginate($perPage);
    }

    public function getStatsForOrganization(Organization $organization): array
    {
        $baseQuery = FinanceException::where('organization_id', $organization->id);

        $criticalCount = (clone $baseQuery)->where('severity', 'critical')->where('status', '!=', 'resolved')->count();
        $pendingCount = (clone $baseQuery)->where('status', 'open')->count();
        $resolvedToday = (clone $baseQuery)->where('status', 'resolved')->whereDate('resolved_at', today())->count();
        $totalRiskValue = (int) (clone $baseQuery)->where('status', '!=', 'resolved')->sum('amount');

        return [
            'critical_count' => $criticalCount,
            'pending_count' => $pendingCount,
            'resolved_today' => $resolvedToday,
            'total_risk_value' => $totalRiskValue,
        ];
    }

    public function create(array $attributes): FinanceException
    {
        return FinanceException::create($attributes);
    }

    public function update(FinanceException $exception, array $attributes): FinanceException
    {
        $exception->update($attributes);

        return $exception->fresh();
    }
}
