<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\POS\QuotePosOrderRequest;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\POS\ServiceTabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceTabController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly ServiceTabService $tabs,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $tabs = $this->tabs->listTabs($request->user(), $organization->id, $request->all());

        return response()->json($tabs);
    }

    public function show(Request $request, int|string $reservation): JsonResponse
    {
        $organization = $this->organization($request);
        $tab = $this->tabs->getTab($request->user(), $organization->id, $reservation);

        return response()->json(['tab' => $tab]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'reservation_id' => 'required',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $tab = $this->tabs->addItems(
                staff: $request->user(),
                organizationId: $organization->id,
                reservationIdOrCode: $data['reservation_id'],
                items: $data['items'],
            );

            return response()->json([
                'tab' => $tab,
                'message' => 'Items added to service tab and stock reserved.',
            ], 201);
        });
    }

    public function removeItem(Request $request, int|string $reservation, int $item): JsonResponse
    {
        $organization = $this->organization($request);

        return $this->idempotency->run($request, function () use ($request, $organization, $reservation, $item) {
            $tab = $this->tabs->removeItem(
                staff: $request->user(),
                organizationId: $organization->id,
                reservationIdOrCode: $reservation,
                itemId: $item,
            );

            return response()->json([
                'tab' => $tab,
                'message' => 'Item removed and stock reservation released.',
            ]);
        });
    }

    public function settle(Request $request, int $tab): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'payment_method' => 'required|in:cash,wallet,qr',
            'cash_tendered' => 'nullable|integer|min:0',
            'customer_id' => 'nullable|integer',
            ...QuotePosOrderRequest::NO_VOUCHER,
        ], QuotePosOrderRequest::NO_VOUCHER_MESSAGES);

        return $this->idempotency->run($request, function () use ($request, $organization, $tab, $data) {
            $result = $this->tabs->settleTab(
                cashier: $request->user(),
                organizationId: $organization->id,
                tabId: $tab,
                paymentData: $data,
            );

            return response()->json([
                'tab' => $result['tab'],
                'order' => $result['order'],
                'receipt' => $result['receipt'],
                'duplicate' => $result['duplicate'] ?? false,
                'message' => 'Service tab settled successfully.',
            ], 200);
        });
    }

    public function cancel(Request $request, int $tab): JsonResponse
    {
        $organization = $this->organization($request);

        return $this->idempotency->run($request, function () use ($request, $organization, $tab) {
            $cancelled = $this->tabs->cancelTab(
                staff: $request->user(),
                organizationId: $organization->id,
                tabId: $tab,
                reason: $request->input('reason'),
            );

            return response()->json([
                'tab' => $cancelled,
                'message' => 'Service tab cancelled and stock reservations released.',
            ]);
        });
    }
}
