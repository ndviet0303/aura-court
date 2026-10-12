<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown, DatePicker, type DropdownOption } from '@/shared/components'
import type { SettlementRecord } from '../../types/finance'
import {
  getProviderFee,
  getHistoricalCommission,
  getRefundAmount,
  getTotalDeduction,
  getNetPayable,
} from '../../composables/useAdminPayouts'

withDefaults(
  defineProps<{
    settlements: SettlementRecord[]
    loading?: boolean
    searchQuery?: string
    selectedStatusFilter?: string
    selectedDateFilter?: string
  }>(),
  {
    loading: false,
    searchQuery: '',
    selectedStatusFilter: 'all',
    selectedDateFilter: '',
  },
)

const emit = defineEmits<{
  (e: 'update:searchQuery', val: string): void
  (e: 'update:selectedStatusFilter', val: string): void
  (e: 'update:selectedDateFilter', val: string): void
  (e: 'createPayout', settlement: SettlementRecord): void
}>()

const { t } = useI18n()

const statusOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.payouts.allStatuses') },
  { value: 'eligible', label: t('admin.payouts.statusEligible'), icon: 'check_circle' },
  { value: 'pending', label: t('admin.payouts.statusPending'), icon: 'schedule' },
  { value: 'settled', label: t('admin.payouts.statusSettled'), icon: 'verified' },
])

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return '---'
  try {
    const d = new Date(dateStr)
    return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('vi-VN')
  } catch {
    return dateStr
  }
}

function getStatusLabel(status: string): string {
  const map: Record<string, string> = {
    eligible: t('admin.payouts.statusEligible'),
    pending: t('admin.payouts.statusPending'),
    settled: t('admin.payouts.statusSettled'),
  }
  return map[status] || status
}

function resetFilters() {
  emit('update:searchQuery', '')
  emit('update:selectedStatusFilter', 'all')
  emit('update:selectedDateFilter', '')
}
</script>

<template>
  <div class="settlement-periods-card">
    <div class="card-header">
      <div class="card-header-titles">
        <h3 class="title">{{ t('admin.payouts.settlementTableTitle') }}</h3>
        <span class="sub">{{ t('admin.payouts.settlementTableSub') }}</span>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="table-toolbar">
      <div class="search-box">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="search-input"
          :placeholder="t('admin.payouts.searchPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="filter-controls">
        <div class="dropdown-filter-wrap">
          <SelectDropdown
            :model-value="selectedStatusFilter"
            :options="statusOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedStatusFilter', String($event))"
          />
        </div>

        <div class="date-filter-wrap">
          <DatePicker
            :model-value="selectedDateFilter"
            :placeholder="t('admin.payouts.allDates')"
            @update:model-value="emit('update:selectedDateFilter', $event)"
          />
        </div>

        <button
          v-if="searchQuery || selectedStatusFilter !== 'all' || selectedDateFilter"
          type="button"
          class="btn-reset-filters"
          @click="resetFilters"
        >
          <span class="material-symbols-outlined icon-16">close</span>
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="table-responsive">
      <table class="aura-data-table">
        <thead>
          <tr>
            <th>{{ t('admin.payouts.colPeriod') }}</th>
            <th>{{ t('admin.payouts.colGross') }}</th>
            <th>{{ t('admin.payouts.colFee') }}</th>
            <th>{{ t('admin.payouts.colNet') }}</th>
            <th>{{ t('admin.payouts.colEligibleAt') }}</th>
            <th>{{ t('admin.payouts.colStatus') }}</th>
            <th class="text-right">{{ t('admin.payouts.colActions') }}</th>
          </tr>
        </thead>

        <!-- Loading Skeleton Rows -->
        <tbody v-if="loading">
          <tr v-for="i in 3" :key="i" class="skeleton-row">
            <td><div class="skeleton-shimmer sk-cell sk-period"></div></td>
            <td><div class="skeleton-shimmer sk-cell sk-amount"></div></td>
            <td><div class="skeleton-shimmer sk-cell sk-fee"></div></td>
            <td><div class="skeleton-shimmer sk-cell sk-amount"></div></td>
            <td><div class="skeleton-shimmer sk-cell sk-date"></div></td>
            <td><div class="skeleton-shimmer sk-cell sk-pill"></div></td>
            <td class="text-right"><div class="skeleton-shimmer sk-cell sk-btn"></div></td>
          </tr>
        </tbody>

        <!-- Empty State -->
        <tbody v-else-if="settlements.length === 0">
          <tr>
            <td colspan="7" class="empty-cell">
              <div class="empty-state-box">
                <span class="material-symbols-outlined empty-icon">summarize</span>
                <p class="empty-text">{{ t('admin.payouts.emptySettlements') }}</p>
                <button
                  v-if="searchQuery || selectedStatusFilter !== 'all' || selectedDateFilter"
                  type="button"
                  class="btn-clear"
                  @click="resetFilters"
                >
                  Xoá bộ lọc tìm kiếm
                </button>
              </div>
            </td>
          </tr>
        </tbody>

        <!-- Data Rows -->
        <tbody v-else>
          <tr v-for="s in settlements" :key="s.id" class="data-row">
            <td class="period-cell">
              <div class="period-badge">
                <span class="material-symbols-outlined icon-16 text-muted">date_range</span>
                <strong>{{ formatDate(s.period_start) }} → {{ formatDate(s.period_end) }}</strong>
              </div>
            </td>

            <td>
              <span class="gross-val">{{ formatMoney(s.gross_amount) }}</span>
            </td>

            <td>
              <div class="fee-cell-content">
                <span class="fee-val text-red"> -{{ formatMoney(getTotalDeduction(s)) }} </span>
                <div class="fee-sub-breakdown text-11 text-muted">
                  <span class="provider-fee-tag">
                    {{ t('admin.payouts.providerFee') }}: {{ formatMoney(getProviderFee(s)) }}
                  </span>
                  <span v-if="getHistoricalCommission(s) > 0" class="hist-comm-tag">
                    • {{ t('admin.payouts.historicalCommission') }}:
                    {{ formatMoney(getHistoricalCommission(s)) }}
                  </span>
                  <span v-if="getRefundAmount(s) > 0" class="hist-comm-tag">
                    • {{ t('admin.payouts.refunds') }}: {{ formatMoney(getRefundAmount(s)) }}
                  </span>
                </div>
              </div>
            </td>

            <td>
              <strong class="net-val text-pink">{{ formatMoney(getNetPayable(s)) }}</strong>
            </td>
            <td>
              <div class="eligible-badge">
                <span class="material-symbols-outlined icon-14 text-muted">event_available</span>
                <span>{{ formatDate(s.eligible_at) }}</span>
              </div>
            </td>

            <td>
              <span class="status-pill" :class="`status-${s.status}`">
                <span class="status-dot"></span>
                <span>{{ getStatusLabel(s.status) }}</span>
              </span>
            </td>

            <td class="text-right actions-cell">
              <AuraButton
                v-if="s.status === 'eligible'"
                variant="primary"
                size="sm"
                @click="emit('createPayout', s)"
              >
                <span class="material-symbols-outlined icon-14">payments</span>
                <span>{{ t('admin.payouts.btnCreatePayoutForPeriod') }}</span>
              </AuraButton>
              <span v-else-if="s.status === 'settled'" class="settled-tag">
                <span class="material-symbols-outlined icon-14">verified</span>
                <span>{{ t('admin.payouts.payoutCreated') }}</span>
              </span>
              <span v-else class="text-muted text-12">
                {{ t('admin.payouts.statusPending') }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.settlement-periods-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 12px;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
}

