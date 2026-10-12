<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Models\HostKyc;
use App\Models\PartnerConsent;
use App\Models\PartnerDocument;
use App\Models\PartnerReviewHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentHostKycRepository implements HostKycRepositoryInterface
{
    public function findById(int $id): ?HostKyc
    {
        return HostKyc::query()
            ->with(['organization', 'venue', 'user', 'reviewer', 'documents', 'consents', 'reviewHistories'])
            ->find($id);
    }

    public function findByIdWithLock(int $id): ?HostKyc
    {
        return HostKyc::query()
            ->lockForUpdate()
            ->with(['organization', 'venue', 'user', 'reviewer', 'documents', 'consents', 'reviewHistories'])
            ->find($id);
    }

    public function findByCode(string $code): ?HostKyc
    {
        return HostKyc::query()
            ->with(['organization', 'venue', 'user', 'reviewer', 'documents', 'consents', 'reviewHistories'])
            ->where('kyc_code', $code)
            ->first();
    }

    public function findLatestByUserId(int $userId): ?HostKyc
    {
        return HostKyc::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->first();
    }

    public function findCurrentByUserId(int $userId): ?HostKyc
    {
        return HostKyc::query()
            ->where('current_user_id', $userId)
            ->with(['organization', 'venue', 'user', 'reviewer', 'documents', 'consents', 'reviewHistories'])
            ->first();
    }

    public function findCurrentByUserIdWithLock(int $userId): ?HostKyc
    {
        return HostKyc::query()
            ->where('current_user_id', $userId)
            ->lockForUpdate()
            ->with(['organization', 'venue', 'user', 'reviewer', 'documents', 'consents', 'reviewHistories'])
            ->first();
    }

    public function findByTaxCode(string $taxCode): ?HostKyc
    {
        return HostKyc::query()
            ->where('tax_code', $taxCode)
            ->whereIn('status', ['draft', 'pending', 'revision_required', 'approved'])
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
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('kyc_code', 'like', $term)
                    ->orWhere('business_name', 'like', $term)
                    ->orWhere('legal_name', 'like', $term)
                    ->orWhere('tax_code', 'like', $term)
                    ->orWhere('representative_name', 'like', $term)
                    ->orWhere('id_card_number', 'like', $term);
            });
        }

        return $query->orderByRaw("CASE WHEN status = 'pending' THEN 1 WHEN status = 'flagged' THEN 2 WHEN status = 'revision_required' THEN 3 WHEN status = 'needs_supplement' THEN 4 ELSE 5 END")
            ->orderByDesc('submitted_at')
            ->paginate($perPage);
    }

    public function getStats(): array
    {
        $pending = HostKyc::query()->where('status', 'pending')->count();
        $flagged = HostKyc::query()->where('status', 'flagged')->count();
        $approved = HostKyc::query()->where('status', 'approved')->count();
        $revisionRequired = HostKyc::query()->whereIn('status', ['revision_required', 'needs_supplement'])->count();
        $avgConfidence = HostKyc::query()->avg('ai_ocr_score') ?: 0.0;
        // A row resubmitted after its last review is back in the queue (reviewed_at < submitted_at), so it is skipped.
        // ponytail: averages in PHP to stay DB-agnostic; move to a SQL aggregate if host_kycs reaches tens of thousands of rows.
        $avgProcessingMinutes = HostKyc::query()
            ->whereColumn('reviewed_at', '>=', 'submitted_at')
            ->get(['submitted_at', 'reviewed_at'])
            ->avg(fn (HostKyc $kyc) => $kyc->submitted_at->diffInMinutes($kyc->reviewed_at));

        return [
            'pending_count' => $pending,
            'ai_confidence_rate' => round($avgConfidence, 1),
            'avg_processing_time_minutes' => $avgProcessingMinutes === null ? null : round($avgProcessingMinutes, 1),
            'flagged_count' => $flagged,
            'approved_count' => $approved,
            'needs_supplement_count' => $revisionRequired,
        ];
    }

    public function create(array $attributes): HostKyc
    {
        return HostKyc::create($attributes);
    }

    public function update(HostKyc $kyc, array $attributes): HostKyc
    {
        $kyc->update($attributes);

        return $kyc->fresh(['organization', 'venue', 'user', 'reviewer', 'documents', 'consents', 'reviewHistories']);
    }

    public function attachDocument(HostKyc $kyc, array $attributes): PartnerDocument
    {
        return $kyc->documents()->create($attributes);
    }

    public function findDocument(int $documentId): ?PartnerDocument
    {
        return PartnerDocument::query()->find($documentId);
    }

    public function deleteDocument(PartnerDocument $document): bool
    {
        return (bool) $document->delete();
    }

    public function recordConsent(HostKyc $kyc, array $attributes): PartnerConsent
    {
        return $kyc->consents()->create($attributes);
    }

    public function recordReview(HostKyc $kyc, array $attributes): PartnerReviewHistory
    {
        return $kyc->reviewHistories()->create($attributes);
    }
}
