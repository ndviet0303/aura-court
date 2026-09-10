<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformProviderReceipt extends Model
{
    use HasFactory;

    protected $table = 'platform_provider_receipts';

    protected $fillable = [
        'payment_attempt_id',
        'provider',
        'provider_transaction_id',
        'amount',
        'currency',
        'raw_payload',
        'verified_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'raw_payload' => 'array',
        'verified_at' => 'datetime',
    ];

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PlatformPaymentAttempt::class, 'payment_attempt_id');
    }
}
