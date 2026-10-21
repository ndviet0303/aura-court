<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Domain\Package\Enums\PackageStatus;
use App\Domain\Package\Enums\PackageTerm;
use Carbon\CarbonInterface;
use Database\Factories\VenuePackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One purchase of a venue package; a renewal moves ends_at of the same row (D1). */
#[Fillable(['organization_id', 'venue_id', 'term_days', 'starts_at', 'ends_at', 'terminated_at', 'terminated_by'])]
class VenuePackage extends Model
{
    /** @use HasFactory<VenuePackageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'term_days' => PackageTerm::class,
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'terminated_at' => UtcDateTime::class,
        ];
    }

    public function statusAt(CarbonInterface $at): PackageStatus
    {
        if ($this->terminated_at !== null) {
            return PackageStatus::Terminated;
        }

        return $this->starts_at->lte($at) && $at->lt($this->ends_at) ? PackageStatus::Active : PackageStatus::Expired;
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function terminator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }
}
