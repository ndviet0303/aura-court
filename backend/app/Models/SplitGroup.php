<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'reservation_id', 'host_user_id', 'expected_amount', 'share_count', 'paid_amount', 'status', 'expires_at', 'host_covers_shortfall', 'confirmed_at'])]
class SplitGroup extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['expected_amount' => 'integer', 'paid_amount' => 'integer', 'expires_at' => 'datetime', 'host_covers_shortfall' => 'boolean', 'confirmed_at' => 'datetime']; }
    protected $appends = ['coverage_payment_attempt'];

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function host(): BelongsTo { return $this->belongsTo(User::class, 'host_user_id'); }
    public function shares(): HasMany { return $this->hasMany(SplitShare::class); }

    public function coveragePaymentAttempt(): ?OwnerPaymentAttempt
    {
        return app(\App\Services\Payment\OwnerPaymentAttemptService::class)->latestForPurpose(
            'split_share',
            'split_group:'.$this->id.':coverage'
        );
    }

    public function getCoveragePaymentAttemptAttribute(): ?array
    {
        $attempt = $this->coveragePaymentAttempt();

        return $attempt ? app(\App\Services\Payment\ReservationPaymentService::class)->instructions($attempt) : null;
    }
}
