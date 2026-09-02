<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatDateTime } from '@/i18n'
import { SelectDropdown, DatePicker, SkeletonLoader, AuraButton } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { StockMovement, InventoryProduct } from '../../types/inventory'

const props = defineProps<{
  movements: StockMovement[]
  products?: InventoryProduct[]
  typeFilter: 'all' | 'in' | 'out' | 'adjust' | 'sale' | 'return'
  productFilter?: number | 'all'
  dateFrom?: string
  dateTo?: string
  search?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:typeFilter', val: 'all' | 'in' | 'out' | 'adjust' | 'sale' | 'return'): void
  (e: 'update:productFilter', val: number | 'all'): void
  (e: 'update:dateFrom', val: string): void
  (e: 'update:dateTo', val: string): void
  (e: 'update:search', val: string): void
  (e: 'resetFilters'): void
}>()

const { t } = useI18n()

const typeOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.inventory.allMovementTypes'), icon: 'list_alt' },
  { value: 'in', label: t('admin.inventory.typeInOption'), icon: 'move_to_inbox' },
  { value: 'out', label: t('admin.inventory.typeOutOption'), icon: 'unarchive' },
  { value: 'adjust', label: t('admin.inventory.typeAdjustOption'), icon: 'tune' },
])

const productOptions = computed<DropdownOption[]>(() => {
  const list: DropdownOption[] = [
    { value: 'all', label: t('admin.inventory.allProductsFilter'), icon: 'inventory_2' },
  ]
  if (props.products) {
    props.products.forEach((p) => {
      list.push({
        value: p.id,
        label: `${p.name} (${p.sku})`,
        icon: 'package_2',
      })
    })
  }
  return list
})

const hasActiveFilters = computed(() => {
  return Boolean(
    (props.typeFilter && props.typeFilter !== 'all') ||
    (props.productFilter && props.productFilter !== 'all') ||
    props.dateFrom ||
    props.dateTo ||
    props.search
  )
})

function getTypeBadgeClass(type: string): string {
  if (type === 'in' || type === 'receipt') return 'type-in'
  if (type === 'out' || type === 'disposal') return 'type-out'
  if (type === 'adjust' || type === 'adjustment') return 'type-adjust'
  if (type === 'sale') return 'type-sale'
  return 'type-default'
}

function getTypeIcon(type: string): string {
  if (type === 'in' || type === 'receipt') return 'arrow_downward'
  if (type === 'out' || type === 'disposal') return 'arrow_upward'
  if (type === 'adjust' || type === 'adjustment') return 'sync'
  if (type === 'sale') return 'shopping_bag'
  return 'swap_vert'
}

function getTypeLabel(type: string): string {
  if (type === 'in' || type === 'receipt') return t('admin.inventory.badgeIn')
  if (type === 'out' || type === 'disposal') return t('admin.inventory.badgeOut')
  if (type === 'adjust' || type === 'adjustment') return t('admin.inventory.badgeAdjust')
  if (type === 'sale') return t('admin.inventory.badgeSale')
  return type.toUpperCase()
}

function isPositive(m: StockMovement): boolean {
  if (m.type === 'in' || m.type === 'receipt') return true
  if (m.quantity_delta && m.quantity_delta > 0) return true
  return false
}
</script>

