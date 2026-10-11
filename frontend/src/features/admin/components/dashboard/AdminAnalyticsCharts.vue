<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import type { AdminReport } from '../../composables/useAdminDashboard'

const props = defineProps<{
  report: AdminReport
  chartTab: 'hourly' | 'court'
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:chartTab', tab: 'hourly' | 'court'): void
  (e: 'update:chart-tab', tab: 'hourly' | 'court'): void
}>()

const { t } = useI18n()

function switchTab(tab: 'hourly' | 'court') {
  emit('update:chartTab', tab)
  emit('update:chart-tab', tab)
}

// Generate standard facility operating hours (06:00 - 22:00)
const hourlyData = computed(() => {
  const map: Record<string, number> = {}
  if (Array.isArray(props.report.byHour)) {
    for (const h of props.report.byHour) {
      if (h && h.hour) {
        const formattedHour = h.hour.length === 2 ? `${h.hour}:00` : h.hour
        map[formattedHour] = h.bookings || 0
      }
    }
  }

  const standardHours = [
    '06:00',
    '07:00',
    '08:00',
    '09:00',
    '10:00',
    '11:00',
    '12:00',
    '13:00',
    '14:00',
    '15:00',
    '16:00',
    '17:00',
    '18:00',
    '19:00',
    '20:00',
    '21:00',
    '22:00',
  ]

  const maxVal = Math.max(...Object.values(map), 1)

  return standardHours.map((hour) => {
    const bookings = map[hour] || 0
    const pct = Math.round((bookings / maxVal) * 100)
    return {
      hour,
      bookings,
      isPeak: bookings > 0 && bookings === maxVal,
      heightPercent: bookings > 0 ? Math.max(pct, 14) : 6,
    }
  })
})

const totalHourlyBookings = computed(() => {
  return hourlyData.value.reduce((acc, h) => acc + h.bookings, 0)
})

const peakHourItem = computed(() => {
  const sorted = [...hourlyData.value]
    .filter((h) => h.bookings > 0)
    .sort((a, b) => b.bookings - a.bookings)
  return sorted[0] || null
})

const maxCourtBookings = computed(() => {
  if (!props.report.byCourt || props.report.byCourt.length === 0) return 1
  return Math.max(...props.report.byCourt.map((c) => c.bookings || 0), 1)
})
</script>

