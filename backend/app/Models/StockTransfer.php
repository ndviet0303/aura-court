<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One move of a product's stock from one venue to another (pos-venue-inventory D5); its two movements reference it. */
#[Fillable(['organization_id', 'product_id', 'from_venue_id', 'to_venue_id', 'quantity', 'note', 'created_by'])]
class StockTransfer extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }

    public function fromVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'from_venue_id');
    }

    public function toVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'to_venue_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
