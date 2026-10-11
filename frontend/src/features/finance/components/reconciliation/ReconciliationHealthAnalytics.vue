<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { HealthTrendPoint } from '../../types/reconciliation'

defineProps<{
  healthTrend: HealthTrendPoint[]
  matchRate: number
  loading?: boolean
}>()

const { t } = useI18n()

const liveLogs = computed(() => [
  {
    time: '14:02:15',
    title: t('admin.reconciliation.liveLogSyncTitle'),
    desc: t('admin.reconciliation.liveLogSyncDesc', { count: '1,450' }),
    type: 'sync',
  },
  {
    time: '12:45:00',
    title: t('admin.reconciliation.liveLogAutoTitle'),
    desc: t('admin.reconciliation.liveLogAutoDesc', { amount: '10,000 ₫', code: 'TXN-88288D' }),
    type: 'auto_resolve',
  },
  {
    time: '09:15:32',
    title: t('admin.reconciliation.liveLogWarnTitle'),
    desc: t('admin.reconciliation.liveLogWarnDesc', { amount: '1.4M ₫', code: 'TXN-88291A' }),
    type: 'mismatch',
  },
  {
    time: '06:00:00',
    title: t('admin.reconciliation.liveLogStartTitle'),
    desc: t('admin.reconciliation.liveLogStartDesc'),
    type: 'sync',
  },
])
</script>

<template>
  <div class="analytics-layout">
    <!-- Left Column: 7-Day Trend Chart & Metrics -->
    <div class="analytics-card">
      <div class="card-header">
        <div class="header-titles">
          <span class="material-symbols-outlined header-icon">monitoring</span>
          <h3 class="card-title">{{ t('admin.reconciliation.analyticsTrendTitle') }}</h3>
        </div>
        <span class="rate-badge">{{
          t('admin.reconciliation.matchRateAccuracy', { rate: matchRate })
        }}</span>
      </div>

      <!-- Trend Visualization -->
      <div class="chart-container">
        <template v-if="loading">
          <div class="bars-wrapper">
            <div v-for="i in 7" :key="i" class="bar-column">
              <div class="bar-track skeleton-track"></div>
              <span class="skeleton-date"></span>
            </div>
          </div>
        </template>
        <template v-else>
          <div class="bars-wrapper">
            <div v-for="(point, idx) in healthTrend" :key="idx" class="bar-column">
              <div class="bar-track">
                <div
                  class="bar-fill"
                  :class="{
                    'bar-ok': point.status === 'matched',
                    'bar-warn': point.status === 'mismatch',
                  }"
                  :style="{ height: point.status === 'matched' ? '92%' : '65%' }"
                  :title="`${point.date}: ${point.status === 'matched' ? '100% Khớp' : 'Có chênh lệch'}`"
                ></div>
              </div>
              <span class="bar-date">{{ point.date.slice(5) }}</span>
            </div>
          </div>
        </template>
      </div>

      <div class="chart-footer">
        <div class="legend-item">
          <span class="legend-dot dot-ok"></span>
          <span>{{ t('admin.reconciliation.legendMatched') }}</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot dot-warn"></span>
          <span>{{ t('admin.reconciliation.legendMismatch') }}</span>
        </div>
      </div>
    </div>

    <!-- Right Column: Live Event Audit Stream -->
    <div class="analytics-card">
      <div class="card-header">
        <div class="header-titles">
          <span class="material-symbols-outlined header-icon">history_edu</span>
          <h3 class="card-title">{{ t('admin.reconciliation.liveStreamTitle') }}</h3>
        </div>
        <span class="live-dot-wrap">
          <span class="live-pulse"></span>
          {{ t('admin.reconciliation.liveBadge') }}
        </span>
      </div>

      <!-- Stream List -->
      <div class="stream-list">
        <template v-if="loading">
          <div v-for="i in 4" :key="`skel-log-${i}`" class="stream-item skeleton-log">
            <div class="skeleton-icon-box"></div>
            <div class="skeleton-log-body">
              <div class="skeleton-log-line w-32 h-3"></div>
              <div class="skeleton-log-line w-48 h-2 mt-1"></div>
            </div>
          </div>
        </template>
        <template v-else>
          <div v-for="(log, idx) in liveLogs" :key="idx" class="stream-item">
            <div class="stream-icon-box" :class="`icon-${log.type}`">
              <span class="material-symbols-outlined text-[16px]">
                {{
                  log.type === 'sync' ? 'sync' : log.type === 'auto_resolve' ? 'rule' : 'warning'
                }}
              </span>
            </div>
            <div class="stream-details">
              <div class="stream-top">
                <span class="stream-item-title">{{ log.title }}</span>
                <span class="stream-time">{{ log.time }}</span>
              </div>
              <p class="stream-desc">{{ log.desc }}</p>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.analytics-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 900px) {
  .analytics-layout {
    grid-template-columns: 1fr;
  }
}

