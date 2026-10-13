<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'user_id', 'court_id', 'frequency', 'starts_at', 'ends_at', 'series_ends_at', 'occurrence_count', 'max_occurrences', 'status', 'total_amount', 'next_invoice_due_at', 'metadata'])]
class RecurringSeries extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'series_ends_at' => 'datetime', 'total_amount' => 'integer', 'next_invoice_due_at' => 'datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** @return HasMany<RecurringOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringOccurrence::class);
    }

    /** @return HasMany<MonthlyInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(MonthlyInvoice::class);
    }
}
