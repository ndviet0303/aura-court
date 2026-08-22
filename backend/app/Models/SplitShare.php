<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'split_group_id', 'payer_id', 'share_number', 'amount', 'paid_amount', 'payment_reference', 'status', 'expires_at', 'paid_at', 'refunded_at'])]
class SplitShare extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['amount' => 'integer', 'paid_amount' => 'integer', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'refunded_at' => 'datetime']; }
    public function group(): BelongsTo { return $this->belongsTo(SplitGroup::class, 'split_group_id'); }
    public function payer(): BelongsTo { return $this->belongsTo(User::class, 'payer_id'); }
}
