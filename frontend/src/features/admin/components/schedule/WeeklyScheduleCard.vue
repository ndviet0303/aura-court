<script setup lang="ts">
import type { WeekdaySchedule } from '../../composables/useAdminSchedule'

defineProps<{
  schedule: WeekdaySchedule[]
}>()

const emit = defineEmits<{
  (e: 'addShift', dayId: number): void
  (e: 'removeShift', dayId: number, shiftIdx: number): void
}>()
</script>

<template>
  <div class="weekly-schedule-card">
    <div class="card-header">
      <h3 class="title">🗓️ Lịch Hoạt Động Tiêu Chuẩn Hàng Tuần</h3>
      <p class="sub">Thiết lập khung giờ mở cửa đón khách cho từng ngày trong tuần.</p>
    </div>

    <div class="days-list">
      <div
        v-for="day in schedule"
        :key="day.id"
        class="day-row-container grid-stack-mobile"
      >
        <div class="day-name-col">
          <strong class="day-name">{{ day.dayName }}</strong>
          <span class="day-status" :class="day.isOpen ? 'text-green' : 'text-red'">
            {{ day.isOpen ? '● Mở cửa' : '○ Tạm đóng' }}
          </span>
        </div>

        <div class="shifts-col">
          <div
            v-for="(shift, sIdx) in day.shifts"
            :key="shift.id"
            class="shift-time-chip"
          >
            <input v-model="shift.start" type="time" class="time-input" />
            <span>➔</span>
            <input v-model="shift.end" type="time" class="time-input" />
            <button
              v-if="day.shifts.length > 1"
              type="button"
              class="btn-delete-shift"
              @click="emit('removeShift', day.id, sIdx)"
            >
              ✕
            </button>
          </div>

          <button
            type="button"
            class="btn-add-shift-link"
            @click="emit('addShift', day.id)"
          >
            + Thêm ca
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.weekly-schedule-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.days-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.day-row-container {
  display: grid;
  grid-template-columns: 140px 1fr;
  align-items: center;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 16px;
}

.day-name-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.day-name {
  font-size: 14px;
}

.day-status {
  font-size: 11px;
}

.text-green { color: #10b981; }
.text-red { color: #ef4444; }

.shifts-col {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.shift-time-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
}

.time-input {
  border: 0;
  background: transparent;
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
}

.btn-delete-shift {
  border: 0;
  background: transparent;
  color: #ef4444;
  cursor: pointer;
  padding: 0 4px;
  font-size: 11px;
}

.btn-add-shift-link {
  border: 0;
  background: transparent;
  color: var(--pink-dark, #db2777);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
</style>
