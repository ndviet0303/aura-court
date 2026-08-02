<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'provider', 'external_transaction_id', 'amount', 'transfer_code', 'status', 'payload', 'received_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer', 'payload' => 'array', 'received_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
