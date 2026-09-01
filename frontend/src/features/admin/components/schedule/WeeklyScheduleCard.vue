<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { WeekdaySchedule } from '../../composables/useAdminSchedule'

defineProps<{
  schedule: WeekdaySchedule[]
}>()

const emit = defineEmits<{
  (e: 'addShift', dayId: number): void
  (e: 'removeShift', dayId: number, shiftIdx: number): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="weekly-schedule-card">
    <div class="card-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">calendar_month</span>
        <h3 class="title">{{ t('admin.schedule.weeklyTitle') }}</h3>
      </div>
      <p class="sub">{{ t('admin.schedule.weeklySub') }}</p>
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
            {{ day.isOpen ? `● ${t('admin.schedule.statusOpen')}` : `○ ${t('admin.schedule.statusClosed')}` }}
          </span>
        </div>

        <div class="shifts-col">
          <div
            v-for="(shift, sIdx) in day.shifts"
            :key="shift.id"
            class="shift-time-chip"
          >
            <input v-model="shift.start" type="time" class="time-input" />
            <span class="material-symbols-outlined icon-arrow">arrow_forward</span>
            <input v-model="shift.end" type="time" class="time-input" />
            <button
              v-if="day.shifts.length > 1"
              type="button"
              class="btn-delete-shift"
              title="Remove Shift"
              @click="emit('removeShift', day.id, sIdx)"
            >
              <span class="material-symbols-outlined icon-14">close</span>
            </button>
          </div>

          <button
            type="button"
            class="btn-add-shift-link"
            @click="emit('addShift', day.id)"
          >
            <span class="material-symbols-outlined icon-16">add</span>
            <span>{{ t('admin.schedule.addShift') }}</span>
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

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
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
  outline: none;
}

.icon-arrow {
  font-size: 14px;
  color: var(--muted);
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}

.btn-delete-shift {
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  padding: 0 2px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-delete-shift:hover {
  color: #ef4444;
}

.btn-add-shift-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 0;
  background: transparent;
  color: var(--pink-dark, #db2777);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
</style>
