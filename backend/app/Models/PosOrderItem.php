<?php
namespace App\Models;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['organization_id','order_id','product_id','sku','name','quantity','unit_price','line_total'])]
class PosOrderItem extends Model { use BelongsToTenant; protected function casts(): array { return ['quantity'=>'integer','unit_price'=>'integer','line_total'=>'integer']; } }
