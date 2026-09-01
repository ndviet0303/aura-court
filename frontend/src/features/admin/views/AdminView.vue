<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAdminDashboard, type ReviewPayment } from '../composables/useAdminDashboard'

import AdminKpiCards from '../components/dashboard/AdminKpiCards.vue'
import AdminDateRangeFilter from '../components/dashboard/AdminDateRangeFilter.vue'
import AdminAnalyticsCharts from '../components/dashboard/AdminAnalyticsCharts.vue'
import AdminRecentBookingsTable from '../components/dashboard/AdminRecentBookingsTable.vue'
import AdminPendingPaymentsCard from '../components/dashboard/AdminPendingPaymentsCard.vue'
import AdminQuickNav from '../components/dashboard/AdminQuickNav.vue'

const { t } = useI18n()

const {
  loading,
  notice,
  error,
  report,
  bookings,
  payments,
  datePreset,
  from,
  to,
  searchFilter,
  statusFilter,
  chartTab,
  csvUrl,
  filteredBookings,
  applyPreset,
  loadData,
  showNotice,
} = useAdminDashboard()

function handleVerifyPayment(p: ReviewPayment) {
  const idx = payments.value.findIndex((item) => item.id === p.id)
  if (idx >= 0) {
    payments.value.splice(idx, 1)
  }
  showNotice(t('admin.dashboard.verifySuccess', { code: p.transfer_code }))
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-dashboard-view">
    <!-- Header Section -->
    <header class="dashboard-header">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined title-icon">analytics</span>
          <h1 class="page-title">{{ t('admin.dashboard.pageTitle') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.dashboard.pageSubtitle') }}</p>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Date Range Preset & Filter Bar -->
    <AdminDateRangeFilter
      :date-preset="datePreset"
      :from="from"
      :to="to"
      :csv-url="csvUrl"
      @preset="applyPreset"
      @update:from="from = $event"
      @update:to="to = $event"
      @filter="loadData"
    />

    <!-- Executive KPI Cards Grid -->
    <AdminKpiCards :report="report" />

    <!-- Analytics Charts (Hourly vs Court) -->
    <AdminAnalyticsCharts
      :report="report"
      :chart-tab="chartTab"
      @update:chart-tab="chartTab = $event"
    />

    <!-- Operational Split Grid (Bookings vs Pending Payments) -->
    <div class="dashboard-split-grid">
      <AdminRecentBookingsTable
        :bookings="filteredBookings"
        :search="searchFilter"
        :status="statusFilter"
        @update:search="searchFilter = $event"
        @update:status="statusFilter = $event"
      />

      <AdminPendingPaymentsCard
        :payments="payments"
        @verify="handleVerifyPayment"
      />
    </div>

    <!-- System Quick Navigation Links -->
    <AdminQuickNav />
  </div>
</template>

<style scoped>
.admin-dashboard-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.dashboard-header {
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

.title-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.dashboard-split-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
}

@media (max-width: 950px) {
  .dashboard-split-grid {
    grid-template-columns: 1fr;
  }
}
</style>