.analytics-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e1e3e4);
  padding: 18px 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--outline, #f3f4f5);
  padding-bottom: 12px;
}

.header-titles {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.card-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.rate-badge {
  font-size: 12px;
  font-weight: 700;
  background: var(--primary-container, #f8e8ec);
  color: var(--on-primary-container, #4e1827);
  padding: 3px 10px;
  border-radius: 999px;
}

.live-dot-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  color: #059669;
}

.live-pulse {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.5;
    transform: scale(1.2);
  }
}

.chart-container {
  height: 140px;
  display: flex;
  align-items: flex-end;
  padding: 10px 0;
}

.bars-wrapper {
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 12px;
}

.bar-column {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  gap: 6px;
}

.bar-track {
  flex: 1;
  width: 100%;
  max-width: 28px;
  background: var(--soft, #f3f4f5);
  border-radius: 6px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.bar-fill {
  width: 100%;
  border-radius: 6px 6px 0 0;
  transition: height 0.4s ease;
}

.bar-ok {
  background: var(--pink-dark, #db2777);
}

.bar-warn {
  background: #ba1a1a;
}

.bar-date {
  font-size: 11px;
  color: var(--muted, #5d5f5f);
  font-weight: 500;
}

.chart-footer {
  display: flex;
  align-items: center;
  gap: 16px;
  font-size: 12px;
  color: var(--muted, #5d5f5f);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.dot-ok {
  background: var(--pink-dark, #db2777);
}

.dot-warn {
  background: #ba1a1a;
}

.stream-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.stream-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 10px;
  background: var(--soft, #f8f9fa);
  border: 1px solid var(--outline, #f3f4f5);
}

.stream-icon-box {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-sync {
  background: #e6f4ea;
  color: #137333;
}

:global(html.dark) .icon-sync {
  background: #133820;
  color: #4ade80;
}

.icon-auto_resolve {
  background: var(--primary-container, #f8e8ec);
  color: var(--on-primary-container, #4e1827);
}

.icon-mismatch {
  background: #ffdad6;
  color: #ba1a1a;
}

:global(html.dark) .icon-mismatch {
  background: #49171b;
  color: #fca5a5;
}

.stream-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stream-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stream-item-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.stream-time {
  font-size: 11px;
  color: var(--muted, #817476);
  font-family: monospace;
}

.stream-desc {
  font-size: 11.5px;
  color: var(--muted, #5d5f5f);
  margin: 0;
}

/* Skeleton Analytics */
.skeleton-track {
  animation: shimmer 1.5s infinite;
}
.skeleton-date {
  width: 24px;
  height: 10px;
  border-radius: 4px;
  background: var(--soft, #eff1f4);
}
.skeleton-log {
  min-height: 48px;
}
.skeleton-icon-box {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--soft, #eff1f4);
  animation: shimmer 1.5s infinite;
}
.skeleton-log-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.skeleton-log-line {
  background: var(--soft, #eff1f4);
  border-radius: 4px;
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% {
    opacity: 0.6;
  }
  50% {
    opacity: 1;
  }
  100% {
    opacity: 0.6;
  }
}
</style>
