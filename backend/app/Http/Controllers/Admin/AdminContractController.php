<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Domain\Admin\Actions\CancelAdminContract;
use App\Domain\Admin\Actions\CreateAdminContract;
use App\Domain\Admin\Actions\GetAdminContracts;
use App\Domain\Admin\Actions\PayAdminContractInvoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContractRequest;
use App\Services\Infrastructure\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminContractController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audits,
        private readonly RecurringContractRepositoryInterface $contracts,
    ) {}

    public function index(Request $request, GetAdminContracts $action): JsonResponse
    {
        $filters = [
            'status' => $request->query('status', 'all'),
            'search' => $request->query('search', ''),
            'venue_id' => $request->query('venue_id'),
        ];

        $perPage = (int) $request->query('per_page', 20);
        $result = $action->execute($filters, $perPage);

        return response()->json($result);
    }

    public function store(StoreContractRequest $request, CreateAdminContract $action): JsonResponse
    {
        $data = $request->validated();
        $contract = $action->execute($data);

        $this->audits->record($request, 'contract.created', $contract, [
            'contract_code' => $contract->metadata['contract_code'] ?? "CTR-{$contract->id}",
            'customer_name' => $contract->metadata['customer_name'] ?? $contract->user?->name,
            'court_id' => $contract->court_id,
        ]);

        return response()->json($contract, 201);
    }

    public function show(int $id): JsonResponse
    {
        $contract = $this->contracts->findById($id);
        abort_unless($contract, 404, 'Hợp đồng không tồn tại.');

        return response()->json($contract);
    }

    public function cancel(Request $request, int $id, CancelAdminContract $action): JsonResponse
    {
        $reason = $request->input('reason', 'Admin huỷ theo yêu cầu');
        $contract = $action->execute($id, $reason);

        $this->audits->record($request, 'contract.cancelled', $contract, [
            'reason' => $reason,
        ]);

        return response()->json($contract);
    }

    public function payInvoice(Request $request, int $id, int $invoiceId, PayAdminContractInvoice $action): JsonResponse
    {
        $data = $request->validate([
            'payment_reference' => 'nullable|string|max:160',
        ]);

        $reference = $data['payment_reference'] ?? 'PAY-MANUAL-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $invoice = $action->execute($id, $invoiceId, $reference);

        $this->audits->record($request, 'contract.invoice_paid', $invoice, [
            'series_id' => $id,
            'invoice_id' => $invoiceId,
            'amount' => $invoice->amount,
            'reference' => $reference,
        ]);

        return response()->json($invoice);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = [
            'status' => $request->query('status', 'all'),
            'search' => $request->query('search', ''),
            'venue_id' => $request->query('venue_id'),
        ];

        $paginator = $this->contracts->getAllPaginated($filters, 1000);
        $contracts = $paginator->items();

        return response()->streamDownload(function () use ($contracts) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Contract Code', 'Customer Name', 'Phone', 'Court', 'Venue', 'Frequency', 'Status', 'Monthly Amount', 'Total Amount', 'Created At']);
            foreach ($contracts as $c) {
                $code = $c->metadata['contract_code'] ?? "CTR-{$c->id}";
                $name = $c->metadata['customer_name'] ?? $c->user?->name;
                $phone = $c->metadata['phone'] ?? $c->user?->phone;
                $court = $c->court?->name;
                $venue = $c->court?->venue?->name;
                $monthly = $c->metadata['monthly_amount'] ?? ($c->invoices->first()?->amount ?: 0);
                fputcsv($output, [
                    $code,
                    $name,
                    $phone,
                    $court,
                    $venue,
                    $c->frequency,
                    $c->status,
                    $monthly,
                    $c->total_amount,
                    $c->created_at?->toIso8601String(),
                ]);
            }
            fclose($output);
        }, 'contracts-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
