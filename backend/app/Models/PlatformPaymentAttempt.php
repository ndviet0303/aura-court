<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlatformPaymentAttempt extends Model
{
    use HasFactory;

    protected $table = 'platform_payment_attempts';

    protected $fillable = [
        'subscription_id',
        'organization_id',
        'reference_code',
        'amount',
        'currency',
        'provider',
        'provider_ref',
        'status',
        'failure_reason',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscription::class, 'subscription_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PlatformProviderReceipt::class, 'payment_attempt_id');
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
