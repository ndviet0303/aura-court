<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'order_id',
    'product_id',
    'sku',
    'name',
    'quantity',
    'rental_duration_hours',
    'unit_price',
    'line_total',
    'tax_rate',
    'tax_amount',
    'metadata',
])]
class PosOrderItem extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'rental_duration_hours' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'tax_rate' => 'integer',
            'tax_amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    /** @return BelongsTo<PosProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }
}
