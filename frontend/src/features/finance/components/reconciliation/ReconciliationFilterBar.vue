<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SelectDropdown, DatePicker } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'

const props = defineProps<{
  activeTab: 'today' | 'yesterday' | '7days' | 'month' | 'custom'
  dateFrom: string
  dateTo: string
  selectedGateway: string
  selectedStatus: string
  selectedRisk: string
  searchQuery: string
  syncing: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', val: 'today' | 'yesterday' | '7days' | 'month' | 'custom'): void
  (e: 'update:dateFrom', val: string): void
  (e: 'update:dateTo', val: string): void
  (e: 'update:selectedGateway', val: string): void
  (e: 'update:selectedStatus', val: string): void
  (e: 'update:selectedRisk', val: string): void
  (e: 'update:searchQuery', val: string): void
  (e: 'sync'): void
  (e: 'export', format: 'pdf' | 'excel' | 'csv'): void
}>()

const { t } = useI18n()

// Dropdown Options
const gatewayOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.reconciliation.allGateways'), icon: 'apps' },
  { value: 'VietQR', label: 'VietQR (MBBank)', icon: 'qr_code' },
  { value: 'MoMo', label: 'MoMo Business', icon: 'account_balance_wallet' },
  { value: 'VNPay', label: 'VNPay QR', icon: 'qr_code_scanner' },
  { value: 'ZaloPay', label: 'ZaloPay Gateway', icon: 'wallet' },
  { value: 'Stripe', label: 'Stripe International', icon: 'credit_card' },
])

const statusOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.reconciliation.allStatuses'), icon: 'filter_list' },
  { value: 'matched', label: t('admin.reconciliation.statusMatched'), icon: 'check_circle' },
  { value: 'mismatch', label: t('admin.reconciliation.statusMismatch'), icon: 'error' },
  { value: 'running', label: t('admin.reconciliation.statusRunning'), icon: 'pending' },
])

const riskOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.reconciliation.allRisks'), icon: 'shield' },
  { value: 'high', label: t('admin.reconciliation.riskHigh'), icon: 'crisis_alert' },
  { value: 'medium', label: t('admin.reconciliation.riskMed'), icon: 'warning' },
  { value: 'low', label: t('admin.reconciliation.riskLow'), icon: 'verified_user' },
])
</script>

