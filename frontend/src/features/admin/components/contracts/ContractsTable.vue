<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import type { RecurringContract } from '../../composables/useAdminContracts'

defineProps<{
  contracts: RecurringContract[]
  activeStatusTab: string
  searchQuery: string
}>()

const emit = defineEmits<{
  (e: 'update:activeStatusTab', tab: string): void
  (e: 'update:searchQuery', q: string): void
  (e: 'viewDetail', contract: RecurringContract): void
  (e: 'openCreate'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="contracts-table-container">
    <!-- Toolbar: Tabs & Search -->
    <div class="contracts-toolbar">
      <div class="status-pills-bar">
        <button
          type="button"
          class="tab-pill"
          :class="{ active: activeStatusTab === 'all' }"
          @click="emit('update:activeStatusTab', 'all')"
        >
          {{ t('admin.contracts.allContracts') }}
        </button>
        <button
          type="button"
          class="tab-pill"
          :class="{ active: activeStatusTab === 'active' }"
          @click="emit('update:activeStatusTab', 'active')"
        >
          {{ t('admin.contracts.statusActive') }}
        </button>
        <button
          type="button"
          class="tab-pill"
          :class="{ active: activeStatusTab === 'overdue' }"
          @click="emit('update:activeStatusTab', 'overdue')"
        >
          {{ t('admin.contracts.statusOverdue') }}
        </button>
      </div>

      <div class="search-input-box">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="search-input"
          :placeholder="t('admin.contracts.searchPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>

    <!-- Contracts Table -->
    <div class="contracts-table-card">
      <div class="table-header">
        <span>{{ t('admin.contracts.hdrContractAndCustomer') }}</span>
        <span>{{ t('admin.contracts.hdrCourtAndSlot') }}</span>
        <span>{{ t('admin.contracts.hdrMatchSchedule') }}</span>
        <span>{{ t('admin.contracts.hdrMonthlyValue') }}</span>
        <span>{{ t('admin.contracts.hdrStatus') }}</span>
        <span>{{ t('admin.contracts.hdrActions') }}</span>
      </div>

      <div class="table-body">
        <div
          v-for="c in contracts"
          :key="c.id"
          class="contract-row"
        >
          <div class="cust-col">
            <strong class="cust-name">{{ c.customer_name }}</strong>
            <span class="contract-code font-mono">{{ c.contract_code }} · {{ c.phone }}</span>
          </div>

          <div class="court-col">
            <span class="court-name">{{ resolveLocalizedText(c.court_name) }}</span>
            <span class="venue-sub">{{ resolveLocalizedText(c.venue_name) }}</span>
          </div>

          <div class="schedule-col">
            <strong>{{ c.weekday }}</strong>
            <span class="time-slot font-mono">{{ c.time_slot }}</span>
          </div>

          <div class="price-col">
            <strong class="text-pink font-mono">{{ formatCurrency(c.total_monthly_amount) }}</strong>
            <span class="deposit-sub">{{ t('admin.contracts.depositLabel', { amount: formatCurrency(c.deposit_amount) }) }}</span>
          </div>

          <div class="status-col">
            <span
              class="status-tag"
              :class="c.status === 'active' ? 'tag-active' : 'tag-overdue'"
            >
              ● {{ c.status === 'active' ? t('admin.contracts.activeEffective') : t('admin.contracts.overdueBadge') }}
            </span>
          </div>

          <div class="actions-col">
            <button
              type="button"
              class="btn-view"
              data-testid="btn-view-detail"
              @click="emit('viewDetail', c)"
            >
              <span class="material-symbols-outlined icon-16">visibility</span>
              <span>{{ t('admin.contracts.detailBtn') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.contracts-table-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.contracts-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.status-pills-bar {
  display: flex;
  gap: 8px;
}

.tab-pill {
  padding: 8px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.tab-pill.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.search-input-box {
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

.contracts-table-card {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-header {
  display: grid;
  grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr 1fr 110px;
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

.contract-row {
  display: grid;
  grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr 1fr 110px;
  padding: 14px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
  transition: background 0.1s;
}

.contract-row:last-child {
  border-bottom: 0;
}

.contract-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
}

.cust-col, .court-col, .schedule-col, .price-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.contract-code, .venue-sub, .time-slot, .deposit-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.status-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.tag-active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.tag-overdue { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

.btn-view {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s;
}

.btn-view:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.icon-16 {
  font-size: 16px;
}
</style>
