<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'sku', 'barcode', 'name', 'description', 'price', 'cost', 'stock_quantity', 'is_active', 'metadata'])]
class PosProduct extends Model
{
    use BelongsToTenant;

    protected $appends = ['category', 'unit', 'min_stock', 'supplier', 'tax_rate', 'is_rental', 'is_service'];
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function getCategoryAttribute(): string
    {
        return $this->metadata['category'] ?? 'general';
    }
    public function getUnitAttribute(): string
    {
        return $this->metadata['unit'] ?? 'chai';
    }

    public function getMinStockAttribute(): int
    {
        return (int) ($this->metadata['min_stock'] ?? 10);
    }

    public function getSupplierAttribute(): ?string
    {
        return $this->metadata['supplier'] ?? null;
    }

    public function getTaxRateAttribute(): int
    {
        if (isset($this->metadata['tax_rate']) && is_numeric($this->metadata['tax_rate'])) {
            return (int) $this->metadata['tax_rate'];
        }
        $cat = $this->category;
        if (in_array($cat, ['drinks', 'snacks', 'nutrition'], true)) {
            return 8;
        }
        if ($cat === 'services') {
            return 10;
        }
        return 0;
    }
    public function getIsRentalAttribute(): bool
    {
        return (bool) ($this->metadata['is_rental'] ?? ($this->category === 'rental'));
    }

    public function isTracked(): bool
    {
        if (isset($this->metadata['is_service']) && (bool) $this->metadata['is_service']) {
            return false;
        }
        if (isset($this->metadata['type']) && $this->metadata['type'] === 'service') {
            return false;
        }
        if (isset($this->metadata['track_stock']) && ! (bool) $this->metadata['track_stock']) {
            return false;
        }
        if ($this->category === 'services') {
            return false;
        }
        return true;
    }

    public function getIsServiceAttribute(): bool
    {
        return ! $this->isTracked();
    }
}

