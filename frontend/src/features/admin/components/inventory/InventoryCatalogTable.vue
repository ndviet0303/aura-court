<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import { SelectDropdown, SkeletonLoader, AuraButton } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { InventoryProduct } from '../../types/inventory'

const props = defineProps<{
  products: InventoryProduct[]
  categories: string[]
  search: string
  categoryFilter: string
  stockFilter: 'all' | 'low' | 'out'
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:categoryFilter', val: string): void
  (e: 'update:stockFilter', val: 'all' | 'low' | 'out'): void
  (e: 'edit', product: InventoryProduct): void
  (e: 'stockIn', product: InventoryProduct): void
  (e: 'stockOut', product: InventoryProduct): void
  (e: 'delete', product: InventoryProduct): void
  (e: 'resetFilters'): void
  (e: 'addProduct'): void
}>()

const { t } = useI18n()

const categoryOptions = computed<DropdownOption[]>(() => {
  const list: DropdownOption[] = [
    { value: 'all', label: t('admin.inventory.allCategories'), icon: 'apps' },
  ]
  const knownIcons: Record<string, string> = {
    drinks: 'local_drink',
    equipment: 'sports_tennis',
    accessories: 'sports_score',
    snacks: 'bakery_dining',
    nutrition: 'bakery_dining',
    rental: 'swap_horizontal_circle',
    services: 'room_service',
  }

  props.categories
    .filter((c) => c !== 'all')
    .forEach((cat) => {
      list.push({
        value: cat,
        label: getCategoryOptionLabel(cat),
        icon: knownIcons[cat] || 'category',
      })
    })

  return list
})

const stockOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.inventory.allStockStatuses'), icon: 'inventory' },
  { value: 'low', label: t('admin.inventory.lowStockOption'), icon: 'warning' },
  { value: 'out', label: t('admin.inventory.outOfStockOption'), icon: 'error' },
])

const hasActiveFilters = computed(() => {
  return Boolean(props.search || props.categoryFilter !== 'all' || props.stockFilter !== 'all')
})

function getCategoryIcon(cat: string): string {
  const map: Record<string, string> = {
    drinks: 'local_drink',
    equipment: 'sports_tennis',
    accessories: 'sports_score',
    snacks: 'bakery_dining',
    nutrition: 'bakery_dining',
    rental: 'swap_horizontal_circle',
    services: 'room_service',
  }
  return map[cat] || 'package_2'
}

function getCategoryOptionLabel(cat: string): string {
  if (cat === 'drinks') return t('admin.inventory.catDrinksOption')
  if (cat === 'equipment') return t('admin.inventory.catEquipmentOption')
  if (cat === 'accessories') return t('admin.inventory.catAccessoriesOption')
  if (cat === 'snacks' || cat === 'nutrition') return t('admin.inventory.catSnacksOption')
  if (cat === 'rental') return t('admin.inventory.catRentalOption')
  if (cat === 'services') return t('admin.inventory.catServicesOption')
  return cat
}

function getCategoryShortLabel(cat: string): string {
  if (cat === 'drinks') return t('admin.inventory.catDrinksShort')
  if (cat === 'equipment') return t('admin.inventory.catEquipmentShort')
  if (cat === 'accessories') return t('admin.inventory.catAccessoriesShort')
  if (cat === 'snacks' || cat === 'nutrition') return t('admin.inventory.catSnacksShort')
  if (cat === 'rental') return t('admin.inventory.catRentalShort')
  if (cat === 'services') return t('admin.inventory.catServicesShort')
  return cat
}

function getCategoryThemeClass(cat: string): string {
  if (cat === 'drinks') return 'cat-drinks'
  if (cat === 'equipment') return 'cat-equipment'
  if (cat === 'accessories') return 'cat-accessories'
  if (cat === 'snacks' || cat === 'nutrition') return 'cat-snacks'
  if (cat === 'rental') return 'cat-rental'
  if (cat === 'services') return 'cat-services'
  return 'cat-default'
}

function calculateMargin(price: number, cost: number): number {
  if (!price || price <= 0) return 0
  return Math.round(((price - cost) / price) * 100)
}
</script>