.sub {
  font-size: 12px;
  color: var(--muted);
  margin-top: 2px;
  display: block;
}

/* Toolbar */
.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 12px;
  padding: 8px 14px;
  flex: 1;
  min-width: 240px;
  max-width: 400px;
}

.search-icon {
  font-size: 18px;
  color: var(--muted);
}

.search-input {
  border: 0;
  background: transparent;
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
  width: 100%;
}

.search-input::placeholder {
  color: var(--muted);
}

.filter-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.dropdown-filter-wrap {
  width: 170px;
}

.date-filter-wrap {
  width: 170px;
}

.btn-reset-filters {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-reset-filters:hover {
  color: var(--pink-dark);
  border-color: var(--pink-dark);
}

/* Table */
.table-responsive {
  width: 100%;
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid var(--outline);
}

.aura-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.aura-data-table th {
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  white-space: nowrap;
}

.aura-data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--outline);
  color: var(--ink);
  vertical-align: middle;
}

.aura-data-table tr:last-child td {
  border-bottom: 0;
}

.data-row:hover td {
  background: var(--soft, rgba(0, 0, 0, 0.01));
}

.period-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.text-red {
  color: #ef4444;
}

.text-right {
  text-align: right;
}

.text-12 {
  font-size: 12px;
}

.gross-val,
.fee-val {
  font-weight: 600;
}

.net-val {
  font-size: 14px;
}

.eligible-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.settled-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #10b981;
  font-size: 12px;
  font-weight: 600;
}

/* Status Pill */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  white-space: nowrap;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.status-eligible {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-pending {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.status-settled {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.actions-cell {
  white-space: nowrap;
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}

/* Empty State */
.empty-cell {
  text-align: center;
  padding: 48px 16px;
}

.empty-state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.empty-icon {
  font-size: 36px;
  color: var(--muted);
  opacity: 0.6;
}

.empty-text {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
}

.btn-clear {
  margin-top: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--pink-dark);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

/* Skeletons */
.sk-cell {
  height: 14px;
  border-radius: 4px;
}

.sk-period {
  width: 140px;
}
.sk-amount {
  width: 90px;
}
.sk-fee {
  width: 75px;
}
.sk-date {
  width: 85px;
}
.sk-pill {
  width: 75px;
  height: 22px;
  border-radius: 999px;
}
.sk-btn {
  width: 80px;
  height: 28px;
  border-radius: 6px;
  margin-left: auto;
}

.skeleton-shimmer {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
}

.skeleton-shimmer::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 209, 220, 0.45) 45%,
    rgba(255, 255, 255, 0.8) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-wave 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skeleton-shimmer {
  background-color: var(--soft, #2b2e36);
}

@keyframes shimmer-wave {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}
</style>
