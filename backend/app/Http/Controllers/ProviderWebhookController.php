<?php

namespace App\Http\Controllers;

use App\Services\ProviderWebhookService;
use Illuminate\Http\Request;

class ProviderWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, ProviderWebhookService $webhooks): array
    {
        return $webhooks->receive($provider, $request);
    }
}
