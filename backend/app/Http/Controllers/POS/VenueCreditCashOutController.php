<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\VenueCreditCashOutCodeRequest;
use App\Models\CashShift;
use App\Models\User;
use App\Services\Finance\VenueCreditCashOutService;
use App\Services\Finance\VenueCreditService;
use App\Services\Infrastructure\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Venue credit at the counter: the served customer's balance and the cash-out code lookup and confirmation (design D7). */
class VenueCreditCashOutController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly VenueCreditCashOutService $cashOuts,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function customer(Request $request, User $user, VenueCreditService $credits): JsonResponse
    {
        return response()->json(['user_id' => $user->id, 'balance' => $credits->balance($this->organization($request)->id, $user->id)]);
    }

    public function lookup(VenueCreditCashOutCodeRequest $request, CashShift $shift): JsonResponse
    {
        abort_unless($shift->organization_id === $this->organization($request)->id, 404);

        return response()->json($this->cashOuts->lookup($shift, $request->user(), (string) $request->validated('code')));
    }

    public function store(VenueCreditCashOutCodeRequest $request, CashShift $shift): Response
    {
        abort_unless($shift->organization_id === $this->organization($request)->id, 404);

        return $this->idempotency->run($request, fn () => response()->json(
            $this->cashOuts->confirm($shift, $request->user(), (string) $request->validated('code')),
            201,
        ));
    }
}
