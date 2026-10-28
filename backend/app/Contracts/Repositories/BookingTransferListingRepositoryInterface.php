<?php

namespace App\Contracts\Repositories;

use App\Models\BookingTransferListing;
use App\Models\BookingTransferListingItem;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Module 08.2 FR-139..FR-142: listings on the shared transfer market. */
interface BookingTransferListingRepositoryInterface
{
    /** @return Collection<int, Reservation> the user's confirmed bookings not started yet, soonest first, with slots and court venue */
    public function candidates(int $userId): Collection;

    public function find(int $id): ?BookingTransferListing;

    /** @param  list<array{reservation_id: int, organization_id: int, price: int}>  $items */
    public function create(int $sellerId, ?string $note, array $items): BookingTransferListing;

    /**
     * FR-141: listings with at least one available booking, newest first; $search matches venue name or address,
     * court name or the note.
     *
     * @return LengthAwarePaginator<int, BookingTransferListing>
     */
    public function search(?string $search, int $perPage): LengthAwarePaginator;

    /** The listing with seller and items (reservation, slots, court venue, held flag). */
    public function withDetails(BookingTransferListing $listing): BookingTransferListing;

    /**
     * Listed items of the listing FOR UPDATE, with the held flag; call inside a transaction.
     *
     * @return Collection<int, BookingTransferListingItem>
     */
    public function lockListedItems(BookingTransferListing $listing): Collection;

    /** @param  list<int>  $itemIds */
    public function withdraw(array $itemIds): void;

    /** BR-190: listed items of these bookings, in any listing, become transferred. @param  list<int>  $reservationIds */
    public function markTransferred(array $reservationIds): void;

    /**
     * Who asked about the listing through chat (D11), latest first.
     *
     * @return list<array{user: array{id: int, name: string, avatar_url: ?string}, conversation_id: int, reservation_ids: list<int>, contacted_at: ?string}>
     */
    public function contacts(BookingTransferListing $listing): array;
}
