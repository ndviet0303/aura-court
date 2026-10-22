<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraLoading } from '@/shared/components'
import { useAdminCourtDetail } from '../composables/useAdminVenueDetail'
import OperatingStatusBadge from '../components/lifecycle/OperatingStatusBadge.vue'
import DetailImageGrid from '../components/detail/DetailImageGrid.vue'
import WeeklyHoursList from '../components/detail/WeeklyHoursList.vue'

const { t } = useI18n()
const route = useRoute()
const organizationId = Number(route.params.organizationId)
const venueId = Number(route.params.venueId)

const { loading, error, court, load } = useAdminCourtDetail(
  organizationId,
  venueId,
  Number(route.params.courtId),
)

// admin.manage.surfaceWood … surfaceOther, the labels of the court form.
const surfaceLabel = computed(() => {
  const surface = court.value?.surface_type
  return surface
    ? t(`admin.manage.surface${surface.charAt(0).toUpperCase()}${surface.slice(1)}`)
    : ''
})

onMounted(load)
</script>

<template>
  <div class="detail-view">
    <RouterLink
      :to="{ name: 'admin-venue-detail', params: { organizationId, venueId } }"
      class="back-link"
    >
      <span class="material-symbols-outlined icon-18">arrow_back</span>
      <span>{{ t('admin.venueDetail.backToVenue') }}</span>
    </RouterLink>

    <AuraLoading v-if="loading && !court" :show-logo="false" />
    <div v-else-if="error && !court" class="alert-error">{{ error }}</div>

    <template v-else-if="court">
      <header class="detail-header">
        <span class="code-label">{{ t('admin.venueDetail.courtCode') }}</span>
        <h1 class="page-title">{{ court.code }}</h1>
        <OperatingStatusBadge :status="court.status" />
      </header>

      <div class="detail-grid">
        <section class="detail-card">
          <dl class="info-list">
            <div class="info-row">
              <dt>{{ t('admin.venueDetail.sport') }}</dt>
              <dd>{{ t(`sportTypes.${court.sport_type}`) }}</dd>
            </div>
            <div v-if="surfaceLabel" class="info-row">
              <dt>{{ t('admin.venueDetail.surface') }}</dt>
              <dd>{{ surfaceLabel }}</dd>
            </div>
            <div v-if="court.capacity" class="info-row">
              <dt>{{ t('admin.venueDetail.capacity') }}</dt>
              <dd>{{ t('admin.manage.courtCapacityValue', { count: court.capacity }) }}</dd>
            </div>
            <div class="info-row is-block">
              <dt>{{ t('admin.venueDetail.description') }}</dt>
              <dd class="description">
                {{ resolveLocalizedText(court.description) || t('admin.venueDetail.notSet') }}
              </dd>
            </div>
            <div class="info-row is-block">
              <dt>{{ t('admin.venueDetail.images') }}</dt>
              <dd><DetailImageGrid :images="court.images" :alt="court.code" /></dd>
            </div>
          </dl>
        </section>

        <section class="detail-card">
          <h2 class="card-title">{{ t('admin.venueDetail.serviceTitle') }}</h2>
          <p v-if="court.service_schedule" class="schedule-source">
            {{
              court.service_schedule_inherited
                ? t('admin.venueDetail.inheritedSchedule')
                : t('admin.venueDetail.ownSchedule')
            }}
          </p>
          <WeeklyHoursList :weekly-hours="court.service_schedule?.weekly_hours" />
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.detail-view {
  display: flex;
  flex-direction: column;
  gap: 18px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  align-self: flex-start;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  text-decoration: none;
}

.back-link:hover {
  color: var(--ink);
}

.detail-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.code-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
  gap: 18px;
}

.detail-card {
  padding: 18px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-title {
  margin: 0 0 8px;
  font-size: 15px;
  font-weight: 800;
}

.schedule-source {
  margin: 0 0 10px;
  font-size: 12px;
  color: var(--muted);
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
}

.info-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 12px;
  font-size: 13px;
}

.info-row.is-block {
  grid-template-columns: 1fr;
  gap: 6px;
}

.info-row dt {
  font-weight: 700;
  color: var(--muted);
}

.info-row dd {
  margin: 0;
}

.description {
  white-space: pre-line;
  line-height: 1.5;
}

.alert-error {
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  background: #ef4444;
}

.icon-18 {
  font-size: 18px;
}

@media (max-width: 900px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
