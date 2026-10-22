<?php

namespace App\Contracts\Repositories;

use App\Models\VoucherUse;
use Carbon\CarbonInterface;

/** Voucher use rows (design D11): a program's held and used counts are counted from them, never kept on the program. */
interface VoucherUseRepositoryInterface
{
    /** The booking's use in any status, with its code; at most one, since reservation_id is unique. */
    public function forReservation(int $reservationId): ?VoucherUse;

    /** Held and used uses of the program, leaving out the use of $exceptReservationId (D17). */
    public function countActive(int $programId, ?int $exceptReservationId): int;

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