<template>
  <div class="movements-table-card">
    <div class="table-toolbar">
      <div class="toolbar-left">
        <span class="material-symbols-outlined card-title-icon">history</span>
        <h3 class="title">{{ t('admin.inventory.movementsTitle') }}</h3>
      </div>

      <div class="filters-wrap">
        <div class="search-input-wrap">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            :value="search"
            type="text"
            class="search-input"
            :placeholder="t('admin.inventory.searchMovementsPlaceholder')"
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

        <div class="dropdown-filter-item">
          <SelectDropdown
            :model-value="typeFilter"
            :options="typeOptions"
            variant="bordered"
            @update:model-value="emit('update:typeFilter', $event as any)"
          />
        </div>

        <div v-if="products && products.length > 0" class="dropdown-filter-item">
          <SelectDropdown
            :model-value="productFilter || 'all'"
            :options="productOptions"
            variant="bordered"
            @update:model-value="emit('update:productFilter', $event as any)"
          />
        </div>

        <div class="date-filter-item">
          <DatePicker
            :model-value="dateFrom || ''"
            :placeholder="t('admin.inventory.dateFromPlaceholder')"
            @update:model-value="emit('update:dateFrom', $event)"
          />
        </div>

        <div class="date-filter-item">
          <DatePicker
            :model-value="dateTo || ''"
            :placeholder="t('admin.inventory.dateToPlaceholder')"
            @update:model-value="emit('update:dateTo', $event)"
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

    <!-- Table Responsive Container -->
    <div class="table-responsive">
      <div class="movements-table">
        <div class="table-hdr">
          <span class="col-time">{{ t('admin.inventory.hdrTimestamp') }}</span>
          <span class="col-prod">{{ t('admin.inventory.hdrProduct') }}</span>
          <span class="col-type">{{ t('admin.inventory.hdrTransactionType') }}</span>
          <span class="col-qty">{{ t('admin.inventory.hdrQuantity') }}</span>
          <span class="col-balance">{{ t('admin.inventory.hdrStockBalance') }}</span>
          <span class="col-meta">{{ t('admin.inventory.hdrNoteAndPerformer') }}</span>
        </div>

        <!-- Skeleton Loading -->
        <div v-if="loading" class="table-body">
          <div v-for="i in 6" :key="i" class="movement-row skeleton-row">
            <div class="col-time">
              <SkeletonLoader type="text" width="100px" height="14px" />
            </div>
            <div class="col-prod flex-col-gap">
              <SkeletonLoader type="text" width="140px" height="15px" />
              <SkeletonLoader type="text" width="80px" height="12px" />
            </div>
            <div class="col-type">
              <SkeletonLoader type="text" width="90px" height="22px" rounded="6px" />
            </div>
            <div class="col-qty">
              <SkeletonLoader type="text" width="50px" height="16px" />
            </div>
            <div class="col-balance">
              <SkeletonLoader type="text" width="60px" height="15px" />
            </div>
            <div class="col-meta flex-col-gap">
              <SkeletonLoader type="text" width="160px" height="14px" />
              <SkeletonLoader type="text" width="80px" height="12px" />
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="movements.length === 0" class="empty-state">
          <span class="material-symbols-outlined empty-icon">history</span>
          <h4 class="empty-title">{{ t('common.noData') }}</h4>
          <p class="empty-desc">
            {{ hasActiveFilters ? t('admin.inventory.noMatchingMovements') : t('admin.inventory.noMovementsRecordedYet') }}
          </p>
          <AuraButton v-if="hasActiveFilters" variant="outline" size="sm" @click="emit('resetFilters')">
            {{ t('common.reset') }}
          </AuraButton>
        </div>

        <!-- Actual Movements Rows -->
        <div v-else class="table-body">
          <div v-for="m in movements" :key="m.id" class="movement-row">
            <!-- Timestamp -->
            <div class="col-time">
              <span class="font-mono time-val">{{ m.created_at ? formatDateTime(m.created_at) : '' }}</span>
            </div>

            <!-- Product info -->
            <div class="col-prod p-info-cell">
              <strong class="p-name">{{ resolveLocalizedText(m.product_name) }}</strong>
              <span class="p-sku font-mono">SKU: {{ m.product_sku }}</span>
            </div>

            <!-- Type badge -->
            <div class="col-type">
              <span class="type-badge" :class="getTypeBadgeClass(m.type)">
                <span class="material-symbols-outlined icon-12">{{ getTypeIcon(m.type) }}</span>
                <span>{{ getTypeLabel(m.type) }}</span>
              </span>
            </div>

            <!-- Quantity Delta -->
            <div class="col-qty">
              <strong class="qty-val font-mono" :class="isPositive(m) ? 'text-green' : 'text-pink'">
                {{ isPositive(m) ? `+${m.quantity}` : `-${m.quantity}` }}
                <span class="unit-sub">{{ m.unit }}</span>
              </strong>
            </div>

            <!-- Balance After -->
            <div class="col-balance">
              <span v-if="m.balance_after !== null && m.balance_after !== undefined" class="balance-val font-mono">
                {{ m.balance_after }} {{ m.unit }}
              </span>
              <span v-else class="text-muted font-mono">-</span>
            </div>

            <!-- Note & Performer -->
            <div class="col-meta note-user">
              <span class="note-text">{{ m.note || t('admin.inventory.noNote') }}</span>
              <div class="meta-sub-row">
                <span v-if="m.reference_code" class="ref-code font-mono">#{{ m.reference_code }}</span>
                <span class="user-sub">{{ t('admin.inventory.performerSub', { user: m.created_by_name || 'Admin' }) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.movements-table-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.filters-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.search-input-wrap {
  min-width: 220px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 42px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-input-wrap:focus-within {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
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

.clear-search-btn {
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font-size: 13px;
  padding: 2px;
}

.dropdown-filter-item {
  width: 190px;
}

.date-filter-item {
  width: 150px;
}

.reset-filters-btn {
  height: 42px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.movements-table {
  display: flex;
  flex-direction: column;
  border-radius: 14px;
  border: 1px solid var(--outline);
  overflow: hidden;
  min-width: 900px;
  background: var(--surface);
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.4fr 2fr 1.3fr 1.2fr 1.2fr 2.4fr;
  padding: 14px 18px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.table-body {
  display: flex;
  flex-direction: column;
}

.movement-row {
  display: grid;
  grid-template-columns: 1.4fr 2fr 1.3fr 1.2fr 1.2fr 2.4fr;
  padding: 14px 18px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13.5px;
  transition: background 0.15s ease;
}

.movement-row:last-child {
  border-bottom: none;
}

.movement-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.015));
}

.time-val {
  font-size: 12px;
  color: var(--muted);
}

.p-info-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.p-name {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
}

.p-sku {
  font-size: 11px;
  color: var(--muted);
}

.type-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10.5px;
  font-weight: 800;
  padding: 4px 9px;
  border-radius: 6px;
  width: fit-content;
  letter-spacing: 0.03em;
}

.type-in { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.type-out { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.type-adjust { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.type-sale { background: rgba(219, 39, 119, 0.12); color: var(--pink-dark, #db2777); }
.type-default { background: rgba(148, 163, 184, 0.12); color: var(--muted); }

.qty-val {
  font-size: 15px;
  font-weight: 800;
}

.unit-sub {
  font-size: 11px;
  font-weight: 500;
  color: var(--muted);
  margin-left: 2px;
}

.text-green { color: #10b981; }
.text-pink { color: var(--pink-dark, #db2777); }
.text-muted { color: var(--muted); }

.balance-val {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
}

.note-user {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.note-text {
  font-size: 12.5px;
  color: var(--ink);
}

.meta-sub-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ref-code {
  font-size: 11px;
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.08);
  padding: 1px 6px;
  border-radius: 4px;
}

.user-sub {
  font-size: 11px;
  color: var(--muted);
}

.icon-12 { font-size: 12px; }
.icon-16 { font-size: 16px; }

.flex-col-gap { display: flex; flex-direction: column; gap: 4px; }

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
