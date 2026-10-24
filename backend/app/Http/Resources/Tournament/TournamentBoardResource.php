<?php

namespace App\Http\Resources\Tournament;

use App\Models\TournamentEntry;
use App\Models\TournamentFixture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * FR-122: the play of a tournament as TournamentBoard computes it. The public page lists units without accounts;
 * the management screen lists every entry in full, so it leaves the entries out here.
 *
 * @property array{entries: Collection<int, TournamentEntry>, fixtures: Collection<int, TournamentFixture>, standings: array<string, mixed>} $resource
 */
class TournamentBoardResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly bool $withEntries = true)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return ($this->withEntries ? ['entries' => PublicEntryResource::collection($this->resource['entries'])] : []) + [
            'fixtures' => TournamentFixtureResource::collection($this->resource['fixtures']),
            'standings' => (object) $this->resource['standings'],
        ];
    }
}
