<?php

namespace App\Http\Resources\Finance;

use App\Models\FinanceException;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * One payment exception with the booking its code named (UC-4.12 steps 4-5): the amount due, status, payment deadline
 * and the booker's contact recorded on the booking, so the owner can reconcile and call the customer. Null booking when
 * the code matched none (A-1). Instants are ISO-8601 UTC.
 */
class OwnerPaymentExceptionDetailResource extends OwnerPaymentExceptionResource
{
    public function __construct(FinanceException $exception, private readonly ?Reservation $booking)
    {
        parent::__construct($exception);
    }

    public function toArray(Request $request): array
    {
        $booking = $this->booking;
        $booker = $booking === null ? [] : ($booking->booker ?? []);

        return [
            ...parent::toArray($request),
            'booking' => $booking === null ? null : [
                'code' => (string) $booking->code,
                'status' => (string) $booking->status,
                'release_reason' => $booking->metadata['release_reason'] ?? null,
                'total_amount' => (int) $booking->total_amount,
                'payment_deadline' => self::utc($booking->hold_expires_at),
                'court' => $booking->court?->code,
                'venue' => $booking->court?->venue?->name,
                'starts_at' => self::utc($booking->starts_at),
                'ends_at' => self::utc($booking->ends_at),
                'booker' => [
                    'name' => $booker['name'] ?? $booking->user?->name,
                    'phone' => $booker['phone'] ?? $booking->user?->phone,
                    'email' => $booker['email'] ?? $booking->user?->email,
                ],
            ],
        ];
    }

    private static function utc(?DateTimeInterface $at): ?string
    {
        return $at === null ? null : CarbonImmutable::instance($at)->utc()->toIso8601String();
    }
}
