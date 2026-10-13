<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Domain\Admin\Actions\CancelAdminContract;
use App\Domain\Admin\Actions\GetAdminContracts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelAdminContractRequest;
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
        $user = $request->user();
        $filters = [
            'status' => $request->query('status', 'all'),
            'search' => $request->query('search', ''),
            'venue_id' => $request->query('venue_id'),
        ];

        if ($user && ! $user->isPlatformAdmin()) {
            $authorizedVenueIds = $user->getAuthorizedVenueIds() ?? [];
            if ($request->filled('venue_id')) {
                $requestedVenueId = (int) $request->query('venue_id');
                abort_unless(in_array($requestedVenueId, $authorizedVenueIds, true), 403, 'Unauthorized venue.');
                $filters['venue_id'] = $requestedVenueId;
            } else {
                $filters['venue_ids'] = $authorizedVenueIds;
            }
        }

        $perPage = (int) $request->query('per_page', 20);
        $result = $action->execute($filters, $perPage);

        return response()->json($result);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contract = $this->contracts->findById($id);
        abort_unless($contract, 404, 'Hợp đồng không tồn tại.');
        $user = $request->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($contract->court && $user->canManageVenue($contract->court->venue_id), 403, 'Unauthorized contract venue.');
        }

        return response()->json($contract);
    }

    public function cancel(CancelAdminContractRequest $request, int $id, CancelAdminContract $action): JsonResponse
    {
        $contract = $this->contracts->findById($id);
        abort_unless($contract, 404, 'Hợp đồng không tồn tại.');
        $user = $request->user();
        if ($user && ! $user->isPlatformAdmin()) {
            abort_unless($contract->court && $user->canManageVenue($contract->court->venue_id), 403, 'Unauthorized contract venue.');
        }

        $reason = $request->validated('reason') ?? 'Admin huỷ theo yêu cầu';
        $contract = $action->execute($contract, $user, $reason);

        $this->audits->record($request, 'contract.cancelled', $contract, [
            'reason' => $reason,
        ]);

        return response()->json($contract);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = [
            'status' => $request->query('status', 'all'),
            'search' => $request->query('search', ''),
            'venue_id' => $request->query('venue_id'),
        ];

        if ($user && ! $user->isPlatformAdmin()) {
            $authorizedVenueIds = $user->getAuthorizedVenueIds() ?? [];
            if ($request->filled('venue_id')) {
                $requestedVenueId = (int) $request->query('venue_id');
                abort_unless(in_array($requestedVenueId, $authorizedVenueIds, true), 403, 'Unauthorized venue export.');
                $filters['venue_id'] = $requestedVenueId;
            } else {
                $filters['venue_ids'] = $authorizedVenueIds;
            }
        }
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
