<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraLoading, ErrorModal } from '@/shared/components'
import type { Court } from '@/features/court/types/court'
import { useAdminVenueDetail } from '../composables/useAdminVenueDetail'
import { useLifecycleCommands } from '../composables/useLifecycleCommands'
import type { LifecycleCommand } from '../types/lifecycle'
import OperatingStatusBadge from '../components/lifecycle/OperatingStatusBadge.vue'
import OperationalStopDialog from '../components/lifecycle/OperationalStopDialog.vue'
import AffectedBookingsDialog from '../components/affected/AffectedBookingsDialog.vue'
import VenueInfoCard from '../components/detail/VenueInfoCard.vue'
import WeeklyHoursList from '../components/detail/WeeklyHoursList.vue'
import ScheduleExceptionsList from '../components/detail/ScheduleExceptionsList.vue'
import VenueCourtsList from '../components/detail/VenueCourtsList.vue'

const { t } = useI18n()
const route = useRoute()
const organizationId = Number(route.params.organizationId)
const venueId = Number(route.params.venueId)

const { loading, error, venue, hours, exceptions, courts, load } = useAdminVenueDetail(
  organizationId,
  venueId,
)
const notice = ref('')

// Read-only page: the only actions are court open/pause/resume/close for the owner and the
// maintenance pause pair for staff, both gated by LifecycleActions and enforced by the backend.
const lifecycle = reactive(
  useLifecycleCommands(async (message) => {
    await load()
    notice.value = message
  }),
)

function handleCommand(court: Court, command: LifecycleCommand) {
  void lifecycle.run({ type: 'court', id: court.id }, command)
}

onMounted(load)
</script>

<template>
  <div class="detail-view">
    <RouterLink to="/admin/venues" class="back-link">
      <span class="material-symbols-outlined icon-18">arrow_back</span>
      <span>{{ t('admin.venueDetail.backToList') }}</span>
    </RouterLink>

    <AuraLoading v-if="loading && !venue" :show-logo="false" />
    <div v-else-if="error && !venue" class="alert-error">{{ error }}</div>

    <template v-else-if="venue">
      <header class="detail-header">
        <h1 class="page-title">{{ resolveLocalizedText(venue.name) }}</h1>
        <OperatingStatusBadge :status="venue.status" />
      </header>
      <div v-if="notice" class="alert-notice">{{ notice }}</div>

      <div class="detail-grid">
        <section class="detail-card">
          <h2 class="card-title">{{ t('admin.venueDetail.infoTitle') }}</h2>
          <VenueInfoCard :venue="venue" />
        </section>

        <div class="detail-column">
          <section class="detail-card">
            <h2 class="card-title">{{ t('admin.venueDetail.hoursTitle') }}</h2>
            <WeeklyHoursList :weekly-hours="hours?.weekly_hours" />
          </section>
          <section class="detail-card">
            <h2 class="card-title">{{ t('admin.venueDetail.exceptionsTitle') }}</h2>
            <ScheduleExceptionsList :exceptions="exceptions" />
          </section>
        </div>
      </div>

      <section class="detail-card">
        <h2 class="card-title">{{ t('admin.venueDetail.courtsTitle') }}</h2>
        <VenueCourtsList
          :courts="courts"
          :organization-id="organizationId"
          :venue-id="venueId"
          :owner="lifecycle.isOwnerOf(organizationId)"
          :busy="lifecycle.busy"
          @command="handleCommand"
        />
      </section>
    </template>

    <OperationalStopDialog
      :command="lifecycle.stopRequest?.command ?? null"
      target="court"
      :owner="lifecycle.isOwnerOf(organizationId)"
      :loading="lifecycle.busy"
      @close="lifecycle.closeStop"
      @submit="lifecycle.submitStop"
    />
    <AffectedBookingsDialog :state="lifecycle.affected" />
    <ErrorModal
      :open="lifecycle.failureOpen"
      v-bind="lifecycle.failureModal"
      @close="lifecycle.closeFailure"
    />
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

.detail-column {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.detail-card {
  padding: 18px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-title {
  margin: 0 0 12px;
  font-size: 15px;
  font-weight: 800;
}

.alert-error,
.alert-notice {
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  background: #ef4444;
}

.alert-notice {
  background: #10b981;
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
