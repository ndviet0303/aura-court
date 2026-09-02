<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useFinancialReconciliation } from '@/features/finance/composables/useFinancialReconciliation'
import ReconciliationKpiCards from '@/features/finance/components/reconciliation/ReconciliationKpiCards.vue'
import ReconciliationAlertBanner from '@/features/finance/components/reconciliation/ReconciliationAlertBanner.vue'
import ReconciliationFilterBar from '@/features/finance/components/reconciliation/ReconciliationFilterBar.vue'
import ReconciliationGrid from '@/features/finance/components/reconciliation/ReconciliationGrid.vue'
import ReconciliationInvestigationDrawer from '@/features/finance/components/reconciliation/ReconciliationInvestigationDrawer.vue'
import ReconciliationHealthAnalytics from '@/features/finance/components/reconciliation/ReconciliationHealthAnalytics.vue'

const { t } = useI18n()

const {
  loading,
  syncing,
  detailLoading,
  notice,
  error,
  activeTab,
  dateFrom,
  dateTo,
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
          <div class="title-with-icon">
            <span class="material-symbols-outlined header-icon">account_balance</span>
            <h1 class="page-title">{{ t('admin.reconciliation.title') }}</h1>
          </div>
          <span class="live-sync-badge">
            <span class="live-dot"></span>
            {{ t('admin.reconciliation.liveSync') }}
          </span>
        </div>
        <p class="page-sub">
          {{ t('admin.reconciliation.subtitle') }}
        </p>
      </div>

      <div class="header-quick-actions">
        <AuraButton variant="primary" type="button" :disabled="syncing" @click="triggerSync">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-anim': syncing }">sync</span>
          <span>{{ syncing ? t('admin.reconciliation.syncing') : t('admin.reconciliation.syncNow') }}</span>
        </AuraButton>
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
    <ReconciliationKpiCards :stats="stats" :loading="loading" />

    <!-- Realtime Anomaly Alert Banner -->
    <ReconciliationAlertBanner
      :alerts-count="stats.open_alerts_count"
      @auto-resolve="autoResolveSafe"
      @open-investigation="openInvestigationDrawer(runs[0])"
    />

    <!-- Filter Controls Bar -->
    <ReconciliationFilterBar
      v-model:active-tab="activeTab"
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
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
      :loading="loading"
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
  color: var(--ink);
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
  flex-wrap: wrap;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
  margin: 0;
}

.live-sync-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  padding: 3px 10px;
  border-radius: 999px;
  letter-spacing: 0.04em;
}

.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
}

.page-sub {
  font-size: 13px;
  color: var(--muted);
  margin: 6px 0 0;
}

.header-quick-actions {
  display: flex;
  align-items: center;
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
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.alert-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.icon-18 {
  font-size: 18px;
}
</style>
