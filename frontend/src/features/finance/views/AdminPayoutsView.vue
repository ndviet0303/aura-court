<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminPayouts } from '../composables/useAdminPayouts'

import PayoutMetricsBar from '../components/payouts/PayoutMetricsBar.vue'
import BeneficiaryCard from '../components/payouts/BeneficiaryCard.vue'
import PayoutStepper from '../components/payouts/PayoutStepper.vue'
import PayoutHistoryTable from '../components/payouts/PayoutHistoryTable.vue'
import SettlementPeriodsTable from '../components/payouts/SettlementPeriodsTable.vue'
import CreatePayoutModal from '../components/payouts/CreatePayoutModal.vue'
import EditBeneficiaryModal from '../components/payouts/EditBeneficiaryModal.vue'
import SnapshotSettlementModal from '../components/payouts/SnapshotSettlementModal.vue'

const { t } = useI18n()

const {
  loading,
  actionLoading,
  notice,
  error,
  activeTab,
  settlements,
  payouts,
  beneficiary,
  metrics,
  showCreateModal,
  showEditBeneficiaryModal,
  showSnapshotModal,
  selectedSettlementForPayout,
  payoutSearch,
  payoutStatusFilter,
  payoutDateFilter,
  settlementSearch,
  settlementStatusFilter,
  settlementDateFilter,
  filteredPayouts,
  filteredSettlements,
  eligibleSettlements,
  loadData,
  openCreateModal,
  createPayout,
  approvePayout,
  submitPayout,
  syncPayoutStatus,
  snapshotSettlement,
  updateBeneficiary,
} = useAdminPayouts()

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-payouts-view">
    <!-- Top Header Section -->
    <header class="payouts-header-section">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">account_balance_wallet</span>
          <h1 class="page-title">{{ t('admin.payouts.title') }}</h1>
        </div>
        <p class="page-subtitle">{{ t('admin.payouts.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <!-- Refresh Button -->
        <AuraButton variant="secondary" size="sm" :disabled="loading" @click="loadData">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }">
            refresh
          </span>
          <span>{{ t('admin.payouts.refreshBtn') }}</span>
        </AuraButton>

        <!-- Snapshot Settlement Button -->
        <AuraButton variant="secondary" size="sm" @click="showSnapshotModal = true">
          <span class="material-symbols-outlined icon-18">camera_alt</span>
          <span>{{ t('admin.payouts.snapshotSettlementBtn') }}</span>
        </AuraButton>

        <!-- Create Payout Button -->
        <AuraButton
          variant="primary"
          size="sm"
          class="btn-primary-action"
          @click="openCreateModal()"
        >
          <span class="material-symbols-outlined icon-18">payments</span>
          <span>{{ t('admin.payouts.createPayoutBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill alert-success">
      <span class="material-symbols-outlined icon-16">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="alert-pill alert-error">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Settlement Metrics KPI Cards with Skeletons -->
    <PayoutMetricsBar v-bind="metrics" :loading="loading" />

    <!-- Beneficiary Account Card with Skeleton -->
    <BeneficiaryCard
      :beneficiary="beneficiary"
      :loading="loading"
      @update="showEditBeneficiaryModal = true"
    />

    <!-- View Navigation Tab Buttons -->
    <div class="view-navigation-bar">
      <button
        type="button"
        class="view-nav-btn"
        :class="{ active: activeTab === 'payouts' }"
        @click="activeTab = 'payouts'"
      >
        <span class="material-symbols-outlined icon-18">receipt_long</span>
        <span>{{ t('admin.payouts.tabPayouts') }}</span>
        <span v-if="payouts.length > 0" class="nav-count-badge">{{ payouts.length }}</span>
      </button>

      <button
        type="button"
        class="view-nav-btn"
        :class="{ active: activeTab === 'settlements' }"
        @click="activeTab = 'settlements'"
      >
        <span class="material-symbols-outlined icon-18">summarize</span>
        <span>{{ t('admin.payouts.tabSettlements') }}</span>
        <span v-if="settlements.length > 0" class="nav-count-badge">{{ settlements.length }}</span>
      </button>

      <button
        type="button"
        class="view-nav-btn"
        :class="{ active: activeTab === 'flow' }"
        @click="activeTab = 'flow'"
      >
        <span class="material-symbols-outlined icon-18">timeline</span>
        <span>{{ t('admin.payouts.tabFlow') }}</span>
      </button>
    </div>

    <!-- Tab 1: Payouts History Table -->
    <section v-if="activeTab === 'payouts'" class="tab-pane">
      <PayoutHistoryTable
        :payouts="filteredPayouts"
        :loading="loading"
        :action-loading="actionLoading"
        :search-query="payoutSearch"
        :selected-status-filter="payoutStatusFilter"
        :selected-date-filter="payoutDateFilter"
        @update:search-query="payoutSearch = $event"
        @update:selected-status-filter="payoutStatusFilter = $event"
        @update:selected-date-filter="payoutDateFilter = $event"
        @approve="approvePayout"
        @submit="submitPayout"
        @sync="syncPayoutStatus"
      />
    </section>

    <!-- Tab 2: Settlement Periods Table -->
    <section v-else-if="activeTab === 'settlements'" class="tab-pane">
      <SettlementPeriodsTable
        :settlements="filteredSettlements"
        :loading="loading"
        :search-query="settlementSearch"
        :selected-status-filter="settlementStatusFilter"
        :selected-date-filter="settlementDateFilter"
        @update:search-query="settlementSearch = $event"
        @update:selected-status-filter="settlementStatusFilter = $event"
        @update:selected-date-filter="settlementDateFilter = $event"
        @create-payout="openCreateModal($event)"
      />
    </section>

    <!-- Tab 3: Visual 5-Step Payout Stepper Flow -->
    <section v-else-if="activeTab === 'flow'" class="tab-pane">
      <PayoutStepper :current-step="3" />
    </section>

    <!-- Modals -->
    <CreatePayoutModal
      :show="showCreateModal"
      :available-net="metrics.net"
      :eligible-settlements="eligibleSettlements"
      :beneficiary="beneficiary"
      :selected-settlement="selectedSettlementForPayout"
      :loading="actionLoading"
      @close="showCreateModal = false"
      @submit="createPayout"
    />

    <EditBeneficiaryModal
      :show="showEditBeneficiaryModal"
      :beneficiary="beneficiary"
      :loading="actionLoading"
      @close="showEditBeneficiaryModal = false"
      @submit="updateBeneficiary"
    />

    <SnapshotSettlementModal
      :show="showSnapshotModal"
      :loading="actionLoading"
      @close="showSnapshotModal = false"
      @submit="snapshotSettlement"
    />
  </div>
</template>

<style scoped>
.admin-payouts-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.payouts-header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
}

.page-subtitle {
  margin: 6px 0 0;
  font-size: 13.5px;
  color: var(--muted);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* Alert Pills */
.alert-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 12px;
  font-size: 13.5px;
  font-weight: 600;
  animation: slideIn 0.2s ease-out;
}

.alert-success {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

:global(html.dark) .alert-success {
  color: #34d399;
}

.alert-error {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

:global(html.dark) .alert-error {
  color: #f87171;
}

/* Tab Navigation Bar */
.view-navigation-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface);
  padding: 6px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  width: fit-content;
  max-width: 100%;
  overflow-x: auto;
}

.view-nav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.view-nav-btn:hover {
  color: var(--ink);
  background: var(--soft, rgba(0, 0, 0, 0.03));
}

.view-nav-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  box-shadow: 0 2px 8px rgba(219, 39, 119, 0.25);
}

.nav-count-badge {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.2);
  color: inherit;
}

.view-nav-btn:not(.active) .nav-count-badge {
  background: var(--soft, rgba(0, 0, 0, 0.06));
  color: var(--muted);
}

.tab-pane {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
