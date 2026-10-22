<?php

namespace App\Contracts\Repositories;

use App\Models\VoucherUse;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/** Voucher use rows (design D11): a program's held and used counts are counted from them, never kept on the program. */
interface VoucherUseRepositoryInterface
{
    /** The booking's use in any status, with its code; at most one, since reservation_id is unique. */
    public function forReservation(int $reservationId): ?VoucherUse;

    /**
     * The voucher discount of each of these bookings whose use is held or used, by reservation id (D23).
     *
     * @param  list<int>  $reservationIds
     * @return array<int, int>
     */
    public function activeDiscounts(array $reservationIds): array;

    /** Held and used uses of the program, leaving out the use of $exceptReservationId (D17). */
    public function countActive(int $programId, ?int $exceptReservationId): int;

    /**
     * The program's used uses, latest use first, each with its code and its booking's customer, court and slots (FR-68).
     * A booking cancelled after confirmation keeps its use used, so it stays listed.
     *
     * @return LengthAwarePaginator<int, VoucherUse>
     */
    public function paginateUsed(int $programId, int $perPage): LengthAwarePaginator;

    /**
     * The program's held uses, oldest first, loaded like paginateUsed; few, since a hold lasts until its payment deadline.
     *
     * @return Collection<int, VoucherUse>
     */
    public function held(int $programId): Collection;

    /** The use holding or having used the code; at most one by the partial unique index. */
    public function activeForCode(int $codeId): ?VoucherUse;

    /**
     * Inserts under a savepoint; null when a unique index refused the row, with the caller's transaction intact.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ?VoucherUse;

    /** held → used at $usedAt; false when the use is not held. */
    public function markUsed(VoucherUse $use, CarbonInterface $usedAt): bool;

    /** The booking's held use → released; false when it holds none. Touches only voucher_uses (D11). */
    public function releaseHeld(int $reservationId, CarbonInterface $releasedAt): bool;
}
