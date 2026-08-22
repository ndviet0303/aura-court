<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantRealtimeEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly string $eventType,
        public readonly array $payload,
        public readonly ?int $version = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->organizationId}.events")];
    }

    public function broadcastAs(): string
    {
        return $this->eventType;
    }

    public function broadcastWith(): array
    {
        return ['organizationId' => $this->organizationId, 'version' => $this->version, 'payload' => $this->payload];
    }
}
