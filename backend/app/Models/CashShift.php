<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'venue_id',
    'register_id',
    'cashier_id',
    'opening_cash',
    'expected_cash',
    'declared_cash',
    'variance',
    'status',
    'opened_at',
    'closed_at',
    'cash_movements',
])]
class CashShift extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'opening_cash' => 'integer',
            'expected_cash' => 'integer',
            'declared_cash' => 'integer',
            'variance' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'cash_movements' => 'array',
        ];
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(ShiftReconciliation::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'cash_shift_id');
    }
}
