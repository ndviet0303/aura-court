<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import type { InventoryProduct } from '../../types/inventory'

defineProps<{
  products: InventoryProduct[]
  categories: string[]
  search: string
  categoryFilter: string
  stockFilter: 'all' | 'low' | 'out'
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:categoryFilter', val: string): void
  (e: 'update:stockFilter', val: 'all' | 'low' | 'out'): void
  (e: 'edit', product: InventoryProduct): void
  (e: 'stockIn', product: InventoryProduct): void
  (e: 'stockOut', product: InventoryProduct): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="catalog-table-card">
    <div class="table-toolbar">
      <div class="search-input-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="search"
          type="text"
          class="search-input"
          :placeholder="t('admin.inventory.searchPlaceholder')"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="filters-group">
        <select
          :value="categoryFilter"
          class="filter-select"
          @change="emit('update:categoryFilter', ($event.target as HTMLSelectElement).value)"
        >
          <option value="all">{{ t('admin.inventory.allCategories') }}</option>
          <option v-for="c in categories.filter(x => x !== 'all')" :key="c" :value="c">
            {{ c }}
          </option>
        </select>

        <select
          :value="stockFilter"
          class="filter-select"
          @change="emit('update:stockFilter', ($event.target as HTMLSelectElement).value as any)"
        >
          <option value="all">{{ t('admin.inventory.allStockStatuses') }}</option>
          <option value="low">{{ t('admin.inventory.lowStockOption') }}</option>
          <option value="out">{{ t('admin.inventory.outOfStockOption') }}</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <div class="inventory-table">
        <div class="table-hdr">
          <span>{{ t('admin.inventory.hdrProductAndSku') }}</span>
          <span>{{ t('admin.inventory.hdrCategory') }}</span>
          <span>{{ t('admin.inventory.hdrCostAndPrice') }}</span>
          <span>{{ t('admin.inventory.hdrCurrentStock') }}</span>
          <span>{{ t('admin.inventory.hdrActions') }}</span>
        </div>

        <div class="table-body">
          <div v-for="p in products" :key="p.id" class="product-row">
            <div class="p-name-sku">
              <strong class="p-name">{{ resolveLocalizedText(p.name) }}</strong>
              <span class="p-sku font-mono">SKU: {{ p.sku }}</span>
            </div>

            <span class="p-cat-tag">{{ p.category }}</span>

            <div class="p-prices">
              <span class="cost-sub font-mono">{{ t('admin.inventory.costSubLabel', { amount: formatCurrency(p.cost) }) }}</span>
              <strong class="price-val text-pink font-mono">{{ formatCurrency(p.price) }}</strong>
            </div>

            <div class="p-stock-info">
              <strong class="stock-qty font-mono" :class="p.stock_quantity <= p.min_stock ? 'text-red' : 'text-green'">
                {{ p.stock_quantity }} {{ p.unit }}
              </strong>
              <span v-if="p.stock_quantity <= p.min_stock" class="min-alert">
                <span class="material-symbols-outlined icon-14">warning</span>
                <span>{{ t('admin.inventory.belowMinAlert', { min: p.min_stock }) }}</span>
              </span>
            </div>

            <div class="row-actions">
              <button type="button" class="btn-sm" :title="t('admin.inventory.stockInBtn')" @click="emit('stockIn', p)">
                <span class="material-symbols-outlined icon-14">add</span>
                <span>{{ t('admin.inventory.stockInBtn') }}</span>
              </button>
              <button type="button" class="btn-sm" :title="t('admin.inventory.stockOutBtn')" @click="emit('stockOut', p)">
                <span class="material-symbols-outlined icon-14">remove</span>
                <span>{{ t('admin.inventory.stockOutBtn') }}</span>
              </button>
              <button type="button" class="btn-sm" :title="t('admin.inventory.editProduct')" @click="emit('edit', p)">
                <span class="material-symbols-outlined icon-14">edit</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.catalog-table-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input-wrap {
  flex: 1;
  min-width: 240px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.search-icon {
  font-size: 18px;
  color: var(--muted);
}

.search-input {
  width: 100%;
  border: none;
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  outline: none;
}

.filters-group {
  display: flex;
  gap: 10px;
}

.filter-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.inventory-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
  min-width: 800px;
}

.table-hdr {
  display: grid;
  grid-template-columns: 2fr 1.2fr 1.5fr 1.5fr 1.5fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.product-row {
  display: grid;
  grid-template-columns: 2fr 1.2fr 1.5fr 1.5fr 1.5fr;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.product-row:last-child {
  border-bottom: 0;
}

.p-name-sku {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.p-name { font-size: 13px; }
.p-sku { font-size: 11px; color: var(--muted); }

.p-cat-tag {
  font-size: 11px;
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  width: fit-content;
}

.p-prices {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cost-sub { font-size: 11px; color: var(--muted); }
.price-val { font-size: 13px; }
.text-pink { color: var(--pink-dark, #db2777); }

.p-stock-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stock-qty { font-size: 14px; }
.text-green { color: #10b981; }
.text-red { color: #ef4444; }

.min-alert {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  color: #ef4444;
}

.row-actions {
  display: flex;
  gap: 6px;
}

.btn-sm {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-sm:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.icon-14 {
  font-size: 14px;
}
</style>
