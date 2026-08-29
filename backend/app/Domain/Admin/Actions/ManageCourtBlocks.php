<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Models\Court;
use App\Models\CourtBlock;

class ManageCourtBlocks
{
    public function __construct(
        private readonly CourtBlockRepositoryInterface $courtBlocks,
    ) {}

    public function createBlock(Court $court, array $data): CourtBlock
    {
        return $this->courtBlocks->createForCourt($court, [
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function deleteBlock(Court $court, CourtBlock $block): void
    {
        abort_unless($block->court_id === $court->id, 404);
        $this->courtBlocks->delete($block);
    }
}
