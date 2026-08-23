<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments)
    {
        $raw = $request->getContent();
        $signature = $request->header('Stripe-Signature') ?? '';
        $data = $request->json()->all();

        $payments->handleStripeWebhook($data, $raw, $signature);

        return response()->json(['received' => true]);
    }
}
