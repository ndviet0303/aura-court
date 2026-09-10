<?php

namespace App\Contracts\Repositories;

use App\Models\HostKyc;
use App\Models\PartnerConsent;
use App\Models\PartnerDocument;
use App\Models\PartnerReviewHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HostKycRepositoryInterface
{
    public function findById(int $id): ?HostKyc;

    public function findByIdWithLock(int $id): ?HostKyc;

    public function findByCode(string $code): ?HostKyc;

    public function findCurrentByUserId(int $userId): ?HostKyc;

    public function findLatestByUserId(int $userId): ?HostKyc;

    public function findCurrentByUserIdWithLock(int $userId): ?HostKyc;

    public function findByTaxCode(string $taxCode): ?HostKyc;

    public function paginate(
        ?string $status = null,
        ?string $search = null,
        int $perPage = 30
    ): LengthAwarePaginator;

    public function getStats(): array;

    public function create(array $attributes): HostKyc;

    public function update(HostKyc $kyc, array $attributes): HostKyc;

    public function attachDocument(HostKyc $kyc, array $attributes): PartnerDocument;

    public function findDocument(int $documentId): ?PartnerDocument;

    public function deleteDocument(PartnerDocument $document): bool;

    public function recordConsent(HostKyc $kyc, array $attributes): PartnerConsent;

    public function recordReview(HostKyc $kyc, array $attributes): PartnerReviewHistory;
}
