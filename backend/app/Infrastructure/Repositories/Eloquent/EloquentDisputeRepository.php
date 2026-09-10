<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Models\DisputeCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentDisputeRepository implements DisputeRepositoryInterface
{
    public function findById(int $id): ?DisputeCase
    {
        return DisputeCase::query()
            ->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator'])
            ->find($id);
    }

    public function findByCode(string $code): ?DisputeCase
    {
        return DisputeCase::query()
            ->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator'])
            ->where('dispute_code', $code)
            ->first();
    }

    public function paginate(
        ?string $status = null,
        ?string $search = null,
        ?string $severity = null,
        ?string $disputeType = null,
        int $perPage = 30
    ): LengthAwarePaginator {
        $query = DisputeCase::query()->with(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($severity && $severity !== 'all') {
            $query->where('severity', $severity);
        }

        if ($disputeType && $disputeType !== 'all') {
            $query->where('dispute_type', $disputeType);
        }

        if ($search) {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('dispute_code', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('complainant_name', 'like', $term)
                    ->orWhere('respondent_name', 'like', $term)
                    ->orWhere('facility_name', 'like', $term);
            });
        }

        return $query->orderByRaw("CASE WHEN severity = 'critical' THEN 1 WHEN severity = 'high' THEN 2 WHEN status = 'pending' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getStats(): array
    {
        $pending = DisputeCase::query()->where('status', 'pending')->count();
        $investigating = DisputeCase::query()->where('status', 'investigating')->count();
        $awaitingReply = DisputeCase::query()->where('status', 'awaiting_response')->count();
        $resolved = DisputeCase::query()->where('status', 'resolved')->count();
        $critical = DisputeCase::query()->where('severity', 'critical')->whereIn('status', ['pending', 'investigating'])->count();

        return [
            'pending_count' => $pending,
            'investigating_count' => $investigating,
            'awaiting_reply_count' => $awaitingReply,
            'resolved_count' => $resolved,
            'critical_sla_count' => $critical,
            'avg_resolution_time_minutes' => 18.5,
            'consensus_rate' => 98.2,
        ];
    }

    public function create(array $attributes): DisputeCase
    {
        return DisputeCase::create($attributes);
    }

    public function update(DisputeCase $dispute, array $attributes): DisputeCase
    {
        $dispute->update($attributes);

        return $dispute->fresh(['organization', 'venue', 'booking', 'complainant', 'respondent', 'arbitrator']);
    }
}
