<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSubscription extends Model
{
    use HasFactory;

    protected $table = 'platform_subscriptions';

    protected $fillable = [
        'organization_id',
        'plan_id',
        'plan_version',
        'status',
        'billing_cycle',
        'auto_renew',
        'trial_started_at',
        'trial_ends_at',
        'trial_used',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'price_snapshot',
        'currency_snapshot',
        'terms_version_accepted',
        'terms_accepted_at',
        'cancellation_reason',
        // Compatibility aliases
        'price',
        'currency',
        'trial_starts_at',
        'accepted_terms_version',
    ];

    protected $casts = [
        'plan_version' => 'integer',
        'auto_renew' => 'boolean',
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'trial_used' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'price_snapshot' => 'integer',
        'terms_accepted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PlatformPaymentAttempt::class, 'subscription_id');
    }

    public function setPriceAttribute($value): void
    {
        $this->attributes['price_snapshot'] = $value;
    }

    public function getPriceAttribute()
    {
        return $this->price_snapshot;
    }

    public function setCurrencyAttribute($value): void
    {
        $this->attributes['currency_snapshot'] = $value;
    }

    public function getCurrencyAttribute()
    {
        return $this->currency_snapshot;
    }

    public function setTrialStartsAtAttribute($value): void
    {
        $this->attributes['trial_started_at'] = $value;
    }

    public function getTrialStartsAtAttribute()
    {
        return $this->trial_started_at;
    }

    public function setAcceptedTermsVersionAttribute($value): void
    {
        $this->attributes['terms_version_accepted'] = $value;
    }

    public function getAcceptedTermsVersionAttribute()
    {
        return $this->terms_version_accepted;
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    public function isTrial(): bool
    {
        if ($this->status !== 'trial') {
            return false;
        }

        return $this->trial_ends_at === null || $this->trial_ends_at->isFuture();
    }

    public function isValid(): bool
    {
        return $this->isActive() || $this->isTrial();
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->status === 'active' && $this->ends_at !== null && $this->ends_at->isPast()) {
            return true;
        }

        if ($this->status === 'trial' && $this->trial_ends_at !== null && $this->trial_ends_at->isPast()) {
            return true;
        }

        return false;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
