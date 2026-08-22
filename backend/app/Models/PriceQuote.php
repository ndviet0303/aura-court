<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['organization_id', 'user_id', 'court_id', 'quote_token', 'status', 'currency', 'subtotal', 'total_amount', 'rule_version', 'slot_snapshot', 'applied_rule_ids', 'expires_at', 'accepted_at', 'accepted_snapshot'])]
class PriceQuote extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'total_amount' => 'integer',
            'rule_version' => 'integer',
            'slot_snapshot' => 'array',
            'applied_rule_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'accepted_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }
}
