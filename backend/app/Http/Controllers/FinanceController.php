<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\Beneficiary;
use App\Models\FinanceException;
use App\Models\Payout;
use App\Models\Settlement;
use App\Services\BeneficiaryService;
use App\Services\IdempotencyService;
use App\Services\PayoutService;
use App\Services\ReconciliationService;
use App\Services\SettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    use ResolvesV2Organization;

    public function __construct(
        private readonly BeneficiaryService $beneficiaries,
        private readonly SettlementService $settlements,
        private readonly PayoutService $payouts,
        private readonly ReconciliationService $reconciliation,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function beneficiaries(Request $request)
    {
        $organization = $this->organization($request);
        return Beneficiary::query()->where('organization_id', $organization->id)->latest()->get();
    }

    public function storeBeneficiary(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['provider' => 'nullable|string|max:40', 'bank_code' => 'required|string|max:30', 'account_number' => 'required|string|max:80', 'account_holder' => 'nullable|string|max:180', 'tax_id' => 'nullable|string|max:80']);
        return $this->idempotency->run($request, fn () => response()->json(['beneficiary' => $this->beneficiaries->verifyAndCreate($organization, $data)], 201));
    }

    public function settlements(Request $request)
    {
        $organization = $this->organization($request);
        return Settlement::query()->where('organization_id', $organization->id)->with('payouts')->latest('period_end')->paginate(30);
    }

    public function snapshotSettlement(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start']);
        return $this->idempotency->run($request, fn () => response()->json(['settlement' => $this->settlements->snapshot($organization, CarbonImmutable::parse($data['period_start']), CarbonImmutable::parse($data['period_end']))], 201));
    }

    public function payouts(Request $request)
    {
        $organization = $this->organization($request);
        return Payout::query()->where('organization_id', $organization->id)->with(['settlement', 'beneficiary'])->latest()->paginate(30);
    }

    public function createPayout(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['settlement_id' => 'required|integer', 'beneficiary_id' => 'required|integer']);
        $settlement = Settlement::where('organization_id', $organization->id)->findOrFail($data['settlement_id']);
        $beneficiary = Beneficiary::where('organization_id', $organization->id)->findOrFail($data['beneficiary_id']);
        return $this->idempotency->run($request, fn () => response()->json(['payout' => $this->payouts->createDraft($settlement, $beneficiary)], 201));
    }

    public function approvePayout(Request $request, Payout $payout)
    {
        $this->assertPayoutOrganization($request, $payout);
        return $this->idempotency->run($request, fn () => response()->json(['payout' => $this->payouts->approve($payout)]));
    }

    public function submitPayout(Request $request, Payout $payout)
    {
        $this->assertPayoutOrganization($request, $payout);
        return $this->idempotency->run($request, fn () => response()->json(['payout' => $this->payouts->submit($payout)]));
    }

    public function payoutStatus(Request $request, Payout $payout)
    {
        $this->assertPayoutOrganization($request, $payout);
        return $this->idempotency->run($request, fn () => response()->json(['payout' => $this->payouts->syncStatus($payout)]));
    }

    public function exceptions(Request $request)
    {
        $organization = $this->organization($request);
        return FinanceException::query()->where('organization_id', $organization->id)->latest()->paginate(30);
    }

    public function resolveException(Request $request, FinanceException $exception)
    {
        $organization = $this->organization($request);
        abort_unless($exception->organization_id === $organization->id, 404);
        $data = $request->validate(['resolution' => 'required|string|max:2000']);
        return $this->idempotency->run($request, fn () => response()->json(['exception' => $this->reconciliation->resolve($exception, $request->user()->id, $data['resolution'])]));
    }

    private function assertPayoutOrganization(Request $request, Payout $payout): void
    {
        $organization = $this->organization($request);
        abort_unless($payout->organization_id === $organization->id, 404);
    }
}
