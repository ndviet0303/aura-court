<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Domain\Package\Enums\PackageTerm;
use Database\Factories\PackagePaymentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A 10-minute request to pay one term of a venue package; term, price and amount are fixed at creation (D4). */
#[Fillable(['request_code', 'payment_code', 'organization_id', 'venue_id', 'kind', 'term_days', 'amount', 'package_price_id', 'expires_at', 'status', 'paid_at', 'venue_package_id', 'period_starts_at', 'period_ends_at', 'created_by'])]
class PackagePaymentRequest extends Model
{
    /** @use HasFactory<PackagePaymentRequestFactory> */
    use HasFactory;

    public const KIND_PURCHASE = 'purchase';

    public const KIND_RENEWAL = 'renewal';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'term_days' => PackageTerm::class,
            'amount' => 'integer',
            'expires_at' => UtcDateTime::class,
            'paid_at' => UtcDateTime::class,
            'period_starts_at' => UtcDateTime::class,
            'period_ends_at' => UtcDateTime::class,
        ];
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

    /** @return BelongsTo<PackagePrice, $this> */
    public function price(): BelongsTo
    {
        return $this->belongsTo(PackagePrice::class, 'package_price_id');
    }

    /** @return BelongsTo<VenuePackage, $this> */
    public function venuePackage(): BelongsTo
    {
        return $this->belongsTo(VenuePackage::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PackagePaymentReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(PackagePaymentReceipt::class);
    }
}
