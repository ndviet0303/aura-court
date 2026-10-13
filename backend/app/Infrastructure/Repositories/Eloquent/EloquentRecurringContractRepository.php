<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Models\RecurringSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentRecurringContractRepository implements RecurringContractRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = RecurringSeries::query()
            ->withoutGlobalScope('tenant_isolation')
            ->with([
                'court.venue',
                'user',
                'invoices' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->orderBy('period_start'),
                'occurrences' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->orderBy('starts_at'),
            ]);

        if (! empty($filters['venue_id'])) {
            $query->whereHas('court', function ($q) use ($filters) {
                $q->where('venue_id', $filters['venue_id']);
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $query->whereIn('status', ['active', 'confirmed']);
            } elseif ($filters['status'] === 'overdue') {
                $query->where(function ($q) {
                    $q->where('status', 'overdue')
                        ->orWhereHas('invoices', fn ($iq) => $iq->withoutGlobalScope('tenant_isolation')->where('status', 'overdue'));
                });
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('court', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhereHas('venue', fn ($vq) => $vq->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        return $query->latest('id')->paginate($perPage);
    }

    public function findById(int $id): ?RecurringSeries
    {
        return RecurringSeries::query()
            ->withoutGlobalScope('tenant_isolation')
            ->with([
                'court.venue',
                'user',
                'invoices' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->orderBy('period_start'),
                'occurrences' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')->with('reservation')->orderBy('starts_at'),
            ])
            ->find($id);
    }

    public function legacyCancelRepairCandidates(): Collection
    {
        return RecurringSeries::query()
            ->withoutGlobalScope('tenant_isolation')
            ->whereNotNull('metadata->cancelled_at')
            ->whereHas('occurrences', fn ($q) => $q->withoutGlobalScope('tenant_isolation')
                ->where('status', 'released')
                ->where('starts_at', '>', now())
                ->whereHas('reservation', fn ($rq) => $rq->withoutGlobalScope('tenant_isolation')->whereIn('status', ['held', 'confirmed'])))
            ->with('organization')
            ->orderBy('id')
            ->get();
    }

    public function getStats(?int $venueId = null): array
    {
        $query = RecurringSeries::query()->withoutGlobalScope('tenant_isolation');

        if ($venueId) {
            $query->whereHas('court', fn ($q) => $q->where('venue_id', $venueId));
        }

        $allSeries = $query->with(['invoices' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')])->get();

        $activeSeries = $allSeries->filter(fn ($s) => in_array($s->status, ['active', 'confirmed'], true));
        $activeCount = $activeSeries->count();

        // Calculate Monthly Recurring Revenue (MRR) based on active contracts
        $mrrAmount = $activeSeries->sum(function ($s) {
            $monthlyInv = $s->invoices->first();
            if ($monthlyInv && $monthlyInv->amount > 0) {
                return $monthlyInv->amount;
            }
            $occurrenceCount = max(1, $s->occurrence_count ?: 4);
            $totalAmount = $s->total_amount ?: 0;

            return (int) round(($totalAmount / $occurrenceCount) * 4);
        });

        // Count overdue invoices
        $overdueCount = 0;
        foreach ($allSeries as $series) {
            $overdueCount += $series->invoices->filter(function ($inv) {
                return $inv->status === 'overdue' || ($inv->status === 'pending' && Carbon::parse($inv->due_at)->isPast());
            })->count();
        }

        return [
            'active_contracts' => $activeCount,
            'total_contracts' => $allSeries->count(),
            'mrr_amount' => $mrrAmount,
            'overdue_cycles' => $overdueCount,
        ];
    }
}
