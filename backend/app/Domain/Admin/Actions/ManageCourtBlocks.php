<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\Venue;
use App\Services\Reservation\CourtCommitmentConflictService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ManageCourtBlocks
{
    public function __construct(
        private readonly CourtBlockRepositoryInterface $courtBlocks,
        private readonly CourtCommitmentConflictService $conflicts,
    ) {}

    public function createBlock(Court $court, array $data): CourtBlock
    {
        $startsAt = Carbon::parse($data['starts_at'])->utc();
        $endsAt = Carbon::parse($data['ends_at'])->utc();

        return DB::transaction(function () use ($court, $startsAt, $endsAt, $data) {
            Venue::query()->whereKey($court->venue_id)->lockForUpdate()->firstOrFail();
            $court = Court::query()->whereKey($court->id)->lockForUpdate()->firstOrFail();
            $this->conflicts->assertBlockPreservesCommitments($court, $startsAt, $endsAt);

            return $this->courtBlocks->createForCourt($court, [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $data['reason'] ?? null,
            ]);
        });
    }

    public function deleteBlock(Court $court, CourtBlock $block): void
    {
        abort_unless($block->court_id === $court->id, 404);
        $this->courtBlocks->delete($block);
    }
}
