<?php
namespace App\Models;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['organization_id','product_id','type','quantity_delta','unit_cost','reference_type','reference_id','created_by','metadata'])]
class StockMovement extends Model { use BelongsToTenant; protected function casts(): array { return ['quantity_delta'=>'integer','unit_cost'=>'integer','metadata'=>'array']; } }
