<?php

namespace App\Http\Controllers\Tournament;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Domain\Tournament\Actions\ConfirmEntryPayment;
use App\Domain\Tournament\Actions\OpenEntryPayment;
use App\Domain\Tournament\Actions\RemoveRosterEntry;
use App\Domain\Tournament\Actions\ReviewEntry;
use App\Domain\Tournament\Actions\SubmitEntry;
use App\Domain\Tournament\Actions\WithdrawEntry;
use App\Domain\Tournament\DTOs\EntryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\ReasonRequest;
use App\Http\Requests\Tournament\SaveEntryRequest;
use App\Http\Resources\Tournament\TournamentEntryResource;
use App\Http\Resources\Tournament\TournamentSummaryResource;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\ReservationPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** UC-7.21–UC-7.25: applying, following, withdrawing and paying; the owner's review; removing a roster unit. */
class TournamentEntryController extends Controller
{
    public function store(SaveEntryRequest $request, Tournament $tournament, SubmitEntry $action): JsonResponse
    {
        $entry = $action->execute($tournament, $request->user(), EntryData::from($request->validated()));

        return response()->json(['entry' => TournamentEntryResource::make($entry->load(['members', 'user']))], 201);
    }

    public function mine(Request $request, TournamentPlayRepositoryInterface $play, OwnerPaymentAttemptService $attempts, ReservationPaymentService $payments): JsonResponse
    {
        $data = $play->entriesOf($request->user())->map(function (TournamentEntry $entry) use ($request, $attempts, $payments): array {
            $attempt = $attempts->latestForPurpose(ConfirmEntryPayment::PURPOSE, (string) $entry->id);

            return TournamentEntryResource::make($entry)->resolve($request) + [
                'tournament' => TournamentSummaryResource::make($entry->tournament)->resolve($request),
                'payment' => $attempt ? $payments->instructions($attempt) : null,
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    public function withdraw(Request $request, TournamentEntry $entry, WithdrawEntry $action): JsonResponse
    {
        return response()->json(['entry' => TournamentEntryResource::make($action->execute($entry, $request->user())->load(['members', 'user']))]);
    }

    public function pay(Request $request, TournamentEntry $entry, OpenEntryPayment $action): JsonResponse
    {
        $payment = $action->execute($entry, $request->user());

        return response()->json(['entry' => TournamentEntryResource::make($entry->load(['members', 'user'])), 'payment' => $payment]);
    }

    public function approve(Request $request, TournamentEntry $entry, ReviewEntry $action): JsonResponse
    {
        return response()->json(['entry' => TournamentEntryResource::make($action->execute($entry, $request->user(), true)->load(['members', 'user']))]);
    }

    public function reject(ReasonRequest $request, TournamentEntry $entry, ReviewEntry $action): JsonResponse
    {
        $entry = $action->execute($entry, $request->user(), false, (string) $request->validated('reason'));

        return response()->json(['entry' => TournamentEntryResource::make($entry->load(['members', 'user']))]);
    }

    public function destroy(Request $request, TournamentEntry $entry, RemoveRosterEntry $action): Response
    {
        $action->execute($entry, $request->user());

        return response()->noContent();
    }
}
