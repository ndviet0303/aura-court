<script setup lang="ts">
import { onMounted } from 'vue'
import { useAdminSchedule } from '../composables/useAdminSchedule'

import WeeklyScheduleCard from '../components/schedule/WeeklyScheduleCard.vue'
import SpecialDatesCard from '../components/schedule/SpecialDatesCard.vue'
import MaintenanceScheduleCard from '../components/schedule/MaintenanceScheduleCard.vue'
import DowntimeScheduleModal from '../components/schedule/DowntimeScheduleModal.vue'
import SpecialDateModal from '../components/schedule/SpecialDateModal.vue'
import ScheduleAuditLogsModal from '../components/schedule/ScheduleAuditLogsModal.vue'

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
        <h1 class="page-headline">📅 Lịch hoạt động & Giờ Mở Cửa Toàn Hệ Thống</h1>
        <p class="page-sub">Cấu hình thời gian mở sân chuẩn hàng tuần, ngày lễ Tết và lên lịch khoá bảo trì từng sân đấu.</p>
      </div>

      <div class="header-actions">
        <button class="btn-action-neutral" @click="showAuditLogsModal = true">
          🔒 Nhật Ký Kiểm Toán
        </button>
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

.page-headline {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.btn-action-neutral {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
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

@media (max-width: 900px) {
  .schedule-sub-grid {
    grid-template-columns: 1fr;
  }
}
</style>
