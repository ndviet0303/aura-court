<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'code', 'name', 'type', 'currency', 'is_system', 'status', 'metadata'])]
class Account extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'metadata' => 'array'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function entries(): HasMany { return $this->hasMany(JournalEntry::class); }
}
