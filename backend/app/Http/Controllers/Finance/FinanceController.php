<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\FinanceException;
use App\Models\Payout;
use App\Models\Settlement;
use App\Services\Finance\BeneficiaryService;
use App\Services\Finance\PayoutService;
use App\Services\Finance\ReconciliationService;
use App\Services\Finance\SettlementService;
use App\Services\Infrastructure\IdempotencyService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    use ResolvesOrganization;

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

    public function exportSettlementsCsv(Request $request)
    {
        $organization = $this->organization($request);
        $settlements = Settlement::query()->where('organization_id', $organization->id)->latest('period_end')->get();

        return response()->streamDownload(function () use ($settlements) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Period Start', 'Period End', 'Gross Amount', 'Refund Amount', 'Provider Fee', 'Commission', 'Tenant Payable', 'Currency', 'Status', 'Eligible At', 'Created At']);
            foreach ($settlements as $s) {
                fputcsv($output, [
                    $s->id,
                    $s->period_start,
                    $s->period_end,
                    $s->gross_amount,
                    $s->refund_amount,
                    $s->provider_fee_amount,
                    $s->commission_amount,
                    $s->tenant_payable,
                    $s->currency,
                    $s->status,
                    $s->eligible_at?->toIso8601String(),
                    $s->created_at?->toIso8601String(),
                ]);
            }
            fclose($output);
        }, 'settlements-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPayoutsCsv(Request $request)
    {
        $organization = $this->organization($request);
        $payouts = Payout::query()->where('organization_id', $organization->id)->with('beneficiary')->latest()->get();

        return response()->streamDownload(function () use ($payouts) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Settlement ID', 'Beneficiary', 'Bank Code', 'Account Number', 'Amount', 'Currency', 'Status', 'Provider', 'Submitted At', 'Paid At', 'Created At']);
            foreach ($payouts as $p) {
                fputcsv($output, [
                    $p->id,
                    $p->settlement_id,
                    $p->beneficiary?->account_holder,
                    $p->beneficiary?->bank_code,
                    $p->beneficiary?->account_number,
                    $p->amount,
                    $p->currency,
                    $p->status,
                    $p->provider,
                    $p->submitted_at?->toIso8601String(),
                    $p->paid_at?->toIso8601String(),
                    $p->created_at?->toIso8601String(),
                ]);
            }
            fclose($output);
        }, 'payouts-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportExceptionsCsv(Request $request)
    {
        $organization = $this->organization($request);
        $exceptions = FinanceException::query()->where('organization_id', $organization->id)->latest()->get();

        return response()->streamDownload(function () use ($exceptions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Reference', 'Type', 'Severity', 'Amount', 'Status', 'Description', 'Detected At', 'Due At', 'Resolved At']);
            foreach ($exceptions as $e) {
                fputcsv($output, [
                    $e->id,
                    $e->reference,
                    $e->type,
                    $e->severity,
                    $e->amount,
                    $e->status,
                    $e->description,
                    $e->detected_at?->toIso8601String(),
                    $e->due_at?->toIso8601String(),
                    $e->resolved_at?->toIso8601String(),
                ]);
            }
            fclose($output);
        }, 'finance-exceptions-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function assertPayoutOrganization(Request $request, Payout $payout): void
    {
        $organization = $this->organization($request);
        abort_unless($payout->organization_id === $organization->id, 404);
    }
}
