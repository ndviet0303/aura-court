<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'customer_id', 'reservation_id', 'status', 'offline_draft', 'client_reference', 'subtotal', 'discount_amount', 'total_amount'])]
class PosCart extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['offline_draft' => 'boolean', 'subtotal' => 'integer', 'discount_amount' => 'integer', 'total_amount' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosCartItem::class, 'cart_id');
    }
}
