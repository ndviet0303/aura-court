<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_tab_id',
    'product_id',
    'quantity',
    'unit_price',
    'line_total',
    'status',
])]
class ServiceTabItem extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_RELEASED = 'released';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function serviceTab(): BelongsTo
    {
        return $this->belongsTo(ServiceTab::class);
    }

    /** @return BelongsTo<PosProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }
}
