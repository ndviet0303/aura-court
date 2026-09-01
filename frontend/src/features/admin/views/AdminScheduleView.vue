<script setup lang="ts">
import { onMounted } from 'vue'
import { useAdminSchedule } from '../composables/useAdminSchedule'

import ScheduleHeader from '../components/schedule/ScheduleHeader.vue'
import ScheduleKpiBar from '../components/schedule/ScheduleKpiBar.vue'
import WeeklyScheduleCard from '../components/schedule/WeeklyScheduleCard.vue'
import MaintenanceScheduleCard from '../components/schedule/MaintenanceScheduleCard.vue'
import SpecialDatesCard from '../components/schedule/SpecialDatesCard.vue'
import DowntimeScheduleModal from '../components/schedule/DowntimeScheduleModal.vue'
import SpecialDateModal from '../components/schedule/SpecialDateModal.vue'
import ScheduleAuditLogsModal from '../components/schedule/ScheduleAuditLogsModal.vue'
import CourtUnlockConfirmModal from '../components/schedule/CourtUnlockConfirmModal.vue'

const {
  loading,
  saving,
  notice,
  error,
  venues,
  auditLogs,
  selectedVenueFilter,
  weeklySchedule,
  specialDates,
  showDowntimeModal,
  showSpecialDateModal,
  showAuditLogsModal,
  showUnlockConfirmModal,
  blockToUnlock,
  filteredCourts,
  filteredMaintenanceBlocks,
  totalVenuesCount,
  totalCourtsCount,
  activeCourtsCount,
  totalBlocksCount,
  specialDatesCount,
  loadData,
  addShift,
  removeShift,
  toggleDayOpen,
  copySchedule,
  saveWeeklySchedule,
  addDowntime,
  promptUnlockCourt,
  confirmUnlockCourt,
  addSpecialDate,
  deleteSpecialDate,
} = useAdminSchedule()

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-schedule-view">
    <!-- Header Section -->
    <ScheduleHeader
      :venues="venues"
      :selected-venue-id="selectedVenueFilter"
      :saving="saving"
      :loading="loading"
      @update:selected-venue-id="selectedVenueFilter = $event"
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
      :total-blocks="totalBlocksCount"
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
        :blocks="filteredMaintenanceBlocks"
        :loading="loading"
        @schedule-downtime="showDowntimeModal = true"
        @unlock-court="promptUnlockCourt"
      />

      <SpecialDatesCard
        :special-dates="specialDates"
        :loading="loading"
        @add="showSpecialDateModal = true"
        @delete="deleteSpecialDate"
      />
    </div>

    <!-- Modals -->
    <DowntimeScheduleModal
      :show="showDowntimeModal"
      :courts="filteredCourts"
      :loading="loading"
      @close="showDowntimeModal = false"
      @submit="addDowntime"
    />

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
      :show="showUnlockConfirmModal"
      :block="blockToUnlock"
      :loading="loading"
      @close="showUnlockConfirmModal = false"
      @confirm="confirmUnlockCourt"
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
