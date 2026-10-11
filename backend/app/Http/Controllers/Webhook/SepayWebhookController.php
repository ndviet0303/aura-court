<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

class SepayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments)
    {
        $raw = $request->getContent();
        $signature = $request->header('X-SePay-Signature') ?? '';
        $expected = hash_hmac('sha256', $raw, (string) config('services.sepay.hmac_secret'));
        $apiKeyOk = config('services.sepay.api_key') && hash_equals('Apikey '.config('services.sepay.api_key'), $request->header('Authorization', ''));
        abort_unless($signature !== '' ? hash_equals($expected, $signature) : $apiKeyOk, 401, 'Invalid webhook signature.');
        $payments->handleWebhook($request->json()->all(), $raw);

        return ['success' => true];
    }
}
