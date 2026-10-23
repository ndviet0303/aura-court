<?php

namespace App\Contracts\Repositories;

use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** FR-115..FR-117, D15: reviews of a venue, one per reservation, deleted ones kept as trashed rows. */
interface ReviewRepositoryInterface
{
    /** The caller's reservation by code across organizations, with its court, attendance and review (even deleted). */
    public function findOwnedReservation(int $userId, string $code): ?Reservation;

    /** @return LengthAwarePaginator<int, Review> the venue's published reviews, newest first */
    public function paginateForVenue(int $venueId, int $perPage): LengthAwarePaginator;

    /** @return array{count: int, average: float|null} over the venue's published reviews */
    public function summary(int $venueId): array;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Review;

    /** @param  array<string, mixed>  $attributes */
    public function update(Review $review, array $attributes): Review;

    public function delete(Review $review): void;
}
