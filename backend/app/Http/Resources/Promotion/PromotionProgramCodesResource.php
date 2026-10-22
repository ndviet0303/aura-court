<?php

namespace App\Http\Resources\Promotion;

use App\Models\PromotionProgram;
use App\Models\VoucherCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A private program's codes by id, each with its state read from voucher uses (available, held or used, D8), the
 * booking holding or having used it (FR-68), and the deep link its QR encodes (D9): the booking page of the court
 * with the smallest id in the program's set now, with the code filled in. A public program has no codes.
 * A free code of an ended or stopped program is unusable rather than available.
 *
 * @mixin PromotionProgram
 */
class PromotionProgramCodesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $court = $this->courts->first();
        $base = $court === null ? null : rtrim((string) config('app.frontend_url'), '/').'/courts/'.$court->slug.'/schedule?voucher=';
        $free = in_array($this->statusAt(), [PromotionProgram::STATUS_ENDED, PromotionProgram::STATUS_STOPPED], true) ? 'unusable' : 'available';

        return [
            'program_id' => (int) $this->id,
            'court' => $court === null ? null : ['id' => (int) $court->id, 'name' => (string) $court->name, 'slug' => (string) $court->slug],
            'codes' => $this->codes->map(fn (VoucherCode $code): array => [
                'id' => (int) $code->id,
                'code' => (string) $code->code,
                'state' => $code->activeUse?->status ?? $free,
                'reservation' => $code->activeUse?->reservation === null ? null : [
                    'id' => (int) $code->activeUse->reservation->id,
                    'code' => (string) $code->activeUse->reservation->code,
                ],
                'deep_link_url' => $base === null ? null : $base.$code->code,
            ])->all(),
        ];
    }
}
