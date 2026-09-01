<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'organization_id', 'recurring_series_id', 'period_start', 'period_end', 'due_at', 'amount', 'status', 'payment_reference', 'paid_at', 'overdue_at', 'reminder_sent_at'])]
class MonthlyInvoice extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (MonthlyInvoice $invoice) {
            if (empty($invoice->uuid)) {
                $invoice->uuid = (string) Str::uuid7();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_at' => 'datetime',
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'overdue_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(RecurringSeries::class, 'recurring_series_id');
    }
}
