<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency, formatDateTime } from '@/i18n'
import { SelectDropdown, DatePicker, AuraButton, SkeletonLoader } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { Venue } from '@/features/court/types/court'
import type { CashShiftItem } from '../../composables/useAdminShiftReconciliation'

const props = defineProps<{
  shifts: CashShiftItem[]
  venues: Venue[]
  statusFilter: string
  selectedVenueId: number | 'all'
  selectedDate: string | null
  searchQuery: string
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:statusFilter', filter: any): void
  (e: 'update:selectedVenueId', id: any): void
  (e: 'update:selectedDate', date: any): void
  (e: 'update:searchQuery', q: string): void
  (e: 'openReview', shift: CashShiftItem): void
}>()

const { t } = useI18n()

const venueOptions = computed<DropdownOption[]>(() => {
  const options: DropdownOption[] = [
    { value: 'all', label: t('admin.shifts.allVenues'), icon: 'storefront' },
  ]
  props.venues.forEach((v) => {
    const label = resolveLocalizedText(v.name)
    options.push({
      value: v.id,
      label,
      icon: 'location_on',
    })
  })
  return options
})
</script>

<template>
  <div class="shift-history-container">
    <!-- Filters Toolbar -->
    <div class="history-toolbar">
      <div class="status-tabs">
        <button
          type="button"
          class="status-btn"
          :class="{ active: statusFilter === 'all' }"
          @click="emit('update:statusFilter', 'all')"
        >
          {{ t('admin.shifts.allShifts') }}
        </button>
        <button
          type="button"
          class="status-btn"
          :class="{ active: statusFilter === 'matched' }"
          @click="emit('update:statusFilter', 'matched')"
        >
          <span class="material-symbols-outlined icon-14">check_circle</span>
          <span>{{ t('admin.shifts.matched100') }}</span>
        </button>
        <button
          type="button"
          class="status-btn"
          :class="{ active: statusFilter === 'exception' }"
          @click="emit('update:statusFilter', 'exception')"
        >
          <span class="material-symbols-outlined icon-14">warning</span>
          <span>{{ t('admin.shifts.hasVariance') }}</span>
        </button>
        <button
          type="button"
          class="status-btn"
          :class="{ active: statusFilter === 'approved' }"
          @click="emit('update:statusFilter', 'approved')"
        >
          <span class="material-symbols-outlined icon-14">task_alt</span>
          <span>{{ t('admin.shifts.approvedReconciliation') }}</span>
        </button>
      </div>

      <div class="toolbar-right">
        <!-- Venue Filter Dropdown -->
        <div class="dropdown-filter-wrap">
          <SelectDropdown
            :model-value="selectedVenueId"
            :options="venueOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedVenueId', $event)"
          />
        </div>

        <!-- Date Picker Filter -->
        <div class="date-filter-wrap">
          <DatePicker
            :model-value="selectedDate || ''"
            :placeholder="t('admin.shifts.filterDateLabel')"
            @update:model-value="emit('update:selectedDate', $event || null)"
          />
        </div>

        <!-- Search Box -->
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            :value="searchQuery"
            type="text"
            class="search-input"
            :placeholder="t('admin.shifts.searchShiftsPlaceholder')"
            @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="clear-search-btn"
            @click="emit('update:searchQuery', '')"
          >
            ✕
          </button>
        </div>
      </div>
    </div>

    <!-- History Table Card -->
    <div class="history-table-card">
      <div class="table-header">
        <span>{{ t('admin.shifts.hdrShiftAndCashier') }}</span>
        <span>{{ t('admin.shifts.hdrOperationalVenue') }}</span>
        <span>{{ t('admin.shifts.hdrShiftSales') }}</span>
        <span>{{ t('admin.shifts.hdrCountedCash') }}</span>
        <span>{{ t('admin.shifts.hdrVariance') }}</span>
        <span>{{ t('admin.shifts.hdrReconciliationStatus') }}</span>
        <span class="text-right">Tác vụ</span>
      </div>

      <!-- Loading Skeletons -->
      <div v-if="loading" class="table-body">
        <div v-for="i in 5" :key="i" class="history-row-skeleton">
          <SkeletonLoader type="rect" height="32px" width="100%" />
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="shifts.length === 0" class="empty-history-box">
        <span class="material-symbols-outlined empty-table-icon">inbox</span>
        <p class="empty-history-text">{{ t('admin.shifts.emptyHistory') }}</p>
      </div>

      <!-- Rows -->
      <div v-else class="table-body">
        <div v-for="s in shifts" :key="s.id" class="history-row">
          <div class="code-col">
            <div class="code-line">
              <strong class="code font-mono">{{ s.code }}</strong>
              <span v-if="s.status === 'open'" class="live-mini-dot"
                >● {{ t('admin.shifts.openStatus') }}</span
              >
            </div>
            <span class="cashier">{{ s.cashier_name }}</span>
          </div>

          <div class="venue-col">
            <span class="venue-text">{{ resolveLocalizedText(s.venue_name) }}</span>
            <span class="time-sub font-mono">{{
              s.opened_at ? formatDateTime(s.opened_at) : ''
            }}</span>
          </div>

          <div class="sales-col">
            <strong class="text-pink font-mono">{{ formatCurrency(s.total_sales) }}</strong>
            <span class="orders-count">{{
              t('admin.shifts.ordersCountLabel', { count: s.orders_count })
            }}</span>
          </div>

          <div class="cash-col">
            <strong class="font-mono text-ink">{{ formatCurrency(s.declared_cash) }}</strong>
            <span class="expected-sub">{{
              t('admin.shifts.expectedSubLabel', { amount: formatCurrency(s.expected_cash) })
            }}</span>
          </div>

          <div class="diff-col">
            <strong
              class="font-mono"
              :class="s.variance === 0 ? 'text-green' : s.variance < 0 ? 'text-red' : 'text-yellow'"
            >
              {{ s.variance > 0 ? '+' : '' }}{{ formatCurrency(s.variance) }}
            </strong>
          </div>

          <div class="status-col">
            <span class="status-badge" :class="`status-${s.reconciliation_status}`">
              {{
                s.reconciliation_status === 'matched'
                  ? t('admin.shifts.statusMatched')
                  : s.reconciliation_status === 'approved'
                    ? t('admin.shifts.statusApproved')
                    : t('admin.shifts.statusException')
              }}
            </span>
          </div>

          <div class="action-col text-right">
            <AuraButton size="sm" variant="secondary" @click="emit('openReview', s)">
              <span class="material-symbols-outlined icon-14">visibility</span>
              <span>{{ t('admin.shifts.viewAuditBtn') }}</span>
            </AuraButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shift-history-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.history-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.status-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.status-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.status-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.status-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: min(100%, 560px);
  justify-content: flex-end;
}

