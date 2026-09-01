<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAdminShiftReconciliation } from '../composables/useAdminShiftReconciliation'

import ActiveShiftSummaryCard from '../components/reconciliation/ActiveShiftSummaryCard.vue'
import DenominationCalculatorModal from '../components/reconciliation/DenominationCalculatorModal.vue'
import CashDropsModal from '../components/reconciliation/CashDropsModal.vue'
import ShiftHistoryTable from '../components/reconciliation/ShiftHistoryTable.vue'

const { t } = useI18n()

const {
  loading,
  notice,
  error,
  searchQuery,
  statusFilter,
  activeShift,
  denominations,
  totalDenominationCash,
  filteredHistory,
  showNotice,
  addCashDrop,
} = useAdminShiftReconciliation()

const showDenominationModal = ref(false)
const showCashDropsModal = ref(false)

function handleSaveDenominations() {
  activeShift.value.declared_cash = totalDenominationCash.value
  activeShift.value.variance = activeShift.value.declared_cash - activeShift.value.expected_cash
  showDenominationModal.value = false
  showNotice(t('admin.shifts.updatedCashNotice', { amount: `${totalDenominationCash.value.toLocaleString('vi-VN')} ₫` }))
}
</script>

<template>
  <div class="admin-reconciliation-view">
    <!-- Header Section -->
    <header class="reconciliation-header">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">receipt_long</span>
          <h1 class="page-title">{{ t('admin.shifts.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.shifts.subtitle') }}</p>
      </div>

      <div class="header-feedback">
        <div v-if="notice" class="alert-pill">{{ notice }}</div>
        <div v-if="error" class="alert-pill-error">{{ error }}</div>
      </div>
    </header>

    <!-- Active Shift Realtime Card -->
    <section class="active-shift-section">
      <ActiveShiftSummaryCard
        :shift="activeShift"
        @open-denominations="showDenominationModal = true"
        @open-cash-drops="showCashDropsModal = true"
      />
    </section>

    <!-- Shift History & Variance Audit -->
    <section class="history-section">
      <div class="section-title-row">
        <span class="material-symbols-outlined icon-20">history</span>
        <h2 class="section-title">{{ t('admin.shifts.historyTitle') }}</h2>
      </div>
      <ShiftHistoryTable
        :shifts="filteredHistory"
        :status-filter="statusFilter"
        :search-query="searchQuery"
        @update:status-filter="statusFilter = $event"
        @update:search-query="searchQuery = $event"
      />
    </section>

    <!-- Modals -->
    <DenominationCalculatorModal
      :show="showDenominationModal"
      :denominations="denominations"
      :total-cash="totalDenominationCash"
      @close="showDenominationModal = false"
      @save="handleSaveDenominations"
    />

    <CashDropsModal
      :show="showCashDropsModal"
      :drops="activeShift.cash_drops"
      @close="showCashDropsModal = false"
      @add-drop="addCashDrop"
    />
  </div>
</template>

<style scoped>
.admin-reconciliation-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.reconciliation-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.alert-pill {
  padding: 6px 14px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 6px 14px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
}

.active-shift-section, .history-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.icon-20 {
  font-size: 20px;
  color: var(--muted);
}
</style>
