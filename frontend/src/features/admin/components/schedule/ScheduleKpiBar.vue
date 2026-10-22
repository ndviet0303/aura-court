<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SkeletonLoader } from '@/shared/components'

defineProps<{
  totalVenues: number
  totalCourts: number
  activeCourts: number
  totalStops: number
  specialDates: number
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="schedule-kpi-bar">
    <!-- Skeleton Loading State -->
    <template v-if="loading">
      <div v-for="i in 4" :key="i" class="kpi-card skeleton-card">
        <div class="kpi-top-row">
          <SkeletonLoader type="rect" width="20px" height="20px" rounded="4px" />
          <SkeletonLoader type="rect" width="90px" height="12px" rounded="4px" />
        </div>
        <SkeletonLoader type="rect" width="50px" height="26px" rounded="6px" class="mt-2" />
      </div>
    </template>

    <!-- Normal Metrics Cards -->
    <template v-else>
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon">location_on</span>
          <span class="kpi-lbl">{{ t('admin.schedule.kpiTotalVenues') }}</span>
        </div>
        <strong class="kpi-num">{{ totalVenues }}</strong>
      </div>

      <div class="kpi-card text-green">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-green">check_circle</span>
          <span class="kpi-lbl">{{ t('admin.schedule.kpiActiveCourts') }}</span>
        </div>
        <strong class="kpi-num">{{ activeCourts }} / {{ totalCourts }}</strong>
      </div>

      <div class="kpi-card text-yellow">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-yellow">build</span>
          <span class="kpi-lbl">{{ t('admin.schedule.kpiOpenStops') }}</span>
        </div>
        <strong class="kpi-num">{{ totalStops }}</strong>
      </div>

      <div class="kpi-card text-pink">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-pink">event</span>
          <span class="kpi-lbl">{{ t('admin.schedule.kpiSpecialDates') }}</span>
        </div>
        <strong class="kpi-num">{{ specialDates }}</strong>
      </div>
    </template>
  </div>
</template>

<style scoped>
.schedule-kpi-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px 20px;
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

.kpi-num {
  font-size: 24px;
  font-weight: 800;
}

.text-green {
  color: #10b981;
}
.text-yellow {
  color: #f59e0b;
}
.text-pink {
  color: var(--pink-dark, #db2777);
}

.mt-2 {
  margin-top: 6px;
}

@media (max-width: 900px) {
  .schedule-kpi-bar {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .schedule-kpi-bar {
    grid-template-columns: 1fr;
  }
}
</style>
