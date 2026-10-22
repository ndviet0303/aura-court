<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform suspension of a venue (design D3). It never changes the venue's or a court's status;
 * only an approved remediation lifts it. At most one active row per venue (partial unique index).
 */
#[Fillable(['venue_id', 'reason', 'suspended_by', 'suspended_at', 'lifted_by', 'lifted_at', 'remediation_id'])]
class VenueSuspension extends Model
{
    protected function casts(): array
    {
        return ['suspended_at' => UtcDateTime::class, 'lifted_at' => UtcDateTime::class];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /** @return BelongsTo<User, $this> */
    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    /**
     * Submissions oldest first (BR-45 history).
     *
     * @return HasMany<SuspensionRemediation, $this>
     */
    public function remediations(): HasMany
    {
        return $this->hasMany(SuspensionRemediation::class)->orderBy('id');
    }

    /**
     * The approved submission that lifted it; its review note is the decision's conclusion.
     *
     * @return BelongsTo<SuspensionRemediation, $this>
     */
    public function remediation(): BelongsTo
    {
        return $this->belongsTo(SuspensionRemediation::class, 'remediation_id');
    }

    public function isLifted(): bool
    {
        return $this->lifted_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at');
    }
}
