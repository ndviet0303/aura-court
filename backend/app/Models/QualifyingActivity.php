<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'user_id', 'type', 'score_delta', 'source_type', 'source_id', 'verified_by', 'qualifies_at', 'metadata'])]
class QualifyingActivity extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['score_delta' => 'integer', 'qualifies_at' => 'datetime', 'metadata' => 'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
