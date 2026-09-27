<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\SePayReceivingConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceivingConnectionWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $routingId,
        SePayReceivingConnectionService $connections,
        OwnerPaymentAttemptService $payments
    ): JsonResponse {
        $connection = ReceivingConnection::query()
            ->where('routing_id', $routingId)
            ->where('provider', 'sepay')
            ->firstOrFail();

        $verified = $connections->verifyInboundWebhook(
            $connection,
            (string) $request->header('X-SePay-Timestamp', ''),
            (string) $request->header('X-SePay-Signature', ''),
            $request->getContent()
        );
        $receipt = $payments->receiveVerifiedEvent($connection, $verified);
        if ($payments->claimReceiptForDispatch((int) $receipt->id)) {
            ProcessOwnerPaymentReceipt::dispatch((int) $receipt->id)->afterCommit();
        }

        return response()->json(['success' => true]);
    }
}
