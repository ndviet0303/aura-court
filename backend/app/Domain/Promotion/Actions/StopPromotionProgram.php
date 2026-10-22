<?php

namespace App\Domain\Promotion\Actions;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Domain\Promotion\DTOs\PromotionProgramChange;
use Illuminate\Support\Facades\DB;

/**
 * Stops a program for good (design D14): from then on it applies to nothing not yet confirmed, while its terms,
 * codes and usage history stay. Needs no business entitlement. The program row is locked FOR UPDATE first, so a
 * checkout holding it finishes before (D17). Stopping a stopped program changes nothing. Expiring the bookings
 * pending payment that hold its voucher (D19) belongs to task 6.2 and keys on $termsChanged.
 */
class StopPromotionProgram
{
    public function __construct(private readonly PromotionProgramRepositoryInterface $programs) {}

    public function execute(int $programId): PromotionProgramChange
    {
        return DB::transaction(function () use ($programId): PromotionProgramChange {
            $program = $this->programs->findForUpdate($programId) ?? abort(404);
            if ($program->stopped_at !== null) {
                return new PromotionProgramChange($program, false);
            }

            return new PromotionProgramChange($this->programs->update($program, ['stopped_at' => now()]), true);
        });
    }
}
