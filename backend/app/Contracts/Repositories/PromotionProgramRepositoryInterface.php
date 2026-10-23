<?php

namespace App\Contracts\Repositories;

use App\Models\Court;
use App\Models\PromotionProgram;
use App\Models\VoucherCode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface PromotionProgramRepositoryInterface
{
    /**
     * The venue's programs, newest first, each with its courts by id and its held_uses_count and used_uses_count.
     *
     * @return Collection<int, PromotionProgram>
     */
    public function forVenue(int $venueId): Collection;

    /** Loads the program's courts by id and its held_uses_count and used_uses_count. */
    public function loadDetail(PromotionProgram $program): PromotionProgram;

    /** Loads the program's courts by id and its codes by id, each with the use holding or having used it and that use's booking. */
    public function loadCodes(PromotionProgram $program): PromotionProgram;

    /**
     * The courts that exist among $courtIds, locked FOR SHARE in id order (design D11).
     *
     * @param  list<int>  $courtIds
     * @return Collection<int, Court>
     */
    public function lockCourts(array $courtIds): Collection;

    public function find(int $id): ?PromotionProgram;

    public function findForUpdate(int $id): ?PromotionProgram;

    /** FOR SHARE: blocks the owner's update or stop until the caller commits (design D11). */
    public function findForShare(int $id): ?PromotionProgram;

    /** The code exactly as stored (upper case); codes are unique across the platform. */
    public function findCode(string $code): ?VoucherCode;

    /**
     * FR-111, BR-143: free codes of the venue's private programs running at $at, with their program and its courts.
     *
     * @return Collection<int, VoucherCode>
     */
    public function giftableCodes(int $venueId, CarbonInterface $at): Collection;

    public function coversCourt(PromotionProgram $program, int $courtId): bool;

    /**
     * Public programs covering the court that are not stopped and run at $at; usage is not checked here.
     *
     * @return Collection<int, PromotionProgram>
     */
    public function runningPublicForCourt(int $courtId, CarbonInterface $at): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $courtIds
     */
    public function create(array $attributes, array $courtIds): PromotionProgram;

    /** Issues $count new codes, unique across the platform, retrying on a collision with a code issued before (D8). */
    public function createCodes(PromotionProgram $program, int $count): void;

    /** @param  array<string, mixed>  $attributes */
    public function update(PromotionProgram $program, array $attributes): PromotionProgram;

    /**
     * Writes the court set as a diff, never delete and insert again (D11); true when the set changed.
     *
     * @param  list<int>  $courtIds
     */
    public function syncCourts(PromotionProgram $program, array $courtIds): bool;
}