<template>
  <div class="catalog-table-card">
    <!-- Toolbar -->
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
        <button
          v-if="search"
          type="button"
          class="clear-search-btn"
          @click="emit('update:search', '')"
        >
          ✕
        </button>
      </div>

      <div class="filters-group">
        <div class="dropdown-filter-item">
          <SelectDropdown
            :model-value="categoryFilter"
            :options="categoryOptions"
            variant="bordered"
            @update:model-value="emit('update:categoryFilter', String($event))"
          />
        </div>

        <div class="dropdown-filter-item">
          <SelectDropdown
            :model-value="stockFilter"
            :options="stockOptions"
            variant="bordered"
            @update:model-value="emit('update:stockFilter', $event as any)"
          />
        </div>

        <AuraButton
          v-if="hasActiveFilters"
          variant="outline"
          size="sm"
          class="reset-filters-btn"
          @click="emit('resetFilters')"
        >
          <span class="material-symbols-outlined icon-16">filter_alt_off</span>
          <span>{{ t('common.reset') }}</span>
        </AuraButton>
      </div>
    </div>

    <!-- Table content with Skeletons -->
    <div class="table-responsive">
      <div class="inventory-table">
        <!-- Table Header -->
        <div class="table-hdr">
          <span class="col-prod">{{ t('admin.inventory.hdrProductAndSku') }}</span>
          <span class="col-cat">{{ t('admin.inventory.hdrCategory') }}</span>
          <span class="col-price">{{ t('admin.inventory.hdrCostAndPrice') }}</span>
          <span class="col-stock">{{ t('admin.inventory.hdrCurrentStock') }}</span>
          <span class="col-actions text-right">{{ t('admin.inventory.hdrActions') }}</span>
        </div>

        <!-- Skeleton Loading Rows -->
        <div v-if="loading" class="table-body">
          <div v-for="i in 5" :key="i" class="product-row skeleton-row">
            <div class="col-prod flex-center-gap">
              <SkeletonLoader type="avatar" width="38px" height="38px" rounded="10px" />
              <div class="flex-col-gap">
                <SkeletonLoader type="text" width="160px" height="15px" />
                <SkeletonLoader type="text" width="90px" height="12px" />
              </div>
            </div>
            <div class="col-cat">
              <SkeletonLoader type="text" width="80px" height="22px" rounded="6px" />
            </div>
            <div class="col-price flex-col-gap">
              <SkeletonLoader type="text" width="80px" height="14px" />
              <SkeletonLoader type="text" width="60px" height="11px" />
            </div>
            <div class="col-stock flex-col-gap">
              <SkeletonLoader type="text" width="70px" height="14px" />
              <SkeletonLoader type="text" width="50px" height="11px" />
            </div>
            <div class="col-actions flex-end-gap">
              <SkeletonLoader type="text" width="60px" height="28px" rounded="8px" />
              <SkeletonLoader type="text" width="60px" height="28px" rounded="8px" />
              <SkeletonLoader type="text" width="28px" height="28px" rounded="8px" />
              <SkeletonLoader type="text" width="28px" height="28px" rounded="8px" />
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="products.length === 0" class="empty-state">
          <span class="material-symbols-outlined empty-icon">inventory_2</span>
          <h4 class="empty-title">{{ t('common.noData') }}</h4>
          <p class="empty-desc">
            {{
              hasActiveFilters
                ? t('admin.inventory.noMatchingProducts')
                : t('admin.inventory.noProductsCreatedYet')
            }}
          </p>
          <AuraButton
            v-if="hasActiveFilters"
            variant="outline"
            size="sm"
            @click="emit('resetFilters')"
          >
            {{ t('common.reset') }}
          </AuraButton>
          <AuraButton v-else variant="primary" size="sm" @click="emit('addProduct')">
            <span class="material-symbols-outlined icon-16">add_box</span>
            <span>{{ t('admin.inventory.addProduct') }}</span>
          </AuraButton>
        </div>

        <!-- Actual Products Rows -->
        <div v-else class="table-body">
          <div v-for="p in products" :key="p.id" class="product-row">
            <!-- 1. Product Avatar & Info -->
            <div class="col-prod p-name-sku">
              <div class="p-avatar-box" :class="getCategoryThemeClass(p.category)">
                <span class="material-symbols-outlined p-avatar-icon">{{
                  getCategoryIcon(p.category)
                }}</span>
              </div>
              <div class="p-text-meta">
                <strong class="p-name" :title="p.name">{{ resolveLocalizedText(p.name) }}</strong>
                <div class="p-sub-meta">
                  <span class="p-sku font-mono">SKU: {{ p.sku }}</span>
                  <span v-if="p.barcode" class="p-barcode font-mono">· {{ p.barcode }}</span>
                </div>
              </div>
            </div>

            <!-- 2. Category Pill -->
            <div class="col-cat">
              <span class="p-cat-tag" :class="getCategoryThemeClass(p.category)">
                <span class="material-symbols-outlined icon-14">{{
                  getCategoryIcon(p.category)
                }}</span>
                <span>{{ getCategoryShortLabel(p.category) }}</span>
              </span>
            </div>

            <!-- 3. Cost & Retail Price -->
            <div class="col-price p-prices">
              <div class="price-main-row">
                <strong class="price-val font-mono">{{ formatCurrency(p.price) }}</strong>
                <span v-if="p.price > p.cost" class="margin-badge font-mono"
                  >+{{ calculateMargin(p.price, p.cost) }}%</span
                >
                <span class="vat-badge font-mono">VAT {{ p.tax_rate ?? 8 }}%</span>
              </div>
              <span class="cost-sub font-mono">{{
                t('admin.inventory.costSubLabel', { amount: formatCurrency(p.cost) })
              }}</span>
            </div>

            <!-- 4. Stock Info & Status Alert -->
            <div class="col-stock p-stock-info">
              <div class="stock-qty-row">
                <strong
                  class="stock-qty font-mono"
                  :class="
                    p.stock_quantity === 0
                      ? 'text-red'
                      : p.stock_quantity <= p.min_stock
                        ? 'text-yellow'
                        : 'text-green'
                  "
                >
                  {{ p.stock_quantity }} {{ p.unit }}
                </strong>
                <span
                  class="stock-status-pill"
                  :class="
                    p.stock_quantity === 0
                      ? 'pill-out'
                      : p.stock_quantity <= p.min_stock
                        ? 'pill-low'
                        : 'pill-in'
                  "
                >
                  ●
                  {{
                    p.stock_quantity === 0
                      ? t('admin.inventory.outOfStockOption')
                      : p.stock_quantity <= p.min_stock
                        ? t('admin.inventory.lowStockOption')
                        : t('admin.inventory.inStockOption')
                  }}
                </span>
              </div>

              <span
                v-if="p.stock_quantity <= p.min_stock && p.stock_quantity > 0"
                class="min-alert"
              >
                <span class="material-symbols-outlined icon-12">warning</span>
                <span>{{ t('admin.inventory.belowMinAlert', { min: p.min_stock }) }}</span>
              </span>
            </div>

            <!-- 5. Actions -->
            <div class="col-actions row-actions">
              <button
                type="button"
                class="btn-action btn-in"
                :title="t('admin.inventory.stockInBtn')"
                @click="emit('stockIn', p)"
              >
                <span class="material-symbols-outlined icon-14">add</span>
                <span class="btn-text">{{ t('admin.inventory.stockInBtn') }}</span>
              </button>

              <button
                type="button"
                class="btn-action btn-out"
                :title="t('admin.inventory.stockOutBtn')"
                @click="emit('stockOut', p)"
              >
                <span class="material-symbols-outlined icon-14">remove</span>
                <span class="btn-text">{{ t('admin.inventory.stockOutBtn') }}</span>
              </button>

              <button
                type="button"
                class="btn-icon-action"
                :title="t('admin.inventory.editProduct')"
                @click="emit('edit', p)"
              >
                <span class="material-symbols-outlined icon-15">edit</span>
              </button>

              <button
                type="button"
                class="btn-icon-action btn-danger"
                :title="t('admin.inventory.deleteProduct')"
                @click="emit('delete', p)"
              >
                <span class="material-symbols-outlined icon-15">delete</span>
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
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline);
  flex-wrap: wrap;
}

