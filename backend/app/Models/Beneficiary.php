<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'provider', 'provider_beneficiary_id', 'bank_code', 'account_number', 'account_holder', 'tax_id', 'status', 'verified_at', 'verification_payload'])]
class Beneficiary extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['verification_payload' => 'array', 'verified_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
