<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'cart_id',
    'customer_id',
    'cashier_id',
    'cash_shift_id',
    'venue_id',
    'register_id',
    'reservation_id',
    'status',
    'handover_status',
    'handed_over_to_shift_id',
    'payment_method',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'rounding_amount',
    'payment_reference',
    'cash_tendered',
    'change_due',
    'completed_at',
    'metadata',
])]
class PosOrder extends Model
{
    use BelongsToTenant;

    protected $appends = ['receipt', 'vat_breakdown'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'rounding_amount' => 'integer',
            'cash_tendered' => 'integer',
            'change_due' => 'integer',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function handedOverToShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'handed_over_to_shift_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function getReceiptAttribute(): ?array
    {
        return $this->metadata['receipt'] ?? null;
    }

    public function getVatBreakdownAttribute(): array
    {
        return $this->metadata['vat_breakdown'] ?? [];
    }
}
