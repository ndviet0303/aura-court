<?php
namespace App\Models;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['organization_id','sku','barcode','name','description','price','cost','stock_quantity','is_active','metadata'])]
class PosProduct extends Model { use BelongsToTenant; protected function casts(): array { return ['price'=>'integer','cost'=>'integer','stock_quantity'=>'integer','is_active'=>'boolean','metadata'=>'array']; } public function movements(): HasMany { return $this->hasMany(StockMovement::class,'product_id'); } }
