<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourtStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $venueId,
        public readonly int $courtId,
        public readonly string $status,
        public readonly array $metadata = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("venue.{$this->venueId}")];
    }

    public function broadcastAs(): string
    {
        return 'CourtStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'venue_id' => $this->venueId,
            'court_id' => $this->courtId,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
