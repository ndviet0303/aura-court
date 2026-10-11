<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Payment\ProviderWebhookService;
use Illuminate\Http\Request;

class ProviderWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, ProviderWebhookService $webhooks): array
    {
        return $webhooks->receive($provider, $request);
    }
}
