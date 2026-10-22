<?php

namespace App\Http\Resources\Reservation;

use App\Models\AffectedReservationCase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AffectedReservationCase */
class AffectedReservationCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reservation = $this->reservation;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'venue' => ['id' => $this->venue_id, 'name' => $this->venue?->name],
            'reservation' => [
                'id' => $reservation?->id,
                'code' => $reservation?->code,
                'status' => $reservation?->status,
                'payment_status' => match ($reservation?->status) {
                    'held' => 'awaiting_payment',
                    'confirmed', 'in_use' => 'paid',
                    default => $reservation?->status,
                },
                'customer_name' => $reservation?->booker['name'] ?? $reservation?->user?->name,
                'customer_phone' => $reservation?->booker['phone'] ?? $reservation?->user?->phone,
                'court_id' => $reservation->court_id ?? $this->court_id,
                'court_code' => $reservation?->court?->code,
                'starts_at' => $reservation?->starts_at?->toIso8601String(),
                'ends_at' => $reservation?->ends_at?->toIso8601String(),
            ],
            'source' => [
                'type' => $this->source_type,
                'id' => $this->source_id,
                'starts_at' => $this->source_starts_at?->toIso8601String(),
                'ends_at' => $this->sourceEnd()?->toIso8601String(),
            ],
            'channel' => $this->contact_channel,
            'note' => $this->note,
            'refund_note' => $this->refund_note,
            'handled_by' => $this->handledBy ? ['id' => $this->handledBy->id, 'name' => $this->handledBy->name] : null,
            'handled_at' => $this->handled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** A stop or suspension ends when it is resumed or lifted, so its end is read live; both columns hold UTC. */
    private function sourceEnd(): ?Carbon
    {
        $raw = match ($this->source_type) {
            'operational_stop' => $this->stop?->getRawOriginal('ended_at'),
            'venue_suspension' => $this->suspension?->getRawOriginal('lifted_at'),
            default => null,
        };

        return $raw !== null ? Carbon::parse($raw, 'UTC') : $this->source_ends_at;
    }
}
