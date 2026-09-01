<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency, formatDateTime } from '@/i18n'
import type { CashShiftItem } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  shifts: CashShiftItem[]
  statusFilter: string
  searchQuery: string
}>()

const emit = defineEmits<{
  (e: 'update:statusFilter', filter: any): void
  (e: 'update:searchQuery', q: string): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="shift-history-container">
    <!-- Header & Filter -->
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
          {{ t('admin.shifts.matched100') }}
        </button>
        <button
          type="button"
          class="status-btn"
          :class="{ active: statusFilter === 'exception' }"
          @click="emit('update:statusFilter', 'exception')"
        >
          {{ t('admin.shifts.hasVariance') }}
        </button>
        <button
          type="button"
          class="status-btn"
          :class="{ active: statusFilter === 'approved' }"
          @click="emit('update:statusFilter', 'approved')"
        >
          {{ t('admin.shifts.approvedReconciliation') }}
        </button>
      </div>

      <div class="search-box">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="search-input"
          :placeholder="t('admin.shifts.searchShiftsPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>

    <!-- History Table -->
    <div class="history-table-card">
      <div class="table-header">
        <span>{{ t('admin.shifts.hdrShiftAndCashier') }}</span>
        <span>{{ t('admin.shifts.hdrOperationalVenue') }}</span>
        <span>{{ t('admin.shifts.hdrShiftSales') }}</span>
        <span>{{ t('admin.shifts.hdrCountedCash') }}</span>
        <span>{{ t('admin.shifts.hdrVariance') }}</span>
        <span>{{ t('admin.shifts.hdrReconciliationStatus') }}</span>
      </div>

      <div class="table-body">
        <div
          v-for="s in shifts"
          :key="s.id"
          class="history-row"
        >
          <div class="code-col">
            <strong class="code font-mono">{{ s.code }}</strong>
            <span class="cashier">{{ s.cashier_name }} · {{ s.shift_name }}</span>
          </div>

          <div class="venue-col">
            <span>{{ resolveLocalizedText(s.venue_name) }}</span>
            <span class="time-sub font-mono">{{ s.opened_at ? formatDateTime(s.opened_at) : '' }}</span>
          </div>

          <div class="sales-col">
            <strong class="text-pink font-mono">{{ formatCurrency(s.total_sales) }}</strong>
            <span class="orders-count">{{ t('admin.shifts.ordersCountLabel', { count: s.orders_count }) }}</span>
          </div>

          <div class="cash-col">
            <strong class="font-mono">{{ formatCurrency(s.declared_cash) }}</strong>
            <span class="expected-sub">{{ t('admin.shifts.expectedSubLabel', { amount: formatCurrency(s.expected_cash) }) }}</span>
          </div>

          <div class="diff-col">
            <strong class="font-mono" :class="s.variance === 0 ? 'text-green' : s.variance < 0 ? 'text-red' : 'text-yellow'">
              {{ s.variance > 0 ? '+' : '' }}{{ formatCurrency(s.variance) }}
            </strong>
          </div>

          <div class="status-col">
            <span
              class="status-badge"
              :class="`status-${s.reconciliation_status}`"
            >
              {{
                s.reconciliation_status === 'matched'
                  ? t('admin.shifts.statusMatched')
                  : s.reconciliation_status === 'approved'
                  ? t('admin.shifts.statusApproved')
                  : t('admin.shifts.statusException')
              }}
            </span>
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
  gap: 16px;
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
  gap: 8px;
}

.status-btn {
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

.status-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.search-box {
  flex: 1;
  max-width: 320px;
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

.history-table-card {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-header {
  display: grid;
  grid-template-columns: 2fr 1.8fr 1.2fr 1.2fr 1.2fr 1.5fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.table-body {
  display: flex;
  flex-direction: column;
}

.history-row {
  display: grid;
  grid-template-columns: 2fr 1.8fr 1.2fr 1.2fr 1.2fr 1.5fr;
  padding: 14px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.history-row:last-child {
  border-bottom: 0;
}

.code-col, .venue-col, .sales-col, .cash-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cashier, .time-sub, .orders-count, .expected-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }

.status-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.status-matched, .status-approved {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-exception_short, .status-exception_over {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}
</style>
