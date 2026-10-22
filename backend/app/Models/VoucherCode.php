<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A single-use code of a private program, unique across the platform; whether it is free is read from its uses (D8). */
#[Fillable(['program_id', 'code'])]
class VoucherCode extends Model
{
    /** @return BelongsTo<PromotionProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PromotionProgram::class, 'program_id');
    }

    /**
     * The use holding or having used the code, at most one by the partial unique index (D11).
     *
     * @return HasOne<VoucherUse, $this>
     */
    public function activeUse(): HasOne
    {
        return $this->hasOne(VoucherUse::class, 'voucher_code_id')->whereIn('status', [VoucherUse::STATUS_HELD, VoucherUse::STATUS_USED]);
    }
}
