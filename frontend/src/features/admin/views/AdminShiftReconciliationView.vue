<script setup lang="ts">
import { useAdminShiftReconciliation } from '../composables/useAdminShiftReconciliation'

import ShiftHeader from '../components/reconciliation/ShiftHeader.vue'
import ShiftKpiBar from '../components/reconciliation/ShiftKpiBar.vue'
import ActiveShiftSummaryCard from '../components/reconciliation/ActiveShiftSummaryCard.vue'
import ShiftHistoryTable from '../components/reconciliation/ShiftHistoryTable.vue'
import DenominationCalculatorModal from '../components/reconciliation/DenominationCalculatorModal.vue'
import CashDropsModal from '../components/reconciliation/CashDropsModal.vue'
import OpenShiftModal from '../components/reconciliation/OpenShiftModal.vue'
import CloseShiftModal from '../components/reconciliation/CloseShiftModal.vue'
import ShiftReconciliationDetailModal from '../components/reconciliation/ShiftReconciliationDetailModal.vue'

const {
  loading,
  saving,
  notice,
  error,
  venuesList,
  activeShift,
  hasActiveShift,
  searchQuery,
  statusFilter,
  selectedVenueId,
  selectedDate,
  showOpenShiftModal,
  showCloseShiftModal,
  showDenominationModal,
  showCashDropsModal,
  showReconciliationDetailModal,
  selectedShiftForReview,
  denominations,
  totalDenominationCash,
  filteredHistory,
  activeShiftsCount,
  todayCashCollected,
  todayDigitalRevenue,
  totalVarianceDiscrepancy,
  matchRatePercent,
  loadData,
  incrementDenom,
  resetDenominations,
  openNewShift,
  closeCurrentShift,
  reviewShiftReconciliation,
  addCashDrop,
  applyCountedCashToDeclared,
  openReviewModal,
} = useAdminShiftReconciliation()

async function handleOpenShiftSubmit(openingCash: number) {
  await openNewShift(openingCash)
}

async function handleCloseShiftSubmit(payload: { declaredCash: number; note?: string }) {
  await closeCurrentShift(payload.declaredCash, payload.note)
}

async function handleSaveReviewDecision(payload: { shiftId: number; status: string; note?: string }) {
  await reviewShiftReconciliation(payload.shiftId, payload.status, payload.note)
}
</script>

<template>
  <div class="admin-shifts-view">
    <!-- Header Section -->
    <ShiftHeader
      :loading="loading"
      :saving="saving"
      :has-active-shift="hasActiveShift"
      :notice="notice"
      :error="error"
      @refresh="loadData"
      @open-shift="showOpenShiftModal = true"
      @close-shift="showCloseShiftModal = true"
    />

    <!-- KPI Summary Bar with Skeleton Loading -->
    <ShiftKpiBar
      :active-shifts="activeShiftsCount"
      :today-cash="todayCashCollected"
      :today-digital="todayDigitalRevenue"
      :total-variance="totalVarianceDiscrepancy"
      :match-rate="matchRatePercent"
      :loading="loading"
    />

    <!-- Realtime Active Shift Section -->
    <section class="active-shift-section">
      <ActiveShiftSummaryCard
        :shift="activeShift"
        :has-active-shift="hasActiveShift"
        :loading="loading"
        @open-denominations="showDenominationModal = true"
        @open-cash-drops="showCashDropsModal = true"
        @open-shift="showOpenShiftModal = true"
        @close-shift="showCloseShiftModal = true"
      />
    </section>

    <!-- Shift History & Variance Audit Section -->
    <section class="history-section">
      <ShiftHistoryTable
        :shifts="filteredHistory"
        :venues="venuesList"
        :status-filter="statusFilter"
        :selected-venue-id="selectedVenueId"
        :selected-date="selectedDate"
        :search-query="searchQuery"
        :loading="loading"
        @update:status-filter="statusFilter = $event"
        @update:selected-venue-id="selectedVenueId = $event"
        @update:selected-date="selectedDate = $event"
        @update:search-query="searchQuery = $event"
        @open-review="openReviewModal"
      />
    </section>

    <!-- Modals -->
    <DenominationCalculatorModal
      :show="showDenominationModal"
      :denominations="denominations"
      :total-cash="totalDenominationCash"
      @close="showDenominationModal = false"
      @save="applyCountedCashToDeclared"
      @increment="incrementDenom"
      @reset="resetDenominations"
    />

    <CashDropsModal
      :show="showCashDropsModal"
      :drops="activeShift.cash_drops"
      @close="showCashDropsModal = false"
      @add-drop="addCashDrop"
    />

    <OpenShiftModal
      :show="showOpenShiftModal"
      :saving="saving"
      @close="showOpenShiftModal = false"
      @submit="handleOpenShiftSubmit"
    />

    <CloseShiftModal
      :show="showCloseShiftModal"
      :shift="activeShift"
      :saving="saving"
      @close="showCloseShiftModal = false"
      @submit="handleCloseShiftSubmit"
      @open-denominations="showDenominationModal = true"
    />

    <ShiftReconciliationDetailModal
      :show="showReconciliationDetailModal"
      :shift="selectedShiftForReview"
      :saving="saving"
      @close="showReconciliationDetailModal = false"
      @save-decision="handleSaveReviewDecision"
    />
  </div>
</template>

<style scoped>
.admin-shifts-view {
  display: flex;
  flex-direction: column;
  gap: 22px;
  width: 100%;
  max-width: 100%;
  margin: 0;
  padding: 24px 28px;
  color: var(--ink);
  box-sizing: border-box;
}

.active-shift-section,
.history-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
