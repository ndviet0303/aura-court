<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\ReviewRepositoryInterface;
use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EloquentReviewRepository implements ReviewRepositoryInterface
{
    private const TENANT_SCOPE = 'tenant_isolation';

    public function findOwnedReservation(int $userId, string $code): ?Reservation
    {
        return Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->where('user_id', $userId)
            ->where('code', $code)
            ->with([
                'court:id,code,venue_id',
                'attendance' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE),
                'review' => fn ($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
            ])
            ->first();
    }

    public function paginateForVenue(int $venueId, int $perPage): LengthAwarePaginator
    {
        return Review::query()
            ->where('venue_id', $venueId)
            ->where('is_published', true)
            ->with($this->shown())
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function summary(int $venueId): array
    {
        $row = Review::query()->where('venue_id', $venueId)->where('is_published', true)
            ->selectRaw('count(*) as total, avg(rating) as average')
            ->first();
        $average = $row?->getAttribute('average');

        return ['count' => (int) $row?->getAttribute('total'), 'average' => $average === null ? null : round((float) $average, 1)];
    }

    public function create(array $attributes): Review
    {
        return Review::create($attributes)->load($this->shown());
    }

    public function update(Review $review, array $attributes): Review
    {
        $review->update($attributes);

        return $review->load($this->shown());
    }

    public function delete(Review $review): void
    {
        $review->delete();
    }

    /** @return array<int|string, mixed> what a review shows: author, court and the reservation code */
    private function shown(): array
    {
        return ['user:id,name,avatar_url', 'court:id,code,venue_id', 'reservation' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE)->select(['id', 'code'])];
    }
}
