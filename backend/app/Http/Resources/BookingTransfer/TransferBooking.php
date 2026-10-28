<?php

namespace App\Http\Resources\BookingTransfer;

use App\Models\Reservation;
use App\Models\User;

/** FR-140, BR-180: what every transfer view shows of a booking, read from it; never its codes or contact. */
final class TransferBooking
{
    /** @return array{venue: array<string, mixed>|null, court: array<string, mixed>|null, starts_at: ?string, ends_at: ?string} */
    public static function of(?Reservation $reservation): array
    {
        $court = $reservation?->court;
        $venue = $court?->venue;

        return [
            'venue' => $venue === null ? null : ['id' => (int) $venue->id, 'name' => (string) $venue->name, 'address' => $venue->address],
            'court' => $court === null ? null : [
                'id' => (int) $court->id,
                'name' => (string) $court->code,
                'image_url' => ($court->images ?? [])[0] ?? ($venue->images ?? [])[0] ?? null,
            ],
            'starts_at' => $reservation?->starts_at?->toIso8601String(),
            'ends_at' => $reservation?->ends_at?->toIso8601String(),
        ];
    }

    /** FR-141: who posted, without email or phone. @return array{id: int, name: string, avatar_url: ?string}|null */
    public static function person(?User $user): ?array
    {
        return $user === null ? null : ['id' => (int) $user->id, 'name' => (string) $user->name, 'avatar_url' => $user->avatar_url];
    }
}
