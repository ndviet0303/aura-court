<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'marketplace_listing_id', 'seller_id', 'buyer_id', 'seller_entitlement_id', 'buyer_entitlement_id', 'buyer_amount', 'marketplace_fee', 'seller_refund', 'buyer_payment_reference', 'seller_refund_reference', 'idempotency_key', 'status', 'failure_reason', 'metadata'])]
class EntitlementTransfer extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['buyer_amount' => 'integer', 'marketplace_fee' => 'integer', 'seller_refund' => 'integer', 'metadata' => 'array'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function sellerEntitlement(): BelongsTo
    {
        return $this->belongsTo(ReservationEntitlement::class, 'seller_entitlement_id');
    }

    public function buyerEntitlement(): BelongsTo
    {
        return $this->belongsTo(ReservationEntitlement::class, 'buyer_entitlement_id');
    }
}
