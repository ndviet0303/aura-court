<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'cash_shift_id', 'variance', 'status', 'note', 'reviewed_by', 'reviewed_at'])]
class ShiftReconciliation extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'variance' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }
}
