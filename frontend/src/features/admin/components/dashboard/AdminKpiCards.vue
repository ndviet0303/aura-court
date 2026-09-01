<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { AdminReport } from '../../composables/useAdminDashboard'

defineProps<{
  report: AdminReport
}>()

const { t } = useI18n()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="admin-kpi-grid">
    <div class="kpi-card">
      <div class="kpi-top-row">
        <span class="material-symbols-outlined kpi-icon text-pink">payments</span>
        <span class="kpi-title">{{ t('admin.dashboard.totalRevenue') }}</span>
      </div>
      <strong class="kpi-value text-pink">{{ formatMoney(report.revenue) }}</strong>
      <span class="kpi-sub text-green">{{ t('admin.dashboard.revenueTrend') }}</span>
    </div>

    <div class="kpi-card">
      <div class="kpi-top-row">
        <span class="material-symbols-outlined kpi-icon">sports_tennis</span>
        <span class="kpi-title">{{ t('admin.dashboard.totalBookings') }}</span>
      </div>
      <strong class="kpi-value">{{ report.bookings }}</strong>
      <span class="kpi-sub">{{ t('admin.dashboard.confirmedBookings', { count: report.confirmed }) }}</span>
    </div>

    <div class="kpi-card">
      <div class="kpi-top-row">
        <span class="material-symbols-outlined kpi-icon text-green">trending_up</span>
        <span class="kpi-title">{{ t('admin.dashboard.occupancyRate') }}</span>
      </div>
      <strong class="kpi-value text-green">{{ report.occupancyRate || 85 }}%</strong>
      <span class="kpi-sub">{{ t('admin.dashboard.avgWeeklyOccupancy') }}</span>
    </div>

    <div class="kpi-card">
      <div class="kpi-top-row">
        <span class="material-symbols-outlined kpi-icon text-yellow">currency_exchange</span>
        <span class="kpi-title">{{ t('admin.dashboard.pendingRefunds') }}</span>
      </div>
      <strong class="kpi-value text-yellow">{{ report.pendingRefunds }}</strong>
      <span class="kpi-sub">{{ t('admin.dashboard.awaitingAccounting') }}</span>
    </div>
  </div>
</template>

<style scoped>
.admin-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.kpi-top-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 2px;
}

.kpi-icon {
  font-size: 20px;
}

.kpi-title {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.kpi-value {
  font-size: 24px;
  font-weight: 800;
}

.kpi-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }

@media (max-width: 900px) {
  .admin-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
