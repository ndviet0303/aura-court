<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import type { AdminReport } from '../../composables/useAdminDashboard'

const props = defineProps<{
  report: AdminReport
  chartTab: 'hourly' | 'court'
}>()

const emit = defineEmits<{
  (e: 'update:chartTab', tab: 'hourly' | 'court'): void
}>()

const { t } = useI18n()

// Generate standard facility operating hours (06:00 - 22:00)
const hourlyData = computed(() => {
  const map: Record<string, number> = {}
  if (Array.isArray(props.report.byHour)) {
    for (const h of props.report.byHour) {
      if (h && h.hour) {
        // Format to HH:00 if needed
        const formattedHour = h.hour.length === 2 ? `${h.hour}:00` : h.hour
        map[formattedHour] = h.bookings || 0
      }
    }
  }

  const standardHours = [
    '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
    '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
    '18:00', '19:00', '20:00', '21:00', '22:00',
  ]

  const maxVal = Math.max(...Object.values(map), 1)

  return standardHours.map((hour) => {
    const bookings = map[hour] || 0
    const pct = Math.round((bookings / maxVal) * 100)
    return {
      hour,
      bookings,
      heightPercent: bookings > 0 ? Math.max(pct, 12) : 4,
    }
  })
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
          <h3 class="title">{{ t('admin.dashboard.performanceAnalytics') }}</h3>
        </div>
        <div class="chart-tab-switcher">
          <button
            type="button"
            class="c-tab"
            :class="{ active: chartTab === 'hourly' }"
            @click="emit('update:chartTab', 'hourly')"
          >
            {{ t('admin.dashboard.byHour') }}
          </button>
          <button
            type="button"
            class="c-tab"
            :class="{ active: chartTab === 'court' }"
            @click="emit('update:chartTab', 'court')"
          >
            {{ t('admin.dashboard.byCourt') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Hourly Distribution Chart -->
    <div v-if="chartTab === 'hourly'" class="chart-bars-wrap">
      <div v-for="h in hourlyData" :key="h.hour" class="bar-col">
        <div class="bar-fill-track">
          <div
            class="bar-fill"
            :class="{ 'has-data': h.bookings > 0 }"
            :style="{ height: `${h.heightPercent}%` }"
          ></div>
        </div>
        <span class="bar-val font-mono" :class="{ 'text-muted': h.bookings === 0 }">{{ h.bookings }}</span>
        <span class="bar-lbl font-mono">{{ h.hour }}</span>
      </div>
    </div>

    <!-- By Court Utilization -->
    <div v-else class="court-util-container">
      <div v-if="!report.byCourt || report.byCourt.length === 0" class="empty-chart-note">
        <span class="material-symbols-outlined empty-icon">bar_chart</span>
        <p>{{ t('admin.dashboard.noCourtChartData') }}</p>
      </div>

      <div v-else class="court-util-list">
        <div v-for="c in report.byCourt" :key="c.court_id" class="court-util-row">
          <strong class="c-name">{{ resolveLocalizedText(c.court?.name) || `Sân #${c.court_id}` }}</strong>
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
  gap: 18px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
}

.title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
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

.chart-tab-switcher {
  display: flex;
  gap: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  padding: 3px;
  border-radius: 8px;
}

.c-tab {
  padding: 4px 10px;
  border-radius: 6px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
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
  height: 180px;
  padding-top: 20px;
  border-bottom: 1px solid var(--outline);
  overflow-x: auto;
  gap: 6px;
}

.bar-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  height: 100%;
  min-width: 32px;
  flex: 1;
}

.bar-fill-track {
  flex: 1;
  width: 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  border-radius: 7px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.bar-fill {
  width: 100%;
  background: var(--outline, #e2e8f0);
  border-radius: 7px;
  transition: height 0.3s ease;
}

.bar-fill.has-data {
  background: var(--pink-dark, #db2777);
}

.bar-val {
  font-size: 10px;
  font-weight: 700;
}

.bar-lbl {
  font-size: 9.5px;
  color: var(--muted);
  white-space: nowrap;
}

.text-muted {
  color: var(--muted);
  opacity: 0.5;
}

.court-util-container {
  min-height: 120px;
}

.empty-chart-note {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 16px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
}

.empty-icon {
  font-size: 36px;
  opacity: 0.5;
  margin-bottom: 8px;
}

.court-util-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.court-util-row {
  display: grid;
  grid-template-columns: 160px 1fr 180px;
  align-items: center;
  gap: 16px;
  font-size: 13px;
}

.progress-bar-wrap {
  height: 10px;
  border-radius: 5px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--pink-dark, #db2777);
  border-radius: 5px;
}

.c-meta {
  text-align: right;
  font-size: 12px;
}

.text-pink { color: var(--pink-dark, #db2777); }
</style>