.search-input-wrap {
  position: relative;
  flex: 1;
  min-width: 260px;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 14px;
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.search-input {
  width: 100%;
  height: 40px;
  padding: 0 36px 0 40px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.025));
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.search-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
  background: var(--surface);
}

.clear-search-btn {
  position: absolute;
  right: 10px;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font-size: 12px;
  padding: 4px;
}

.filters-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.dropdown-filter-item {
  width: 190px;
}

.reset-filters-btn {
  height: 40px;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.inventory-table {
  min-width: 880px;
  width: 100%;
}

.table-hdr {
  display: grid;
  grid-template-columns: 2.4fr 1.1fr 1.5fr 1.3fr 190px;
  padding: 12px 20px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.025));
  border-bottom: 1px solid var(--outline);
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  align-items: center;
}

.table-body {
  display: flex;
  flex-direction: column;
}

.product-row {
  display: grid;
  grid-template-columns: 2.4fr 1.1fr 1.5fr 1.3fr 190px;
  padding: 13px 20px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13.5px;
  transition: background 0.15s ease;
}

.product-row:last-child {
  border-bottom: none;
}

.product-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

/* 1. Product & SKU column */
.p-name-sku {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.p-avatar-box {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.p-avatar-icon {
  font-size: 20px;
}

.p-text-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.p-name {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.p-sub-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.p-sku {
  font-weight: 600;
  color: var(--ink-secondary, var(--ink));
  opacity: 0.85;
}

/* 2. Category Badges (Themed pastel & dark-compatible pills) */
.p-cat-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  font-weight: 600;
  padding: 3.5px 9px;
  border-radius: 999px;
  white-space: nowrap;
  width: fit-content;
  border: 1px solid transparent;
}

/* Category Themes */
.cat-drinks {
  background: rgba(59, 130, 246, 0.12);
  color: #3b82f6;
  border-color: rgba(59, 130, 246, 0.2);
}
.cat-equipment {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
  border-color: rgba(245, 158, 11, 0.2);
}
.cat-accessories {
  background: rgba(168, 85, 247, 0.12);
  color: #a855f7;
  border-color: rgba(168, 85, 247, 0.2);
}
.cat-snacks {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border-color: rgba(16, 185, 129, 0.2);
}
.cat-rental {
  background: rgba(6, 182, 212, 0.12);
  color: #06b6d4;
  border-color: rgba(6, 182, 212, 0.2);
}
.cat-services {
  background: rgba(236, 72, 153, 0.12);
  color: #ec4899;
  border-color: rgba(236, 72, 153, 0.2);
}
.cat-default {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  color: var(--ink);
  border-color: var(--outline);
}

/* 3. Cost & Price column */
.p-prices {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.price-main-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.price-val {
  font-size: 14px;
  font-weight: 800;
  color: var(--pink-dark, #ec4899);
}

.margin-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 1.5px 5px;
  border-radius: 4px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.vat-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 1.5px 5px;
  border-radius: 4px;
  background: rgba(59, 130, 246, 0.12);
  color: #3b82f6;
  border: 1px solid rgba(59, 130, 246, 0.2);
}

.cost-sub {
  font-size: 11.5px;
  color: var(--muted);
}

/* 4. Stock Info column */
.p-stock-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.stock-qty-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.stock-qty {
  font-size: 14px;
  font-weight: 800;
}

.text-green {
  color: #10b981;
}
.text-yellow {
  color: #f59e0b;
}
.text-red {
  color: #ef4444;
}

.stock-status-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  white-space: nowrap;
}

.pill-in {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}
.pill-low {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}
.pill-out {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.min-alert {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  color: #ef4444;
  font-weight: 500;
}

/* 5. Row Actions */
.row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  height: 30px;
  padding: 0 8px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(255, 255, 255, 0.04));
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-action:hover {
  background: var(--surface);
}

.btn-in:hover {
  border-color: rgba(16, 185, 129, 0.5);
  color: #10b981;
  background: rgba(16, 185, 129, 0.08);
}

.btn-out:hover {
  border-color: rgba(245, 158, 11, 0.5);
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.08);
}

.btn-icon-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(255, 255, 255, 0.04));
  color: var(--ink-secondary, var(--ink));
  cursor: pointer;
  transition: all 0.15s ease;
  padding: 0;
}

.btn-icon-action:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.08);
}

.btn-icon-action.btn-danger:hover {
  border-color: #ef4444;
  color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}

.icon-12 {
  font-size: 12px;
}
.icon-14 {
  font-size: 14px;
}
.icon-15 {
  font-size: 15px;
}
.icon-16 {
  font-size: 16px;
}
.text-right {
  text-align: right;
}

.flex-center-gap {
  display: flex;
  align-items: center;
  gap: 10px;
}
.flex-col-gap {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.flex-end-gap {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  gap: 8px;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted);
  opacity: 0.5;
  margin-bottom: 4px;
}

.empty-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
}

.empty-desc {
  margin: 0 0 12px;
  font-size: 13px;
  color: var(--muted);
  max-width: 320px;
}
</style>
