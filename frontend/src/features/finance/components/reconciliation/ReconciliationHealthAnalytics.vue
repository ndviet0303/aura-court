<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { HealthTrendPoint } from '../../types/reconciliation'

defineProps<{
  healthTrend: HealthTrendPoint[]
  matchRate: number | null
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="analytics-card">
    <div class="card-header">
      <div class="header-titles">
        <span class="material-symbols-outlined header-icon">monitoring</span>
        <h3 class="card-title">{{ t('admin.reconciliation.analyticsTrendTitle') }}</h3>
      </div>
      <span v-if="matchRate !== null" class="rate-badge">{{
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
              <!-- Days without a reconciliation run keep an empty track. -->
              <div
                v-if="point.status"
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
      <div class="legend-item">
        <span class="legend-dot dot-none"></span>
        <span>{{ t('admin.reconciliation.legendNoRun') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
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

.dot-none {
  background: var(--soft, #f3f4f5);
  border: 1px solid var(--outline, #e1e3e4);
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
