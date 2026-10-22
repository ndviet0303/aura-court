<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Domain\Package\Enums\PackageTerm;
use Database\Factories\PackagePriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A term's price from effective_at; a later price for the same term sets replaced_at on scheduled ones (D3). */
#[Fillable(['term_days', 'amount', 'effective_at', 'replaced_at', 'created_by'])]
class PackagePrice extends Model
{
    /** @use HasFactory<PackagePriceFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'term_days' => PackageTerm::class,
            'amount' => 'integer',
            'effective_at' => UtcDateTime::class,
            'replaced_at' => UtcDateTime::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
