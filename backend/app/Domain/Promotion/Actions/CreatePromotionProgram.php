<?php

namespace App\Domain\Promotion\Actions;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Domain\Promotion\DTOs\CreatePromotionProgramData;
use App\Models\Court;
use App\Models\PromotionProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating a program issues it (design D8, D14); a private program gets its codes in the same transaction. The
 * submitted courts are locked FOR SHARE in id order before the program is inserted (D11): the insert locks the
 * venue through its key and the court rows lock the courts, the reverse of checkout's court then venue otherwise.
 * Three attempts, so a deadlock with the venue-then-court paths still on main (ManageCourtBlocks::createBlock,
 * SaveVenueScheduleException, SaveVenueOperatingSchedule) is retried.
 */
class CreatePromotionProgram
{
    public function __construct(
        private readonly PromotionProgramRepositoryInterface $programs,
    ) {}

    public function execute(CreatePromotionProgramData $data): PromotionProgram
    {
        return DB::transaction(function () use ($data): PromotionProgram {
            $courts = $this->programs->lockCourts($data->courtIds);
            if ($courts->count() !== count($data->courtIds) || $courts->contains(fn (Court $court): bool => (int) $court->venue_id !== $data->venueId)) {
                throw ValidationException::withMessages(['court_ids' => 'Sân được chọn không thuộc cơ sở của chương trình.']);
            }

            $program = $this->programs->create([
                'venue_id' => $data->venueId,
                'type' => $data->type,
                'discount_kind' => $data->discountKind,
                'code_count' => $data->codeCount,
                ...$data->terms,
            ], $data->courtIds);
            if ($data->codeCount !== null) {
                $this->programs->createCodes($program, $data->codeCount);
            }

            return $program;
        }, 3);
    }
}
