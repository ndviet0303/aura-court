<script setup lang="ts">
import type { AdminReport } from '../../composables/useAdminDashboard'

defineProps<{
  report: AdminReport
  chartTab: 'hourly' | 'court'
}>()

const emit = defineEmits<{
  (e: 'update:chartTab', tab: 'hourly' | 'court'): void
}>()
</script>

<template>
  <div class="analytics-charts-card">
    <div class="card-header">
      <div class="title-row">
        <h3 class="title">📊 Phân Tích Hiệu Suất & Khung Giờ Cao Điểm</h3>
        <div class="chart-tab-switcher">
          <button
            type="button"
            class="c-tab"
            :class="{ active: chartTab === 'hourly' }"
            @click="emit('update:chartTab', 'hourly')"
          >
            Theo Giờ Trong Ngày
          </button>
          <button
            type="button"
            class="c-tab"
            :class="{ active: chartTab === 'court' }"
            @click="emit('update:chartTab', 'court')"
          >
            Theo Sân Đấu
          </button>
        </div>
      </div>
    </div>

    <!-- Hourly Distribution Chart -->
    <div v-if="chartTab === 'hourly'" class="chart-bars-wrap">
      <div v-for="h in report.byHour || []" :key="h.hour" class="bar-col">
        <div class="bar-fill-track">
          <div class="bar-fill" :style="{ height: `${Math.min(h.bookings * 3, 100)}%` }"></div>
        </div>
        <span class="bar-val font-mono">{{ h.bookings }}</span>
        <span class="bar-lbl font-mono">{{ h.hour }}</span>
      </div>
    </div>

    <!-- By Court Utilization -->
    <div v-else class="court-util-list">
      <div v-for="c in report.byCourt || []" :key="c.court_id" class="court-util-row">
        <strong class="c-name">{{ c.court?.name || `Sân #${c.court_id}` }}</strong>
        <div class="progress-bar-wrap">
          <div class="progress-fill" :style="{ width: `${Math.min((c.bookings / 50) * 100, 100)}%` }"></div>
        </div>
        <div class="c-meta">
          <span>{{ c.bookings }} lượt đặt</span> · 
          <strong class="text-pink">{{ (c.revenue || 0).toLocaleString('vi-VN') }} ₫</strong>
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
}

.c-tab.active {
  background: var(--surface);
  color: var(--ink);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.chart-bars-wrap {
  display: flex;
  justify-content: space-around;
  align-items: flex-end;
  height: 180px;
  padding-top: 20px;
  border-bottom: 1px solid var(--outline);
}

.bar-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  height: 100%;
  width: 44px;
}

.bar-fill-track {
  flex: 1;
  width: 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  border-radius: 8px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.bar-fill {
  width: 100%;
  background: var(--pink-dark, #db2777);
  border-radius: 8px;
  transition: height 0.3s ease;
}

.bar-val {
  font-size: 10px;
  font-weight: 700;
}

.bar-lbl {
  font-size: 10px;
  color: var(--muted);
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
