<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { MonitoredVenueDetail } from '../../types/suspension'
import OperatingStatusBadge from '../lifecycle/OperatingStatusBadge.vue'
import WeeklyHoursList from '../detail/WeeklyHoursList.vue'

defineProps<{ courts: MonitoredVenueDetail['courts'] }>()

const { t } = useI18n()
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('admin.monitoring.courtsTitle') }}</h2>
    <p v-if="!courts.length" class="admin-detail-muted">{{ t('admin.monitoring.noCourts') }}</p>
    <details v-for="court in courts" :key="court.id" class="monitored-court">
      <summary class="monitored-court-head">
        <strong>{{ court.code }}</strong>
        <span class="admin-detail-muted">{{ t(`sportTypes.${court.sport_type}`) }}</span>
        <OperatingStatusBadge :status="court.status" />
      </summary>
      <p v-if="court.description" class="admin-detail-muted">{{ court.description }}</p>
      <div v-if="court.images.length" class="monitored-court-images">
        <img
          v-for="image in court.images"
          :key="image"
          :src="image"
          :alt="court.code"
          loading="lazy"
        />
      </div>
      <p class="admin-detail-muted">
        {{
          t(
            court.schedule_inherited
              ? 'admin.monitoring.scheduleInherited'
              : 'admin.monitoring.scheduleOwn',
          )
        }}
      </p>
      <WeeklyHoursList :weekly-hours="court.service_schedule" />
    </details>
  </section>
</template>

<style scoped>
.monitored-court {
  padding: 10px 0;
  border-top: 1px solid var(--outline);
}

.monitored-court-head {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.monitored-court-images {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 8px 0;
}

.monitored-court-images img {
  width: 96px;
  height: 64px;
  object-fit: cover;
  border-radius: 8px;
}
</style>
