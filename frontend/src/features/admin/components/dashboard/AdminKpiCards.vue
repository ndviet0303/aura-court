<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { AdminReport } from '../../composables/useAdminDashboard'

defineProps<{
  report: AdminReport
  loading?: boolean
}>()

const { t } = useI18n()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="admin-kpi-grid">
    <template v-if="loading">
      <div v-for="i in 4" :key="i" class="kpi-card skeleton-card">
        <div class="kpi-top-row">
          <div class="skeleton-item skeleton-icon"></div>
          <div class="skeleton-item skeleton-title-bar"></div>
        </div>
        <div class="skeleton-item skeleton-val-bar"></div>
        <div class="skeleton-item skeleton-sub-bar"></div>
      </div>
    </template>

    <template v-else>
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
        <strong class="kpi-value text-yellow">{{ report.pendingRefunds >= 1000 ? formatMoney(report.pendingRefunds) : report.pendingRefunds }}</strong>
        <span class="kpi-sub">{{ t('admin.dashboard.awaitingAccounting') }}</span>
      </div>
    </template>
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

/* Skeleton Shimmer */
.skeleton-card {
  pointer-events: none;
}

.skeleton-item {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 6px;
}

.skeleton-item::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 209, 220, 0.45) 45%,
    rgba(255, 255, 255, 0.8) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-wave 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skeleton-item {
  background-color: var(--soft, #2b2e36);
}

:global(html.dark) .skeleton-item::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(120, 85, 94, 0.35) 45%,
    rgba(255, 255, 255, 0.1) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

.skeleton-icon {
  width: 20px;
  height: 20px;
  border-radius: 4px;
}

.skeleton-title-bar {
  width: 90px;
  height: 12px;
}

.skeleton-val-bar {
  width: 120px;
  height: 26px;
  margin: 4px 0 2px;
}

.skeleton-sub-bar {
  width: 100px;
  height: 12px;
}

@keyframes shimmer-wave {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

@media (max-width: 900px) {
  .admin-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
