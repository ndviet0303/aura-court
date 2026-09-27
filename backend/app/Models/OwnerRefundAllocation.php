<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['owner_refund_obligation_id', 'owner_payment_allocation_id', 'gross_amount', 'fee_amount', 'amount', 'created_at'])]
class OwnerRefundAllocation extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['gross_amount' => 'integer', 'fee_amount' => 'integer', 'amount' => 'integer', 'created_at' => 'datetime'];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(OwnerRefundObligation::class, 'owner_refund_obligation_id');
    }

    public function paymentAllocation(): BelongsTo
    {
        return $this->belongsTo(OwnerPaymentAllocation::class, 'owner_payment_allocation_id');
    }
}
