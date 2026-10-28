<?php

namespace App\Http\Controllers\Tournament;

use App\Domain\Tournament\Actions\AddRosterEntry;
use App\Domain\Tournament\Actions\ImportRoster;
use App\Domain\Tournament\Actions\UpdateRosterEntry;
use App\Domain\Tournament\DTOs\EntryData;
use App\Domain\Tournament\Support\RosterSheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\RosterFileRequest;
use App\Http\Requests\Tournament\SaveEntryRequest;
use App\Http\Resources\Tournament\TournamentEntryResource;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** UC-7.27, FR-126: an internal tournament's roster, from the spreadsheet template or typed and corrected unit by unit. */
class TournamentRosterController extends Controller
{
    public function template(Request $request, Tournament $tournament): StreamedResponse
    {
        ImportRoster::assertOpen($tournament, $request->user());

        return RosterSheet::template($tournament);
    }

    public function preview(RosterFileRequest $request, Tournament $tournament, ImportRoster $action): JsonResponse
    {
        $sheet = $action->preview($tournament, $request->user(), (string) $request->file('file')->getRealPath());

        return response()->json(['entries' => array_map(fn (EntryData $entry): array => ['name' => $entry->name, 'members' => $entry->members], $sheet['entries']), 'errors' => $sheet['errors']]);
    }

    public function confirm(RosterFileRequest $request, Tournament $tournament, ImportRoster $action): JsonResponse
    {
        return response()->json(['entries' => TournamentEntryResource::collection($action->confirm($tournament, $request->user(), (string) $request->file('file')->getRealPath()))]);
    }

    public function store(SaveEntryRequest $request, Tournament $tournament, AddRosterEntry $action): JsonResponse
    {
        $entry = $action->execute($tournament, $request->user(), EntryData::from($request->validated()));

        return response()->json(['entry' => TournamentEntryResource::make($entry->load(['members', 'user']))], 201);
    }

    public function update(SaveEntryRequest $request, TournamentEntry $entry, UpdateRosterEntry $action): JsonResponse
    {
        $entry = $action->execute($entry, $request->user(), EntryData::from($request->validated()));

        return response()->json(['entry' => TournamentEntryResource::make($entry)]);
    }
}
