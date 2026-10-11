<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { SkeletonLoader } from '@/shared/components'

defineProps<{
  totalRules: number
  activeRules: number
  peakRules: number
  holidayRules: number
  averagePrice: number
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="pricing-kpi-bar">
    <!-- Skeleton Loading State -->
    <template v-if="loading">
      <div v-for="i in 5" :key="i" class="kpi-card skeleton-card">
        <div class="kpi-top-row">
          <SkeletonLoader type="rect" width="20px" height="20px" rounded="4px" />
          <SkeletonLoader type="rect" width="80px" height="12px" rounded="4px" />
        </div>
        <SkeletonLoader type="rect" width="60px" height="24px" rounded="6px" class="mt-2" />
        <SkeletonLoader type="rect" width="100px" height="11px" rounded="4px" class="mt-1" />
      </div>
    </template>

    <!-- Real Metrics -->
    <template v-else>
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon">tune</span>
          <span class="kpi-lbl">{{ t('admin.pricing.kpiTotalRules') }}</span>
        </div>
        <strong class="kpi-val">{{ totalRules }}</strong>
        <span class="kpi-sub">{{ t('admin.pricing.kpiConfiguredRules') }}</span>
      </div>

      <div class="kpi-card text-green">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-green">check_circle</span>
          <span class="kpi-lbl">{{ t('admin.pricing.kpiActiveRules') }}</span>
        </div>
        <strong class="kpi-val">{{ activeRules }} / {{ totalRules }}</strong>
        <span class="kpi-sub">{{ t('admin.pricing.kpiLiveNow') }}</span>
      </div>

      <div class="kpi-card text-pink">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-pink">trending_up</span>
          <span class="kpi-lbl">{{ t('admin.pricing.kpiPeakRules') }}</span>
        </div>
        <strong class="kpi-val">{{ peakRules }}</strong>
        <span class="kpi-sub">{{ t('admin.pricing.kpiPrimeTimeSurge') }}</span>
      </div>

      <div class="kpi-card text-amber">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-amber">celebration</span>
          <span class="kpi-lbl">{{ t('admin.pricing.kpiHolidayRules') }}</span>
        </div>
        <strong class="kpi-val">{{ holidayRules }}</strong>
        <span class="kpi-sub">{{ t('admin.pricing.kpiSpecialDays') }}</span>
      </div>

      <div class="kpi-card text-blue">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-blue">price_check</span>
          <span class="kpi-lbl">{{ t('admin.pricing.kpiAvgBasePrice') }}</span>
        </div>
        <strong class="kpi-val font-mono">{{ formatCurrency(averagePrice) }}</strong>
        <span class="kpi-sub">{{ t('admin.pricing.kpiPerSlot') }}</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.pricing-kpi-bar {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px 18px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.kpi-top-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.kpi-icon {
  font-size: 20px;
  color: var(--muted);
}

.kpi-lbl {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.kpi-val {
  font-size: 22px;
  font-weight: 800;
  margin-top: 2px;
}

.kpi-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}
.text-green {
  color: #10b981;
}
.text-blue {
  color: #3b82f6;
}
.text-amber {
  color: #f59e0b;
}

.mt-1 {
  margin-top: 4px;
}
.mt-2 {
  margin-top: 8px;
}

@media (max-width: 1100px) {
  .pricing-kpi-bar {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .pricing-kpi-bar {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .pricing-kpi-bar {
    grid-template-columns: 1fr;
  }
}
</style>
