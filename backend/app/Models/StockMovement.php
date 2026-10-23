<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'venue_id', 'product_id', 'type', 'quantity_delta', 'unit_cost', 'reference_type', 'reference_id', 'created_by', 'metadata'])]
class StockMovement extends Model
{
    use BelongsToTenant;

    protected $appends = ['product_name', 'product_sku', 'unit', 'created_by_name', 'note', 'balance_after'];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'unit_cost' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getProductNameAttribute(): string
    {
        return $this->product?->name ?? ($this->metadata['product_name'] ?? 'Sản phẩm');
    }

    public function getProductSkuAttribute(): string
    {
        return $this->product?->sku ?? ($this->metadata['product_sku'] ?? 'SKU');
    }

    public function getUnitAttribute(): string
    {
        return $this->product?->unit ?? ($this->metadata['unit'] ?? 'item');
    }

    public function getCreatedByNameAttribute(): string
    {
        return $this->creator?->name ?? 'Admin';
    }

    public function getNoteAttribute(): ?string
    {
        return $this->metadata['note'] ?? $this->metadata['reference'] ?? null;
    }

    public function getBalanceAfterAttribute(): ?int
    {
        return isset($this->metadata['balance_after']) ? (int) $this->metadata['balance_after'] : null;
    }
}
