<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import ContractsTableSkeleton from './ContractsTableSkeleton.vue'
import type { RecurringContractItem } from '../../types/contract'

const props = defineProps<{
  contracts: RecurringContractItem[]
  venues: any[]
  activeStatusTab: string
  searchQuery: string
  selectedVenueId?: number
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeStatusTab', tab: string): void
  (e: 'update:searchQuery', q: string): void
  (e: 'update:selectedVenueId', venueId: number | undefined): void
  (e: 'viewDetail', contract: RecurringContractItem): void
  (e: 'openCreate'): void
}>()

const { t } = useI18n()

const venueOptions = computed<DropdownOption[]>(() => [
  { value: '', label: t('admin.contracts.allVenues') },
  ...(props.venues || []).map((v) => ({
    value: v.id,
    label: resolveLocalizedText(v.name),
  })),
])
</script>

<template>
  <div class="contracts-table-container">
    <!-- Toolbar: Tabs, Search & Filters -->
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
        <button
          type="button"
          class="tab-pill"
          :class="{ active: activeStatusTab === 'cancelled' }"
          @click="emit('update:activeStatusTab', 'cancelled')"
        >
          {{ t('admin.contracts.statusCancelled') }}
        </button>
      </div>

      <div class="toolbar-actions">
        <!-- Venue Selector Filter with shared SelectDropdown -->
        <div class="venue-dropdown-wrap">
          <SelectDropdown
            :model-value="selectedVenueId ?? ''"
            :options="venueOptions"
            variant="bordered"
            :aria-label="t('admin.contracts.allVenues')"
            @update:model-value="(val) => emit('update:selectedVenueId', val !== '' ? Number(val) : undefined)"
          />
        </div>

        <!-- Search Input -->
        <div class="search-input-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            :value="searchQuery"
            type="text"
            class="search-input"
            :placeholder="t('admin.contracts.searchPlaceholder')"
            @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="clear-btn"
            @click="emit('update:searchQuery', '')"
          >
            ✕
          </button>
        </div>
      </div>
    </div>

    <!-- Contracts Table Card -->
    <div class="contracts-table-card">
      <div class="table-header">
        <span>{{ t('admin.contracts.hdrContractAndCustomer') }}</span>
        <span>{{ t('admin.contracts.hdrCourtAndSlot') }}</span>
        <span>{{ t('admin.contracts.hdrMatchSchedule') }}</span>
        <span>{{ t('admin.contracts.hdrMonthlyValue') }}</span>
        <span>{{ t('admin.contracts.hdrStatus') }}</span>
        <span class="text-right">{{ t('admin.contracts.hdrActions') }}</span>
      </div>

      <!-- Loading Skeletons -->
      <ContractsTableSkeleton v-if="loading" />

      <!-- Empty State -->
      <div v-else-if="contracts.length === 0" class="empty-contracts">
        <span class="material-symbols-outlined empty-icon">receipt_long</span>
        <h4 class="empty-title">{{ t('admin.contracts.noContractsFound') }}</h4>
        <p class="empty-desc">{{ t('admin.contracts.noContractsSub') }}</p>
        <AuraButton variant="primary" class="mt-2" @click="emit('openCreate')">
          <span class="material-symbols-outlined icon-18">add</span>
          <span>{{ t('admin.contracts.createFirstContract') }}</span>
        </AuraButton>
      </div>

      <!-- Table Body -->
      <div v-else class="table-body">
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
              :class="{
                'tag-active': c.status === 'active' || c.status === 'confirmed',
                'tag-overdue': c.status === 'overdue',
                'tag-cancelled': c.status === 'cancelled',
              }"
            >
              ● {{
                c.status === 'active' || c.status === 'confirmed'
                  ? t('admin.contracts.activeEffective')
                  : c.status === 'overdue'
                    ? t('admin.contracts.overdueBadge')
                    : t('admin.contracts.statusCancelled')
              }}
            </span>
          </div>

          <div class="actions-col">
            <AuraButton
              size="sm"
              variant="outline"
              data-testid="btn-view-detail"
              @click="emit('viewDetail', c)"
            >
              <span class="material-symbols-outlined icon-16">visibility</span>
              <span>{{ t('admin.contracts.detailBtn') }}</span>
            </AuraButton>
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
  flex-wrap: wrap;
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

.tab-pill:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
}

.tab-pill.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.toolbar-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.venue-dropdown-wrap {
  min-width: 220px;
}

.venue-dropdown-wrap :deep(.custom-dropdown),
.venue-dropdown-wrap :deep(.custom-dropdown-trigger) {
  width: 100%;
}

.search-input-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  min-width: 260px;
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

.clear-btn {
  background: none;
  border: none;
  color: var(--muted);
  font-size: 12px;
  cursor: pointer;
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
  grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr 1fr 120px;
  padding: 14px 16px;
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
  grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr 1fr 120px;
  padding: 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
  transition: background 0.12s;
}

.contract-row:last-child {
  border-bottom: 0;
}

.contract-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.cust-col, .court-col, .schedule-col, .price-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cust-name {
  font-weight: 700;
  color: var(--ink);
}

.court-name {
  font-weight: 600;
  color: var(--ink);
}

.contract-code, .venue-sub, .time-slot, .deposit-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
  font-weight: 700;
}

.text-right {
  text-align: right;
}

.status-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 6px;
  width: fit-content;
}

.tag-active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.tag-overdue { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.tag-cancelled { background: rgba(107, 114, 128, 0.1); color: #6b7280; }

.actions-col {
  display: flex;
  justify-content: flex-end;
}

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
  transition: all 0.15s;
}

.btn-view:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
  background: var(--surface-secondary, rgba(219, 39, 119, 0.04));
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}

.empty-contracts-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
  text-align: center;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted);
  opacity: 0.6;
  margin-bottom: 12px;
}

.empty-title {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 700;
}

.empty-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.mt-4 {
  margin-top: 16px;
}

@media (max-width: 1024px) {
  .table-header, .contract-row {
    grid-template-columns: 1.8fr 1.4fr 1.2fr 1fr 100px;
  }
  .schedule-col, .table-header span:nth-child(3) {
    display: none;
  }
}
</style>
