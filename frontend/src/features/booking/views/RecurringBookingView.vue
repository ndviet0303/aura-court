<script setup lang="ts">
import { onMounted } from 'vue'
import { useRecurringBooking } from '../composables/useRecurringBooking'

import RecurringWizardCard from '../components/recurring/RecurringWizardCard.vue'
import RecurringSeriesManager from '../components/recurring/RecurringSeriesManager.vue'

const {
  courts,
  seriesList,
  loading,
  submitting,
  notice,
  error,
  selectedCourtId,
  frequency,
  selectedWeekdays,
  startTime,
  endTime,
  startDate,
  endDate,
  weekdayButtons,
  toggleWeekday,
  loadData,
  submitSeries,
} = useRecurringBooking()

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="recurring-booking-view-page">
    <!-- Header Hero -->
    <header class="recurring-header">
      <div class="header-titles">
        <h1 class="page-title">🏸 Đặt Sân Cố Định & Quản Lý Lịch Định Kỳ</h1>
        <p class="page-sub">Đăng ký lịch chơi cố định hàng tuần cho cá nhân, câu lạc bộ hoặc doanh nghiệp.</p>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <div class="recurring-content-grid">
      <!-- Wizard Card (Left Column) -->
      <RecurringWizardCard
        :courts="courts"
        :selected-court-id="selectedCourtId"
        :frequency="frequency"
        :selected-weekdays="selectedWeekdays"
        :start-time="startTime"
        :end-time="endTime"
        :start-date="startDate"
        :end-date="endDate"
        :weekday-buttons="weekdayButtons"
        :submitting="submitting"
        @update:selected-court-id="selectedCourtId = $event"
        @update:frequency="frequency = $event"
        @update:start-time="startTime = $event"
        @update:end-time="endTime = $event"
        @update:start-date="startDate = $event"
        @update:end-date="endDate = $event"
        @toggle-weekday="toggleWeekday"
        @submit="submitSeries"
      />

      <!-- Manager List (Right Column) -->
      <RecurringSeriesManager
        :series-list="seriesList"
      />
    </div>
  </div>
</template>

<style scoped>
.recurring-booking-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.recurring-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
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

.recurring-content-grid {
  display: grid;
  grid-template-columns: 1fr 1.2fr;
  gap: 20px;
}

@media (max-width: 900px) {
  .recurring-content-grid {
    grid-template-columns: 1fr;
  }
}
</style>
