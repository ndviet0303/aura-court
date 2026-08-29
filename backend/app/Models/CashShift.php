<?php
namespace App\Models;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
#[Fillable(['organization_id','cashier_id','opening_cash','expected_cash','declared_cash','variance','status','opened_at','closed_at'])]
class CashShift extends Model { use BelongsToTenant; protected function casts(): array { return ['opening_cash'=>'integer','expected_cash'=>'integer','declared_cash'=>'integer','variance'=>'integer','opened_at'=>'datetime','closed_at'=>'datetime']; } public function reconciliation(): HasOne { return $this->hasOne(ShiftReconciliation::class); } public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); } }

