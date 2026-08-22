<?php
namespace App\Models;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['organization_id','cart_id','customer_id','cashier_id','reservation_id','status','payment_method','subtotal','discount_amount','total_amount','payment_reference','completed_at','metadata'])]
class PosOrder extends Model { use BelongsToTenant; protected function casts(): array { return ['subtotal'=>'integer','discount_amount'=>'integer','total_amount'=>'integer','completed_at'=>'datetime','metadata'=>'array']; } public function items(): HasMany { return $this->hasMany(PosOrderItem::class,'order_id'); } public function payments(): HasMany { return $this->hasMany(PosPayment::class,'order_id'); } }
