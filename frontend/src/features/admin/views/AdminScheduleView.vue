<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ErrorModal } from '@/shared/components'
import { useAdminSchedule, type CourtStop } from '../composables/useAdminSchedule'
import { useLifecycleCommands } from '../composables/useLifecycleCommands'

import ScheduleHeader from '../components/schedule/ScheduleHeader.vue'
import ScheduleKpiBar from '../components/schedule/ScheduleKpiBar.vue'
import WeeklyScheduleCard from '../components/schedule/WeeklyScheduleCard.vue'
import MaintenanceScheduleCard from '../components/schedule/MaintenanceScheduleCard.vue'
import SpecialDatesCard from '../components/schedule/SpecialDatesCard.vue'
import OperationalStopDialog from '../components/lifecycle/OperationalStopDialog.vue'
import AffectedBookingsDialog from '../components/affected/AffectedBookingsDialog.vue'
import SpecialDateModal from '../components/schedule/SpecialDateModal.vue'
import ScheduleAuditLogsModal from '../components/schedule/ScheduleAuditLogsModal.vue'
import CourtUnlockConfirmModal from '../components/schedule/CourtUnlockConfirmModal.vue'

const {
  loading,
  saving,
  notice,
  error,
  venues,
  courts,
  auditLogs,
  selectedVenueFilter,
  selectedScheduleCourtId,
  weeklySchedule,
  specialDates,
  showSpecialDateModal,
  showAuditLogsModal,
  affected,
  filteredCourts,
  filteredStops,
  totalVenuesCount,
  totalCourtsCount,
  activeCourtsCount,
  specialDatesCount,
  loadData,
  showNotice,
  addShift,
  removeShift,
  toggleDayOpen,
  copySchedule,
  saveWeeklySchedule,
  addSpecialDate,
  deleteSpecialDate,
} = useAdminSchedule()

const stopToResume = ref<CourtStop | null>(null)
const lifecycle = reactive(
  useLifecycleCommands(async (message) => {
    stopToResume.value = null
    await loadData()
    showNotice(message)
  }),
)
// Only an active court can be paused.
const pausableCourts = computed(() => filteredCourts.value.filter((c) => c.status === 'active'))
// The business kind needs the owner of every listed court; a staff venue in the list limits it to maintenance.
const ownsPausable = computed(() =>
  pausableCourts.value.every((c) => lifecycle.isOwnerOf(c.venue?.organization_id)),
)

function confirmResume() {
  const courtId = stopToResume.value?.court_id
  if (courtId) void lifecycle.run({ type: 'court', id: courtId }, 'resume')
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-schedule-view">
    <!-- Header Section -->
    <ScheduleHeader
      :venues="venues"
      :courts="courts"
      :selected-venue-id="selectedVenueFilter"
      :selected-calendar-court-id="selectedScheduleCourtId"
      :saving="saving"
      :loading="loading"
      @update:selected-venue-id="selectedVenueFilter = $event"
      @update:selected-calendar-court-id="selectedScheduleCourtId = $event"
      @open-audit-logs="showAuditLogsModal = true"
      @save-schedule="saveWeeklySchedule(true)"
      @refresh="loadData"
    />

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- KPI Summary Bar -->
    <ScheduleKpiBar
      :total-venues="totalVenuesCount"
      :total-courts="totalCourtsCount"
      :active-courts="activeCourtsCount"
      :total-stops="filteredStops.length"
      :special-dates="specialDatesCount"
      :loading="loading"
    />

    <!-- Standard Weekly Operating Schedule -->
    <WeeklyScheduleCard
      :schedule="weeklySchedule"
      :loading="loading"
      @add-shift="addShift"
      @remove-shift="removeShift"
      @toggle-open="toggleDayOpen"
      @copy-schedule="copySchedule"
    />

    <!-- Court Maintenance & Special Dates Grid -->
    <div class="schedule-sub-grid">
      <MaintenanceScheduleCard
        :stops="filteredStops"
        :owner-of="lifecycle.isOwnerOf"
        :loading="loading"
        @schedule-downtime="lifecycle.requestStop('pause')"
        @resume-court="stopToResume = $event"
      />

      <SpecialDatesCard
        :special-dates="specialDates"
        :loading="loading"
        @add="showSpecialDateModal = true"
        @delete="deleteSpecialDate"
      />
    </div>

    <!-- Modals -->
    <OperationalStopDialog
      :command="lifecycle.stopRequest?.command ?? null"
      target="court"
      :owner="ownsPausable"
      :courts="pausableCourts"
      :loading="lifecycle.busy"
      @close="lifecycle.closeStop"
      @submit="lifecycle.submitStop"
    />
    <AffectedBookingsDialog :state="lifecycle.affected" />
    <AffectedBookingsDialog :state="affected" />

    <SpecialDateModal
      :show="showSpecialDateModal"
      @close="showSpecialDateModal = false"
      @submit="addSpecialDate"
    />

    <ScheduleAuditLogsModal
      :show="showAuditLogsModal"
      :logs="auditLogs"
      @close="showAuditLogsModal = false"
    />

    <CourtUnlockConfirmModal
      :block="stopToResume"
      :loading="lifecycle.busy"
      @close="stopToResume = null"
      @confirm="confirmResume"
    />
    <ErrorModal
      :open="lifecycle.failureOpen"
      v-bind="lifecycle.failureModal"
      @close="lifecycle.closeFailure"
    />
  </div>
</template>

<style scoped>
.admin-schedule-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.alert-pill {
  padding: 10px 18px;
  border-radius: 10px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  animation: slide-in 0.2s ease-out;
}

.alert-pill-error {
  padding: 10px 18px;
  border-radius: 10px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  animation: slide-in 0.2s ease-out;
}

.schedule-sub-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

@keyframes slide-in {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 900px) {
  .schedule-sub-grid {
    grid-template-columns: 1fr;
  }
}
</style>
