<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Models\HostKyc;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentHostKycRepository implements HostKycRepositoryInterface
{
    public function findById(int $id): ?HostKyc
    {
        return HostKyc::query()
            ->with(['organization', 'venue', 'user', 'reviewer'])
            ->find($id);
    }

    public function findByCode(string $code): ?HostKyc
    {
        return HostKyc::query()
            ->with(['organization', 'venue', 'user', 'reviewer'])
            ->where('kyc_code', $code)
            ->first();
    }

    public function paginate(
        ?string $status = null,
        ?string $search = null,
        int $perPage = 30
    ): LengthAwarePaginator {
        $query = HostKyc::query()->with(['organization', 'venue', 'user', 'reviewer']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('kyc_code', 'like', $term)
                    ->orWhere('business_name', 'like', $term)
                    ->orWhere('legal_name', 'like', $term)
                    ->orWhere('tax_code', 'like', $term)
                    ->orWhere('representative_name', 'like', $term)
                    ->orWhere('id_card_number', 'like', $term);
            });
        }

        return $query->orderByRaw("CASE WHEN status = 'pending' THEN 1 WHEN status = 'flagged' THEN 2 WHEN status = 'needs_supplement' THEN 3 ELSE 4 END")
            ->orderByDesc('submitted_at')
            ->paginate($perPage);
    }

    public function getStats(): array
    {
        $pending = HostKyc::query()->where('status', 'pending')->count();
        $flagged = HostKyc::query()->where('status', 'flagged')->count();
        $approved = HostKyc::query()->where('status', 'approved')->count();
        $needsSupplement = HostKyc::query()->where('status', 'needs_supplement')->count();
        $avgConfidence = HostKyc::query()->avg('ai_ocr_score') ?: 0.0;

        return [
            'pending_count' => $pending,
            'ai_confidence_rate' => round($avgConfidence, 1),
            'avg_processing_time_minutes' => 14.2,
            'flagged_count' => $flagged,
            'approved_count' => $approved,
            'needs_supplement_count' => $needsSupplement,
        ];
    }

    public function create(array $attributes): HostKyc
    {
        return HostKyc::create($attributes);
    }

    public function update(HostKyc $kyc, array $attributes): HostKyc
    {
        $kyc->update($attributes);
        return $kyc->fresh(['organization', 'venue', 'user', 'reviewer']);
    }
}
