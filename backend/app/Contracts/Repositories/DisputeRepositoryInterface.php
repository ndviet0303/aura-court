<?php

namespace App\Contracts\Repositories;

use App\Models\DisputeCase;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DisputeRepositoryInterface
{
    public function findById(int $id, ?User $user = null): ?DisputeCase;

    public function findByCode(string $code, ?User $user = null): ?DisputeCase;

    public function paginate(
        ?string $status = null,
        ?string $search = null,
        ?string $severity = null,
        ?string $disputeType = null,
        int $perPage = 30,
        ?User $user = null,
        ?int $organizationId = null,
        ?int $venueId = null
    ): LengthAwarePaginator;

    public function getStats(?User $user = null, ?int $organizationId = null): array;

    public function userCanAccess(DisputeCase $dispute, User $user): bool;

    /** Whether the user answers the dispute: any active owner of its organization, otherwise its named respondent. */
    public function isRespondent(DisputeCase $dispute, User $user): bool;

    /** The user who answers disputes filed against an organization: its earliest active owner who is not platform staff. */
    public function findRespondentIdForOrganization(int $organizationId): ?int;

    public function create(array $attributes): DisputeCase;

    public function update(DisputeCase $dispute, array $attributes): DisputeCase;
}
