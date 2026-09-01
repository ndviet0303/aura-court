<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminSchedule } from '../composables/useAdminSchedule'

import WeeklyScheduleCard from '../components/schedule/WeeklyScheduleCard.vue'
import SpecialDatesCard from '../components/schedule/SpecialDatesCard.vue'
import MaintenanceScheduleCard from '../components/schedule/MaintenanceScheduleCard.vue'
import DowntimeScheduleModal from '../components/schedule/DowntimeScheduleModal.vue'
import SpecialDateModal from '../components/schedule/SpecialDateModal.vue'
import ScheduleAuditLogsModal from '../components/schedule/ScheduleAuditLogsModal.vue'

const { t } = useI18n()

const {
  loading,
  notice,
  error,
  courts,
  auditLogs,
  weeklySchedule,
  specialDates,
  maintenanceBlocks,
  showDowntimeModal,
  showSpecialDateModal,
  showAuditLogsModal,
  addShift,
  removeShift,
  loadData,
  addDowntime,
  addSpecialDate,
} = useAdminSchedule()

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-schedule-view">
    <!-- Header Section -->
    <header class="schedule-header">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">schedule</span>
          <h1 class="page-headline">{{ t('admin.schedule.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.schedule.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton variant="outline" class="btn-action-neutral" @click="showAuditLogsModal = true">
          <span class="material-symbols-outlined icon-18">history</span>
          <span>{{ t('admin.schedule.auditLogsBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Standard Weekly Schedule -->
    <WeeklyScheduleCard
      :schedule="weeklySchedule"
      @add-shift="addShift"
      @remove-shift="removeShift"
    />

    <!-- Special Dates & Maintenance Row -->
    <div class="schedule-sub-grid">
      <SpecialDatesCard
        :special-dates="specialDates"
        @add="showSpecialDateModal = true"
      />

      <MaintenanceScheduleCard
        :blocks="maintenanceBlocks"
        @schedule-downtime="showDowntimeModal = true"
      />
    </div>

    <!-- Modals -->
    <DowntimeScheduleModal
      :show="showDowntimeModal"
      :courts="courts"
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

.schedule-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-headline {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.schedule-sub-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.icon-18 {
  font-size: 18px;
}

@media (max-width: 900px) {
  .schedule-sub-grid {
    grid-template-columns: 1fr;
  }
}
</style>
