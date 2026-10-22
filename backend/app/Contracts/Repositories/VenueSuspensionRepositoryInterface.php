<?php

namespace App\Contracts\Repositories;

use App\Models\SuspensionRemediation;
use App\Models\Venue;
use App\Models\VenueSuspension;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Suspension decisions (D13) and their immutable remediation submissions (D14). */
interface VenueSuspensionRepositoryInterface
{
    public function venue(int $venueId, bool $lock = false): Venue;

    /** The venue's active suspension, if any; FOR UPDATE when $lock. */
    public function active(int $venueId, bool $lock = false): ?VenueSuspension;

    public function create(array $attributes): VenueSuspension;

    public function find(int $id, bool $lock = false): VenueSuspension;

    /** Decisions of a venue, newest first, with acting admins and the latest submission's state. */
    public function forVenue(int $venueId): Collection;

    /** A decision with its venue, acting admins, conclusion and every submission oldest first. */
    public function detail(int $id): VenueSuspension;

    public function lift(VenueSuspension $suspension, array $attributes): void;

    public function latestRemediation(int $suspensionId): ?SuspensionRemediation;

    public function createRemediation(array $attributes): SuspensionRemediation;

    public function remediation(int $id, bool $lock = false): SuspensionRemediation;

    public function review(SuspensionRemediation $remediation, array $attributes): void;

    /** The latest submission of each decision, optionally by state, pending first then oldest first. */
    public function paginateRequests(?string $status, int $perPage): LengthAwarePaginator;
}
