<?php
namespace App\Models;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['organization_id','name','version','earn_points_per_100_vnd','redeem_points_per_100_vnd','status','published_at'])]
class LoyaltyRule extends Model { use BelongsToTenant; protected function casts(): array { return ['version'=>'integer','earn_points_per_100_vnd'=>'integer','redeem_points_per_100_vnd'=>'integer','published_at'=>'datetime']; } }
