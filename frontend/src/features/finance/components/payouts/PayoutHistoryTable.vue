<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown, DatePicker, type DropdownOption } from '@/shared/components'
import type { PayoutRecord } from '../../types/finance'

const props = withDefaults(
  defineProps<{
    payouts: PayoutRecord[]
    loading?: boolean
    actionLoading?: boolean
    searchQuery?: string
    selectedStatusFilter?: string
    selectedDateFilter?: string
  }>(),
  {
    loading: false,
    actionLoading: false,
    searchQuery: '',
    selectedStatusFilter: 'all',
    selectedDateFilter: '',
  }
)

const emit = defineEmits<{
  (e: 'update:searchQuery', val: string): void
  (e: 'update:selectedStatusFilter', val: string): void
  (e: 'update:selectedDateFilter', val: string): void
  (e: 'approve', payoutId: number): void
  (e: 'submit', payoutId: number): void
  (e: 'sync', payoutId: number): void
}>()

const { t } = useI18n()

const statusOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.payouts.allStatuses') },
  { value: 'paid', label: t('admin.payouts.statusPaid'), icon: 'check_circle' },
  { value: 'approved', label: t('admin.payouts.statusApproved'), icon: 'fact_check' },
  { value: 'submitted', label: t('admin.payouts.statusSubmitted'), icon: 'send' },
  { value: 'processing', label: t('admin.payouts.statusProcessing'), icon: 'hourglass_top' },
  { value: 'draft', label: t('admin.payouts.statusDraft'), icon: 'edit_document' },
  { value: 'failed', label: t('admin.payouts.statusFailed'), icon: 'error' },
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
    paid: t('admin.payouts.statusPaid'),
    approved: t('admin.payouts.statusApproved'),
    submitted: t('admin.payouts.statusSubmitted'),
    processing: t('admin.payouts.statusProcessing'),
    draft: t('admin.payouts.statusDraft'),
    failed: t('admin.payouts.statusFailed'),
    reversed: t('admin.payouts.statusReversed'),
    rejected: t('admin.payouts.statusFailed'),
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
  <div class="payout-history-card">
    <div class="card-header">
      <div class="card-header-titles">
        <h3 class="title">{{ t('admin.payouts.tableTitle') }}</h3>
        <span class="sub">{{ t('admin.payouts.tableSub') }}</span>
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
            <th>{{ t('admin.payouts.colReference') }}</th>
            <th>{{ t('admin.payouts.colCreatedAt') }}</th>
            <th>{{ t('admin.payouts.colBank') }}</th>
            <th>{{ t('admin.payouts.colAmount') }}</th>
            <th>{{ t('admin.payouts.colStatus') }}</th>
            <th class="text-right">{{ t('admin.payouts.colActions') }}</th>
          </tr>
        </thead>

        <!-- Loading Skeleton Rows -->
        <tbody v-if="loading">
          <tr v-for="i in 4" :key="i" class="skeleton-row">
            <td><div class="skeleton-shimmer sk-cell sk-ref"></div></td>
            <td><div class="skeleton-shimmer sk-cell sk-date"></div></td>
            <td>
              <div class="sk-bank-col">
                <div class="skeleton-shimmer sk-cell sk-bank"></div>
                <div class="skeleton-shimmer sk-cell sk-holder"></div>
              </div>
            </td>
            <td><div class="skeleton-shimmer sk-cell sk-amount"></div></td>
            <td><div class="skeleton-shimmer sk-cell sk-pill"></div></td>
            <td class="text-right"><div class="skeleton-shimmer sk-cell sk-btn"></div></td>
          </tr>
        </tbody>

        <!-- Empty State -->
        <tbody v-else-if="payouts.length === 0">
          <tr>
            <td colspan="6" class="empty-cell">
              <div class="empty-state-box">
                <span class="material-symbols-outlined empty-icon">receipt_long</span>
                <p class="empty-text">{{ t('admin.payouts.emptyPayouts') }}</p>
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
          <tr v-for="p in payouts" :key="p.id" class="data-row">
            <td>
              <strong class="font-mono text-pink">{{ p.reference || p.code || `WD-${p.id}` }}</strong>
            </td>

            <td class="date-cell">
              <div class="date-stack">
                <span>{{ formatDate(p.created_at) }}</span>
                <span v-if="p.paid_at" class="completed-sub">
                  {{ formatDate(p.paid_at) }}
                </span>
              </div>
            </td>

            <td>
              <div class="bank-col">
                <span class="bank-acc">
                  {{ p.beneficiary?.bank_code || '---' }}
                  <span v-if="p.beneficiary?.account_number"> - {{ p.beneficiary.account_number }}</span>
                </span>
                <span class="holder-sub">{{ p.beneficiary?.account_holder || p.beneficiary_name || '' }}</span>
              </div>
            </td>

            <td>
              <strong class="amount-val">{{ formatMoney(p.amount) }}</strong>
            </td>

            <td>
              <span class="status-pill" :class="`status-${p.status}`">
                <span class="status-dot"></span>
                <span>{{ getStatusLabel(p.status) }}</span>
              </span>
            </td>

            <td class="text-right actions-cell">
              <!-- Action Buttons according to state -->
              <AuraButton
                v-if="p.status === 'draft'"
                variant="primary"
                size="sm"
                :disabled="actionLoading"
                @click="emit('approve', p.id)"
              >
                <span class="material-symbols-outlined icon-14">fact_check</span>
                <span>{{ t('admin.payouts.btnApprove') }}</span>
              </AuraButton>

              <AuraButton
                v-else-if="p.status === 'approved'"
                variant="primary"
                size="sm"
                :disabled="actionLoading"
                @click="emit('submit', p.id)"
              >
                <span class="material-symbols-outlined icon-14">send</span>
                <span>{{ t('admin.payouts.btnSubmit') }}</span>
              </AuraButton>

              <AuraButton
                v-else-if="p.status === 'submitted' || p.status === 'processing'"
                variant="secondary"
                size="sm"
                :disabled="actionLoading"
                @click="emit('sync', p.id)"
              >
                <span class="material-symbols-outlined icon-14">sync</span>
                <span>{{ t('admin.payouts.btnSyncStatus') }}</span>
              </AuraButton>

              <span v-else class="text-muted text-12">---</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.payout-history-card {
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

.text-pink {
  color: var(--pink-dark, #db2777);
}

.text-right {
  text-align: right;
}

.text-12 {
  font-size: 12px;
}

.date-stack {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.completed-sub {
  font-size: 11px;
  color: #10b981;
}

.bank-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.bank-acc {
  font-weight: 600;
}

.holder-sub {
  font-size: 11px;
  color: var(--muted);
}

.amount-val {
  font-size: 14px;
  color: var(--ink);
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

.status-paid {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-approved {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.status-submitted {
  background: rgba(139, 92, 246, 0.1);
  color: #8b5cf6;
}

.status-processing {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.status-draft {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.status-failed,
.status-rejected {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.status-reversed {
  background: rgba(236, 72, 153, 0.1);
  color: #ec4899;
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

.sk-ref { width: 90px; }
.sk-date { width: 80px; }
.sk-bank-col { display: flex; flex-direction: column; gap: 4px; }
.sk-bank { width: 130px; }
.sk-holder { width: 100px; height: 11px; }
.sk-amount { width: 85px; }
.sk-pill { width: 75px; height: 22px; border-radius: 999px; }
.sk-btn { width: 68px; height: 28px; border-radius: 6px; margin-left: auto; }

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
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
</style>
