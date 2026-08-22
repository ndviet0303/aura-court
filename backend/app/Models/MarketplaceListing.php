<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'reservation_entitlement_id', 'seller_id', 'original_price', 'asking_price', 'fee_bps', 'status', 'cutoff_at', 'sold_at', 'metadata'])]
class MarketplaceListing extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['original_price' => 'integer', 'asking_price' => 'integer', 'cutoff_at' => 'datetime', 'sold_at' => 'datetime', 'metadata' => 'array']; }
    public function entitlement(): BelongsTo { return $this->belongsTo(ReservationEntitlement::class, 'reservation_entitlement_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
    public function transfers(): HasMany { return $this->hasMany(EntitlementTransfer::class); }
}
