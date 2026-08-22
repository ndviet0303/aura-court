<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journal_id', 'organization_id', 'account_id', 'side', 'amount', 'line_number', 'source_type', 'source_id', 'metadata'])]
class JournalEntry extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Journal entries are append-only.'));
        static::deleting(fn () => throw new \LogicException('Journal entries are append-only.'));
    }

    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}
