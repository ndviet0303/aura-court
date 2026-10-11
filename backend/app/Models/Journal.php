<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'event_type', 'event_id', 'reference', 'currency', 'ledger_entity', 'payment_model', 'total_debit', 'total_credit', 'description', 'metadata', 'posted_at'])]
class Journal extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['total_debit' => 'integer', 'total_credit' => 'integer', 'metadata' => 'array', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Financial journals are append-only.'));
        static::deleting(fn () => throw new \LogicException('Financial journals are append-only.'));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
