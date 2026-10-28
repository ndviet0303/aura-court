<?php

namespace App\Http\Resources\Promotion;

use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\VoucherUse;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One booking's use of a program for the owner's usage tracking (FR-68): its status, the booking's rental before the
 * discount (UC-3.17 step 4, from its accepted quote), the discount, private code and
 * times, and the booking with its customer, court and time. used_at is the confirmation time consume recorded (D11);
 * the booking's own status shows a booking cancelled after confirmation. Instants are ISO-8601 UTC.
 *
 * @mixin VoucherUse
 */
class VoucherUseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Reservation $reservation */
        $reservation = $this->reservation;
        /** @var PriceQuote|null $quote */
        $quote = $reservation->quote;

        return [
            'id' => (int) $this->id,
            'status' => (string) $this->status,
            'rental' => $quote === null ? null : (int) $quote->subtotal,
            'discount' => (int) $this->discount,
            'code' => $this->voucherCode?->code,
            'held_at' => self::utc($this->held_at),
            'used_at' => self::utc($this->used_at),
            'reservation' => [
                'id' => (int) $reservation->id,
                'code' => (string) $reservation->code,
                'status' => (string) $reservation->status,
                // Check-in, check-out and no-show stay on metadata while status stays confirmed (UC-3.17 step 4).
                'operational_status' => $reservation->metadata['operational_status'] ?? null,
                // The contact recorded at booking, so a later resale of the slot does not rename the voucher's user.
                'customer_name' => $reservation->booker['name'] ?? $reservation->user?->name,
                'court' => $reservation->court === null ? null : ['id' => (int) $reservation->court->id, 'name' => (string) $reservation->court->code],
                'starts_at' => self::utc($reservation->starts_at),
                'ends_at' => self::utc($reservation->ends_at),
            ],
        ];
    }

    private static function utc(?DateTimeInterface $at): ?string
    {
        return $at === null ? null : CarbonImmutable::instance($at)->utc()->toIso8601String();
    }
}
