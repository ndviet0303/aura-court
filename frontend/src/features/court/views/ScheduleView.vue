<script setup lang="ts">
import { onMounted } from 'vue'
import { useCourtSchedule } from '../composables/useCourtSchedule'

import ScheduleDatePicker from '../components/schedule/ScheduleDatePicker.vue'
import ScheduleCourtSelector from '../components/schedule/ScheduleCourtSelector.vue'
import ScheduleSlotsGrid from '../components/schedule/ScheduleSlotsGrid.vue'
import ScheduleBookingSummary from '../components/schedule/ScheduleBookingSummary.vue'

const {
  store,
  date,
  selectedCourtId,
  court,
  carouselDays,
  totalPrice,
  error,
  notice,
  loading,
  selectDate,
  selectCourt,
  toggleSlot,
  proceedToBooking,
  fetchAvailability,
} = useCourtSchedule()

onMounted(async () => {
  if (store.courts.length === 0) {
    await store.loadCourts()
  }
  await fetchAvailability()
})
</script>

<template>
  <div class="schedule-view-page">
    <header class="schedule-hdr">
      <h1 class="page-title">📅 Lịch Đặt Sân Theo Ngày (Live Court Schedule)</h1>
      <p class="page-sub">Xem tình trạng khung giờ trống theo thời gian thực và chọn lịch thi đấu phù hợp.</p>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Date Carousel Picker -->
    <ScheduleDatePicker
      :days="carouselDays"
      :selected-date="date"
      @select-date="selectDate"
    />

    <!-- Court Selector Bar -->
    <ScheduleCourtSelector
      :courts="store.courts"
      :selected-court-id="selectedCourtId"
      @select-court="selectCourt"
    />

    <!-- Main Split Layout (Slots Grid vs Sticky Summary) -->
    <div class="schedule-split-layout">
      <div class="slots-col">
        <ScheduleSlotsGrid
          :slots="store.slots"
          :selected-slots="store.selectedSlots"
          @toggle-slot="toggleSlot"
        />
      </div>

      <div class="summary-col">
        <ScheduleBookingSummary
          v-if="court"
          :court="court"
          :date="date"
          :selected-slots="store.selectedSlots"
          :total-price="totalPrice"
          @checkout="proceedToBooking"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.schedule-view-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1300px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.schedule-hdr {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  margin: 0;
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

.schedule-split-layout {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 24px;
  align-items: flex-start;
}

.summary-col {
  position: sticky;
  top: 24px;
}

@media (max-width: 900px) {
  .schedule-split-layout {
    grid-template-columns: 1fr;
  }
  .summary-col {
    position: static;
  }
}
</style>
