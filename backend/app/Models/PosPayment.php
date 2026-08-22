<?php
namespace App\Models;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['organization_id','order_id','method','amount','status','provider_reference','metadata'])]
class PosPayment extends Model { use BelongsToTenant; protected function casts(): array { return ['amount'=>'integer','metadata'=>'array']; } }
