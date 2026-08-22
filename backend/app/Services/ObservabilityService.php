<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ObservabilityService
{
    public function record(string $event, array $context = [], string $level = 'info'): void
    {
        $context += [
            'event' => $event,
            'request_id' => function_exists('request') && request()->hasHeader('X-Request-ID') ? request()->header('X-Request-ID') : null,
            'organization_id' => app(TenantContext::class)->getOrganizationId(),
            'occurred_at' => now()->toIso8601String(),
        ];
        Log::channel(config('observability.channel', 'structured'))->{$level}($event, $context);
    }

    public function metric(string $name, int|float $value = 1, array $tags = []): void
    {
        $this->record('metric', ['metric' => $name, 'value' => $value, 'tags' => $tags]);
    }

    public function alert(string $name, array $context = []): void
    {
        $this->record('alert', ['alert' => $name] + $context, 'warning');
    }
}
