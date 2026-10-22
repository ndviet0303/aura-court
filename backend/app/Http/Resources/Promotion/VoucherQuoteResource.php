<?php

namespace App\Http\Resources\Promotion;

use App\Domain\Pricing\DTOs\RentalQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A voucher priced for one court interval (design D10): its terms from the quote's snapshot and the amounts with it.
 * id is the program a checkout names as voucher_program_id; code is set for a private code only. percent is
 * percent_bp / 100 for display, as on the owner's program (D27); the discount itself is integer math in the engine.
 * Instants are ISO-8601 with the application clock's offset, as the booking snapshot keeps them.
 *
 * @mixin RentalQuote
 */
class VoucherQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $voucher = $this->snapshot['voucher'];

        return [
            'id' => $voucher['program_id'],
            'name' => $voucher['name'],
            'type' => $voucher['type'],
            'code' => $voucher['code'],
            'discount_kind' => $voucher['discount_kind'],
            'percent_bp' => $voucher['percent_bp'],
            'percent' => $voucher['percent_bp'] === null ? null : $voucher['percent_bp'] / 100,
            'amount' => $voucher['amount'],
            'max_discount' => $voucher['max_discount'],
            'min_rental' => $voucher['min_rental'],
            'starts_at' => $voucher['starts_at'],
            'ends_at' => $voucher['ends_at'],
            'rental' => $this->rental,
            'discount' => $this->discount,
            'total' => $this->total,
        ];
    }
}