.dropdown-filter-wrap {
  width: 210px;
  flex-shrink: 0;
}

.date-filter-wrap {
  width: 180px;
  flex-shrink: 0;
}

.date-filter-wrap :deep(.date-picker) {
  width: 100%;
}

.date-filter-wrap :deep(.date-picker-trigger) {
  height: 40px;
  padding: 0 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  box-sizing: border-box;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  flex: 1;
  min-width: 220px;
  box-sizing: border-box;
}

.search-icon {
  font-size: 18px;
  color: var(--muted);
  flex-shrink: 0;
}

.search-input {
  width: 100%;
  flex: 1;
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
  font-size: 12px;
  padding: 2px;
}

.history-table-card {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.table-header {
  display: grid;
  grid-template-columns: 1.8fr 1.6fr 1.2fr 1.2fr 1.1fr 1.4fr 1.2fr;
  padding: 12px 18px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  align-items: center;
}

.table-body {
  display: flex;
  flex-direction: column;
}

.history-row {
  display: grid;
  grid-template-columns: 1.8fr 1.6fr 1.2fr 1.2fr 1.1fr 1.4fr 1.2fr;
  padding: 14px 18px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
  transition: background-color 0.15s;
}

.history-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.015));
}

.history-row:last-child {
  border-bottom: 0;
}

.history-row-skeleton {
  padding: 14px 18px;
  border-bottom: 1px solid var(--outline);
}

.empty-history-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 48px 20px;
  color: var(--muted);
}

.empty-table-icon {
  font-size: 36px;
  opacity: 0.6;
}

.empty-history-text {
  margin: 0;
  font-size: 13.5px;
}

.code-col,
.venue-col,
.sales-col,
.cash-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.code-line {
  display: flex;
  align-items: center;
  gap: 6px;
}

.code {
  font-size: 13px;
  color: var(--ink);
}

.live-mini-dot {
  font-size: 10px;
  font-weight: 800;
  color: #10b981;
}

.venue-text {
  font-weight: 600;
  color: var(--ink);
}

.cashier,
.time-sub,
.orders-count,
.expected-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
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
.text-ink {
  color: var(--ink);
}
.text-right {
  text-align: right;
  justify-content: flex-end;
  display: flex;
}

.status-badge {
  display: inline-flex;
  font-size: 11px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  white-space: nowrap;
}

.status-matched {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.status-approved {
  background: rgba(139, 92, 246, 0.12);
  color: #8b5cf6;
}

.status-exception,
.status-rejected {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.icon-14 {
  font-size: 14px;
}

:global(html.dark) .history-table-card,
:global(html.dark) .search-box,
:global(html.dark) .status-btn,
:global(html.dark) .date-filter-wrap :deep(.date-picker-trigger) {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
  color: var(--ink);
}

:global(html.dark) .table-header {
  background: rgba(255, 255, 255, 0.02);
}

@media (max-width: 1024px) {
  .history-table-card {
    overflow-x: auto;
  }
  .table-header,
  .history-row {
    min-width: 900px;
  }
}
</style>
