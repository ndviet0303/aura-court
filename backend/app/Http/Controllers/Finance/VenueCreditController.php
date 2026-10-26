<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateVenueCreditCashOutCodeRequest;
use App\Http\Resources\Finance\VenueCreditEntryResource;
use App\Models\Organization;
use App\Models\VenueCreditAccount;
use App\Services\Finance\VenueCreditCashOutService;
use App\Services\Finance\VenueCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The customer's own venue credit: balances per organization and the one-time counter cash-out code (design D8). */
class VenueCreditController extends Controller
{
    public function __construct(private readonly VenueCreditService $credits) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->credits->accountsOf($request->user()->id)->map(fn (VenueCreditAccount $account): array => [
            'organization' => ['id' => $account->organization_id, 'name' => $account->organization?->name],
            'balance' => $account->balance,
            'reserved' => $account->reserved,
            'entries' => VenueCreditEntryResource::collection($account->entries)->resolve($request),
        ])->values()]);
    }

    public function storeCashOutCode(CreateVenueCreditCashOutCodeRequest $request, Organization $organization, VenueCreditCashOutService $cashOuts): JsonResponse
    {
        return response()->json($cashOuts->create($request->user(), $organization, (int) $request->validated('amount')), 201);
    }
}
