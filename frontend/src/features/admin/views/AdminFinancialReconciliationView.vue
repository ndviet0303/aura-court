<script setup lang="ts">
import { useFinancialReconciliation } from '@/features/finance/composables/useFinancialReconciliation'
import ReconciliationKpiCards from '@/features/finance/components/reconciliation/ReconciliationKpiCards.vue'
import ReconciliationAlertBanner from '@/features/finance/components/reconciliation/ReconciliationAlertBanner.vue'
import ReconciliationFilterBar from '@/features/finance/components/reconciliation/ReconciliationFilterBar.vue'
import ReconciliationGrid from '@/features/finance/components/reconciliation/ReconciliationGrid.vue'
import ReconciliationInvestigationDrawer from '@/features/finance/components/reconciliation/ReconciliationInvestigationDrawer.vue'
import ReconciliationHealthAnalytics from '@/features/finance/components/reconciliation/ReconciliationHealthAnalytics.vue'

const {
  loading,
  syncing,
  detailLoading,
  notice,
  error,
  activeTab,
  selectedGateway,
  selectedStatus,
  selectedRisk,
  searchQuery,
  isDrawerOpen,
  selectedRun,
  runDetail,
  stats,
  runs,
  filteredRuns,
  openInvestigationDrawer,
  closeDrawer,
  triggerSync,
  autoResolveSafe,
  exportReport,
} = useFinancialReconciliation()
</script>

<template>
  <div class="financial-reconciliation-view">
    <!-- View Header -->
    <header class="view-header">
      <div class="header-titles">
        <div class="title-badge-row">
          <h1 class="page-title">⚖️ Đối Soát Tài Chính & Cảnh Báo Thông Minh</h1>
          <span class="live-sync-badge">
            <span class="live-dot"></span>
            NAPAS & PSP: ONLINE 100%
          </span>
        </div>
        <p class="page-sub">
          Hệ thống đối soát 3 chiều tự động giữa Sổ cái nội bộ Aura, Cổng thanh toán (PSP) và Tài khoản quyết toán ngân hàng.
        </p>
      </div>

      <div class="header-quick-actions">
        <button class="btn-top-sync" type="button" :disabled="syncing" @click="triggerSync">
          <span class="material-symbols-outlined" :class="{ 'spin-anim': syncing }">sync</span>
          {{ syncing ? 'Đang đồng bộ...' : 'Đồng Bộ Tức Thì' }}
        </button>
      </div>
    </header>

    <!-- Feedback Notice Alert -->
    <div v-if="notice" class="alert-notice">
      <span class="material-symbols-outlined">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="alert-error">
      <span class="material-symbols-outlined">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- 4 Summary KPI Cards -->
    <ReconciliationKpiCards :stats="stats" />

    <!-- Realtime Anomaly Alert Banner -->
    <ReconciliationAlertBanner
      :alerts-count="stats.open_alerts_count"
      @auto-resolve="autoResolveSafe"
      @open-investigation="openInvestigationDrawer(runs[0])"
    />

    <!-- Filter Controls Bar -->
    <ReconciliationFilterBar
      v-model:active-tab="activeTab"
      v-model:selected-gateway="selectedGateway"
      v-model:selected-status="selectedStatus"
      v-model:selected-risk="selectedRisk"
      v-model:search-query="searchQuery"
      :syncing="syncing"
      @sync="triggerSync"
      @export="exportReport"
    />

    <!-- Multi-Way Reconciliation Data Grid -->
    <ReconciliationGrid
      :items="filteredRuns"
      :loading="loading"
      @investigate="openInvestigationDrawer"
      @auto-resolve="autoResolveSafe"
    />

    <!-- Health Analytics & Live Recon Log -->
    <ReconciliationHealthAnalytics
      :health-trend="stats.health_trend"
      :match-rate="stats.match_rate"
    />

    <!-- Slide-over Drawer for Drill-down Investigation -->
    <ReconciliationInvestigationDrawer
      :show="isDrawerOpen"
      :item="selectedRun"
      :detail="runDetail"
      :loading="detailLoading"
      @close="closeDrawer"
      @resolve="autoResolveSafe"
    />
  </div>
</template>

<style scoped>
.financial-reconciliation-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1440px;
  margin: 0 auto;
  padding: 24px 20px;
  color: #191c1d;
  font-family: 'Inter', sans-serif;
}

.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.title-badge-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #191c1d;
  margin: 0;
}

.live-sync-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  background: #e6f4ea;
  color: #137333;
  padding: 3px 10px;
  border-radius: 999px;
  letter-spacing: 0.04em;
}

.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #137333;
}

.page-sub {
  font-size: 14px;
  color: #5d5f5f;
  margin: 4px 0 0;
}

.btn-top-sync {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #78555e;
  color: #ffffff;
  border: none;
  padding: 8px 18px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-top-sync:hover:not(:disabled) {
  background: #5e3e47;
}

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.alert-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #d1fae5;
  color: #065f46;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 500;
}

.alert-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #ffdad6;
  color: #ba1a1a;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 500;
}
</style>
