<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton } from '@/shared/components'
import { useMonitoredVenue } from '../composables/useVenueMonitoring'
import { useSuspendVenue } from '../composables/useSuspendVenue'
import OperatingStatusBadge from '../components/lifecycle/OperatingStatusBadge.vue'
import WeeklyHoursList from '../components/detail/WeeklyHoursList.vue'
import MonitoredCourtsList from '../components/monitoring/MonitoredCourtsList.vue'
import SuspensionDecisionCard from '../components/suspension/SuspensionDecisionCard.vue'
import SuspendVenueDialog from '../components/suspension/SuspendVenueDialog.vue'

// Task 5.10: read-only venue detail for the platform admin; the only write is suspension (D13).
const route = useRoute()
const { t } = useI18n()

const venueId = computed(() => Number(route.params.venueId))
const { loading, error, venue, load } = useMonitoredVenue(venueId)
const notice = ref('')
const suspension = useSuspendVenue(async (message) => {
  notice.value = message
  await load()
})
</script>

<template>
  <div class="admin-detail-page">
    <RouterLink :to="{ name: 'admin-monitoring-venues' }" class="admin-detail-back">
      ← {{ t('admin.monitoring.backToList') }}
    </RouterLink>

    <p v-if="loading && !venue" class="admin-detail-muted">{{ t('common.loading') }}</p>
    <p v-else-if="error && !venue" class="admin-detail-error" role="alert">{{ error }}</p>

    <template v-else-if="venue">
      <header class="admin-detail-header">
        <div>
          <h1 class="admin-detail-title">{{ resolveLocalizedText(venue.name) }}</h1>
          <OperatingStatusBadge :status="venue.status" :suspension="venue.active_suspension" />
        </div>
        <AuraButton
          v-if="!venue.active_suspension"
          variant="danger"
          size="sm"
          @click="suspension.open(venue.id)"
        >
          {{ t('admin.suspension.suspendAction') }}
        </AuraButton>
      </header>

      <p v-if="notice" class="admin-detail-notice" role="status">{{ notice }}</p>

      <section class="admin-detail-card">
        <h2 class="admin-detail-section-title">{{ t('admin.monitoring.infoTitle') }}</h2>
        <dl class="admin-detail-grid">
          <dt>{{ t('admin.monitoring.columns.organization') }}</dt>
          <dd>{{ venue.organization?.name ?? '—' }}</dd>
          <dt>{{ t('admin.monitoring.owners') }}</dt>
          <dd>
            <div v-for="owner in venue.owners" :key="owner.id">
              {{ owner.name }} · {{ owner.phone ?? owner.email ?? '—' }}
            </div>
            <span v-if="!venue.owners.length">—</span>
          </dd>
          <dt>{{ t('admin.monitoring.address') }}</dt>
          <dd>{{ resolveLocalizedText(venue.address) || '—' }}</dd>
          <dt>{{ t('admin.monitoring.columns.contact') }}</dt>
          <dd>{{ [venue.phone, venue.email].filter(Boolean).join(' · ') || '—' }}</dd>
        </dl>
      </section>

      <section class="admin-detail-card">
        <h2 class="admin-detail-section-title">{{ t('admin.monitoring.hoursTitle') }}</h2>
        <WeeklyHoursList :weekly-hours="venue.operating_hours" />
      </section>

      <MonitoredCourtsList :courts="venue.courts" />

      <section class="admin-detail-card">
        <h2 class="admin-detail-section-title">{{ t('admin.suspension.historyTitle') }}</h2>
        <p v-if="!venue.suspensions.length" class="admin-detail-muted">
          {{ t('admin.suspension.noDecisions') }}
        </p>
      </section>
      <SuspensionDecisionCard v-for="item in venue.suspensions" :key="item.id" :suspension="item" />
    </template>

    <SuspendVenueDialog :state="suspension" />
  </div>
</template>
