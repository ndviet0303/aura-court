<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useAdminDashboard, type ReviewPayment } from '../composables/useAdminDashboard'

import PlatformFeeSummary from '@/features/platform-fees/components/PlatformFeeSummary.vue'
import AdminRecentBookingsTable from '../components/dashboard/AdminRecentBookingsTable.vue'
import AdminPendingPaymentsCard from '../components/dashboard/AdminPendingPaymentsCard.vue'
import AdminQuickNav from '../components/dashboard/AdminQuickNav.vue'

const { t } = useI18n()

const {
  loading,
  notice,
  error,
  payments,
  searchFilter,
  statusFilter,
  filteredBookings,
  showNotice,
} = useAdminDashboard()

function handleVerifyPayment(p: ReviewPayment) {
  const idx = payments.value.findIndex((item) => item.id === p.id)
  if (idx >= 0) {
    payments.value.splice(idx, 1)
  }
  showNotice(t('admin.dashboard.verifySuccess', { code: p.transfer_code }))
}
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

    <!-- D15: platform fees collected this month and venues expiring within 7 days -->
    <PlatformFeeSummary />

    <!-- Operational Split Grid (Bookings vs Pending Payments) -->
    <div class="dashboard-split-grid">
      <AdminRecentBookingsTable
        :bookings="filteredBookings"
        :search="searchFilter"
        :status="statusFilter"
        :loading="loading"
        @update:search="searchFilter = $event"
        @update:status="statusFilter = $event"
      />

      <AdminPendingPaymentsCard
        :payments="payments"
        :loading="loading"
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
