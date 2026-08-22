<?php
namespace App\Models;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['organization_id','cash_shift_id','variance','status','note','reviewed_by','reviewed_at'])]
class ShiftReconciliation extends Model { use BelongsToTenant; protected function casts(): array { return ['variance'=>'integer','reviewed_at'=>'datetime']; } }
