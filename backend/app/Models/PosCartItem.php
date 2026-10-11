<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'cart_id', 'product_id', 'quantity', 'unit_price', 'discount_amount', 'line_total'])]
class PosCartItem extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'integer', 'discount_amount' => 'integer', 'line_total' => 'integer'];
    }
}