<template>
  <div class="filter-wrapper">
    <!-- Top Row: Cycle Tabs & Action Buttons -->
    <div class="cycle-tabs-row">
      <div class="tabs-group">
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'today' }"
          @click="emit('update:activeTab', 'today')"
        >
          <span class="pulse-indicator"></span>
          {{ t('admin.reconciliation.tabToday') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'yesterday' }"
          @click="emit('update:activeTab', 'yesterday')"
        >
          {{ t('admin.reconciliation.tabYesterday') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === '7days' }"
          @click="emit('update:activeTab', '7days')"
        >
          {{ t('admin.reconciliation.tab7Days') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'month' }"
          @click="emit('update:activeTab', 'month')"
        >
          {{ t('admin.reconciliation.tabMonth') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'custom' }"
          @click="emit('update:activeTab', 'custom')"
        >
          <span class="material-symbols-outlined tab-icon">calendar_month</span>
          {{ t('admin.reconciliation.tabCustom') }}
        </button>
      </div>

      <div class="quick-actions-group">
        <button
          class="btn-sync"
          type="button"
          :disabled="syncing"
          @click="emit('sync')"
        >
          <span class="material-symbols-outlined" :class="{ 'spin-anim': syncing }">sync</span>
          {{ syncing ? t('admin.reconciliation.syncing') : t('admin.reconciliation.syncNow') }}
        </button>
        <div class="export-dropdown-wrap">
          <button class="btn-export" type="button" @click="emit('export', 'pdf')">
            <span class="material-symbols-outlined">download</span>
            {{ t('admin.reconciliation.exportBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Custom Date Range Row (when Custom tab is active) -->
    <div v-if="activeTab === 'custom'" class="custom-date-row">
      <div class="date-input-item">
        <label class="date-label">{{ t('admin.reconciliation.dateFromLabel') }}:</label>
        <div class="date-picker-container">
          <DatePicker
            :model-value="dateFrom"
            :placeholder="t('admin.reconciliation.dateFromLabel')"
            @update:model-value="emit('update:dateFrom', $event)"
          />
        </div>
      </div>
      <span class="date-sep">→</span>
      <div class="date-input-item">
        <label class="date-label">{{ t('admin.reconciliation.dateToLabel') }}:</label>
        <div class="date-picker-container">
          <DatePicker
            :model-value="dateTo"
            :placeholder="t('admin.reconciliation.dateToLabel')"
            @update:model-value="emit('update:dateTo', $event)"
          />
        </div>
      </div>
    </div>

    <!-- Bottom Row: Filter Controls using SelectDropdown -->
    <div class="filters-row">
      <!-- Search Input -->
      <div class="search-field">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          :placeholder="t('admin.reconciliation.searchPlaceholder')"
          class="search-input"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          class="clear-search-btn"
          type="button"
          @click="emit('update:searchQuery', '')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Gateway Dropdown -->
      <div class="dropdown-filter-item">
        <label class="dropdown-label">{{ t('admin.reconciliation.gatewayFilterLabel') }}</label>
        <div class="dropdown-box">
          <SelectDropdown
            :model-value="selectedGateway"
            :options="gatewayOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedGateway', String($event))"
          />
        </div>
      </div>

      <!-- Status Dropdown -->
      <div class="dropdown-filter-item">
        <label class="dropdown-label">{{ t('admin.reconciliation.statusFilterLabel') }}</label>
        <div class="dropdown-box">
          <SelectDropdown
            :model-value="selectedStatus"
            :options="statusOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedStatus', String($event))"
          />
        </div>
      </div>

      <!-- Risk Level Dropdown -->
      <div class="dropdown-filter-item">
        <label class="dropdown-label">{{ t('admin.reconciliation.riskFilterLabel') }}</label>
        <div class="dropdown-box">
          <SelectDropdown
            :model-value="selectedRisk"
            :options="riskOptions"
            variant="bordered"
            @update:model-value="emit('update:selectedRisk', String($event))"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.filter-wrapper {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 16px 20px;
  border: 1px solid var(--outline, #e1e3e4);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.cycle-tabs-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.tabs-group {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--soft, #f8f9fa);
  padding: 4px;
  border-radius: 999px;
  border: 1px solid var(--outline, #e1e3e4);
  flex-wrap: wrap;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 500;
  color: var(--muted, #4f4446);
  border: none;
  background: transparent;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: var(--ink, #191c1d);
}

.tab-btn.active {
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  font-weight: 700;
  box-shadow: 0 2px 6px rgba(219, 39, 119, 0.25);
}

.tab-icon {
  font-size: 16px;
}

.pulse-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #059669;
}

.tab-btn.active .pulse-indicator {
  background: #ffffff;
}

.quick-actions-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-sync {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--primary-container, #f8e8ec);
  color: var(--on-primary-container, #4e1827);
  border: none;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-sync:hover:not(:disabled) {
  background: #f8bbc9;
}

.btn-sync:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.btn-export {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface, #f8f9fa);
  border: 1px solid var(--outline, #e1e3e4);
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: background 0.2s;
}

.btn-export:hover {
  background: var(--soft, #edeeef);
}

.custom-date-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: var(--soft, #f8f9fa);
  border-radius: 12px;
  border: 1px solid var(--outline, #e1e3e4);
  flex-wrap: wrap;
}

.date-input-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.date-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
}

.date-picker-container {
  min-width: 160px;
}

.date-sep {
  color: var(--muted, #817476);
  font-weight: 700;
}

.filters-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding-top: 10px;
  border-top: 1px solid var(--outline, #f3f4f5);
}

.search-field {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--soft, #f8f9fa);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 12px;
  padding: 8px 14px;
  flex: 1;
  min-width: 260px;
}

.search-icon {
  font-size: 18px;
  color: var(--muted, #817476);
}

.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
  color: var(--ink, #191c1d);
}

.clear-search-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted, #817476);
  display: flex;
  align-items: center;
  padding: 0;
}

.dropdown-filter-item {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 170px;
}

.dropdown-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  white-space: nowrap;
}

.dropdown-box {
  flex: 1;
  min-width: 150px;
}
</style>
