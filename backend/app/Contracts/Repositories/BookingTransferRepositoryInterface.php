<?php

namespace App\Contracts\Repositories;

use App\Models\BookingTransfer;
use App\Models\Reservation;
use Illuminate\Support\Collection;

/** Module 08.2 D3–D5: transfers between two customers and the bookings they move. */
interface BookingTransferRepositoryInterface
{
    /**
     * Bookings by id, across organizations, with slots and court venue; FOR UPDATE by id order when $lock.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Reservation> keyed by id
     */
    public function reservations(array $ids, bool $lock = false): Collection;

    /**
     * What D8 reads besides the booking row: any attendance (used), a completed transfer, an open affected-booking
     * case, a pending transfer other than $exceptTransferId (held), and a listed listing item.
     *
     * @return array{used: bool, transferred: bool, incident: bool, held: bool, listed: bool}
     */
    public function facts(Reservation $reservation, ?int $exceptTransferId = null): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{reservation_id: int, organization_id: int, price: int}>  $items
     */
    public function create(array $attributes, array $items): BookingTransfer;

    /** The transfer re-read FOR UPDATE; call inside a transaction. */
    public function lock(BookingTransfer $transfer): BookingTransfer;

    /** Sets the status of the transfer and of each of its items. @param  array<string, mixed>  $attributes */
    public function close(BookingTransfer $transfer, string $status, array $attributes): BookingTransfer;

    /**
     * BR-188: the booking and its active entitlements move to the buyer, with the buyer's contact as holder and a
     * new check-in code, so the old QR stops working.
     *
     * @param  array{name: string, phone: string, email: string}  $holder
     */
    public function moveHolding(Reservation $reservation, int $buyerId, array $holder): void;

    /** @return Collection<int, BookingTransfer> where the user is seller or buyer, newest first, with what the resource shows */
    public function forUser(int $userId): Collection;

    public function withDetails(BookingTransfer $transfer): BookingTransfer;

    /** @return list<int> active owners of the organization */
    public function ownerIds(int $organizationId): array;
}
