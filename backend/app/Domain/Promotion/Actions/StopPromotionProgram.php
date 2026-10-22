<?php

namespace App\Domain\Promotion\Actions;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Events\PromotionProgramChanged;
use App\Models\PromotionProgram;
use Illuminate\Support\Facades\DB;

/**
 * Stops a program for good (design D14): from then on it applies to nothing not yet confirmed, while its terms,
 * codes and usage history stay. Needs no business entitlement. The program row is locked FOR UPDATE first, so a
 * checkout holding it finishes before (D17). Stopping a stopped program changes nothing. After the commit the
 * bookings pending payment that still hold its voucher are expired (D19); a repeated stop checks them again.
 */
class StopPromotionProgram
{
    public function __construct(private readonly PromotionProgramRepositoryInterface $programs) {}

    public function execute(int $programId): PromotionProgram
    {
        $program = DB::transaction(function () use ($programId): PromotionProgram {
            $program = $this->programs->findForUpdate($programId) ?? abort(404);

            return $program->stopped_at !== null ? $program : $this->programs->update($program, ['stopped_at' => now()]);
        });
        PromotionProgramChanged::dispatch((int) $program->id);

        return $program;
    }
}