<template>
  <div class="analytics-charts-card">
    <div class="card-header">
      <div class="title-row">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">bar_chart</span>
          <div>
            <h3 class="title">{{ t('admin.dashboard.performanceAnalytics') }}</h3>
            <span v-if="peakHourItem" class="peak-badge">
              <span class="material-symbols-outlined icon-14">bolt</span>
              Khung giờ cao điểm: <strong>{{ peakHourItem.hour }}</strong> ({{
                peakHourItem.bookings
              }}
              lượt)
            </span>
          </div>
        </div>
        <div class="chart-tab-switcher">
          <button
            type="button"
            class="c-tab"
            :class="{ active: chartTab === 'hourly' }"
            @click="switchTab('hourly')"
          >
            <span class="material-symbols-outlined tab-icon">schedule</span>
            {{ t('admin.dashboard.byHour') }}
          </button>
          <button
            type="button"
            class="c-tab"
            :class="{ active: chartTab === 'court' }"
            @click="switchTab('court')"
          >
            <span class="material-symbols-outlined tab-icon">stadium</span>
            {{ t('admin.dashboard.byCourt') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Skeleton Loading Chart -->
    <div v-if="loading" class="chart-bars-wrap skeleton-chart">
      <div v-for="i in 17" :key="i" class="bar-col">
        <div class="bar-fill-track">
          <div
            class="skeleton-item skeleton-bar"
            :style="{ height: `${20 + ((i * 17) % 65)}%` }"
          ></div>
        </div>
        <div class="skeleton-item skeleton-hour-lbl"></div>
      </div>
    </div>

    <!-- Real Hourly Distribution Chart -->
    <div v-else-if="chartTab === 'hourly'" class="chart-bars-wrap">
      <div
        v-for="h in hourlyData"
        :key="h.hour"
        class="bar-col"
        :title="`${h.hour}: ${h.bookings} lượt đặt sân`"
      >
        <div class="bar-fill-track">
          <div
            class="bar-fill"
            :class="{ 'has-data': h.bookings > 0, 'is-peak': h.isPeak }"
            :style="{ height: `${h.heightPercent}%` }"
          >
            <div v-if="h.isPeak" class="peak-crown"></div>
          </div>
        </div>
        <span
          class="bar-val font-mono"
          :class="{ 'text-muted': h.bookings === 0, 'text-pink': h.bookings > 0 }"
        >
          {{ h.bookings }}
        </span>
        <span class="bar-lbl font-mono">{{ h.hour }}</span>
      </div>
    </div>

    <!-- Real By Court Utilization -->
    <div v-else class="court-util-container">
      <div v-if="!report.byCourt || report.byCourt.length === 0" class="empty-chart-note">
        <span class="material-symbols-outlined empty-icon">bar_chart</span>
        <p>{{ t('admin.dashboard.noCourtChartData') }}</p>
      </div>

      <div v-else class="court-util-list">
        <div v-for="c in report.byCourt" :key="c.court_id" class="court-util-row">
          <strong class="c-name">{{
            resolveLocalizedText(c.court?.name) || `Sân #${c.court_id}`
          }}</strong>
          <div class="progress-bar-wrap">
            <div
              class="progress-fill"
              :style="{ width: `${Math.min((c.bookings / maxCourtBookings) * 100, 100)}%` }"
            ></div>
          </div>
          <div class="c-meta">
            <span>{{ t('admin.dashboard.bookingsCount', { count: c.bookings }) }}</span> ·
            <strong class="text-pink">{{ formatCurrency(c.revenue || 0) }}</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.analytics-charts-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.peak-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 3px;
  font-size: 11px;
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.08);
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 500;
}

.icon-14 {
  font-size: 14px;
}

.chart-tab-switcher {
  display: flex;
  gap: 4px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  padding: 3px;
  border-radius: 8px;
}

.c-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 6px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.tab-icon {
  font-size: 16px;
}

.c-tab.active {
  background: var(--surface);
  color: var(--ink);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.chart-bars-wrap {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 6px;
  height: 180px;
  padding: 16px 8px 8px;
  overflow-x: auto;
}

.bar-col {
  flex: 1;
  min-width: 24px;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.bar-fill-track {
  flex: 1;
  width: 100%;
  max-width: 24px;
  display: flex;
  align-items: flex-end;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  overflow: hidden;
}

.bar-fill {
  width: 100%;
  background: var(--outline);
  border-radius: 6px 6px 0 0;
  transition: height 0.4s ease;
}

.bar-fill.has-data {
  background: linear-gradient(180deg, var(--pink-dark, #db2777) 0%, rgba(236, 72, 153, 0.6) 100%);
}

.bar-val {
  font-size: 10px;
  font-weight: 700;
}

.bar-lbl {
  font-size: 10px;
  color: var(--muted);
}

.text-muted {
  color: var(--muted);
}

.court-util-container {
  padding: 10px 0;
}

.empty-chart-note {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
}

.empty-icon {
  font-size: 36px;
  margin-bottom: 8px;
}

.court-util-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.court-util-row {
  display: grid;
  grid-template-columns: 180px 1fr auto;
  align-items: center;
  gap: 16px;
  font-size: 13px;
}

.c-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.progress-bar-wrap {
  width: 100%;
  height: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--pink-dark, #db2777);
  border-radius: 4px;
  transition: width 0.3s;
}

.c-meta {
  font-size: 12px;
  color: var(--muted);
  white-space: nowrap;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

/* Skeleton Shimmer */
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

.skeleton-bar {
  width: 100%;
  border-radius: 6px 6px 0 0;
}

.skeleton-hour-lbl {
  width: 20px;
  height: 10px;
  margin-top: 4px;
}

@keyframes shimmer-wave {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

@media (max-width: 600px) {
  .court-util-row {
    grid-template-columns: 1fr;
    gap: 6px;
  }
}
</style>
