<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SkeletonLoader } from '@/shared/components'

defineProps<{
  totalVenues: number
  activeVenues: number
  totalCourts: number
  mapsLinked: number
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="venue-kpi-bar">
    <!-- Skeleton Loading State -->
    <template v-if="loading">
      <div v-for="i in 4" :key="i" class="kpi-card skeleton-card">
        <div class="kpi-top-row">
          <SkeletonLoader type="rect" width="20px" height="20px" rounded="4px" />
          <SkeletonLoader type="rect" width="90px" height="12px" rounded="4px" />
        </div>
        <SkeletonLoader type="rect" width="60px" height="26px" rounded="6px" class="mt-2" />
        <SkeletonLoader type="rect" width="110px" height="11px" rounded="4px" class="mt-1" />
      </div>
    </template>

    <!-- Real Metrics -->
    <template v-else>
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon">location_on</span>
          <span class="kpi-lbl">{{ t('admin.venues.kpiTotalVenues') }}</span>
        </div>
        <strong class="kpi-val">{{ totalVenues }}</strong>
        <span class="kpi-sub">{{ t('admin.venues.kpiNationalCoverage') }}</span>
      </div>

      <div class="kpi-card text-green">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-green">check_circle</span>
          <span class="kpi-lbl">{{ t('admin.venues.kpiActiveVenues') }}</span>
        </div>
        <strong class="kpi-val">{{ activeVenues }} / {{ totalVenues }}</strong>
        <span class="kpi-sub">{{ t('admin.venues.statusActive') }}</span>
      </div>

      <div class="kpi-card text-pink">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-pink">sports_tennis</span>
          <span class="kpi-lbl">{{ t('admin.venues.kpiTotalCourts') }}</span>
        </div>
        <strong class="kpi-val">{{ totalCourts }}</strong>
        <span class="kpi-sub">{{ t('admin.venues.kpiCourtsReady') }}</span>
      </div>

      <div class="kpi-card text-blue">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-blue">map</span>
          <span class="kpi-lbl">{{ t('admin.venues.kpiMapsLinked') }}</span>
        </div>
        <strong class="kpi-val">{{ mapsLinked }} / {{ totalVenues }}</strong>
        <span class="kpi-sub">{{ t('admin.venues.kpiGpsReady') }}</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.venue-kpi-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px 20px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
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
  font-size: 24px;
  font-weight: 800;
  margin-top: 2px;
}

.kpi-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-blue { color: #3b82f6; }

.mt-1 { margin-top: 4px; }
.mt-2 { margin-top: 8px; }

@media (max-width: 900px) {
  .venue-kpi-bar {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .venue-kpi-bar {
    grid-template-columns: 1fr;
  }
}
</style>
