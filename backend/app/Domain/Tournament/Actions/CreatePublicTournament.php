<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Domain\Tournament\DTOs\TournamentSetupData;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\User;

/** FR-120, UC-7.16: the venue owner sets up a public tournament as a draft; it is seen once published. */
class CreatePublicTournament
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly VenueRepositoryInterface $venues,
    ) {}

    public function execute(User $owner, int $venueId, TournamentSetupData $setup): Tournament
    {
        $venue = $this->venues->findById($venueId);
        if ($venue === null || ! $owner->canManageVenue($venue)) {
            TournamentAccess::fail('TOURNAMENT_FORBIDDEN', 'Chỉ chủ cơ sở được tạo giải đấu tại cơ sở này.', 403);
        }

        return $this->tournaments->create($setup->attributes + [
            'type' => Tournament::TYPE_PUBLIC,
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->id,
            'created_by' => $owner->id,
            'status' => Tournament::STATUS_DRAFT,
        ]);
    }
}
