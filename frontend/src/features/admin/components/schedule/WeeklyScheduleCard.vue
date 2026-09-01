<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SkeletonLoader, TimePicker } from '@/shared/components'
import type { WeekdaySchedule } from '../../composables/useAdminSchedule'

defineProps<{
  schedule: WeekdaySchedule[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'addShift', dayId: number): void
  (e: 'removeShift', dayId: number, shiftIdx: number): void
  (e: 'toggleOpen', dayId: number): void
  (e: 'copySchedule', fromDayId: number, target: 'weekdays' | 'all'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="weekly-schedule-card">
    <div class="card-header">
      <div class="header-left">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">calendar_month</span>
          <h3 class="title">{{ t('admin.schedule.weeklyTitle') }}</h3>
        </div>
        <p class="sub">{{ t('admin.schedule.weeklySub') }}</p>
      </div>

      <!-- Quick Actions Toolbar -->
      <div class="quick-actions-bar">
        <span class="quick-label">{{ t('admin.schedule.quickActions') }}</span>
        <button
          type="button"
          class="btn-quick-action"
          @click="emit('copySchedule', 1, 'weekdays')"
        >
          <span class="material-symbols-outlined icon-14">content_copy</span>
          <span>{{ t('admin.schedule.copyMonToWeekdays') }}</span>
        </button>
        <button
          type="button"
          class="btn-quick-action"
          @click="emit('copySchedule', 1, 'all')"
        >
          <span class="material-symbols-outlined icon-14">sync</span>
          <span>{{ t('admin.schedule.copyMonToAll') }}</span>
        </button>
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="days-list">
      <div v-for="i in 7" :key="i" class="day-row-container skeleton-day-row">
        <div class="day-name-col">
          <SkeletonLoader type="rect" width="80px" height="16px" rounded="4px" />
          <SkeletonLoader type="rect" width="50px" height="12px" rounded="4px" />
        </div>
        <div class="shifts-col">
          <SkeletonLoader type="rect" width="160px" height="34px" rounded="8px" />
          <SkeletonLoader type="rect" width="90px" height="30px" rounded="8px" />
        </div>
      </div>
    </div>

    <!-- Days List -->
    <div v-else class="days-list">
      <div
        v-for="day in schedule"
        :key="day.id"
        class="day-row-container"
        :class="{ 'day-closed': !day.isOpen }"
      >
        <div class="day-name-col">
          <div class="day-title-wrap">
            <strong class="day-name">{{ t(`admin.schedule.days.${day.id}`) }}</strong>
            <button
              type="button"
              class="day-toggle-btn"
              :class="day.isOpen ? 'toggle-active' : 'toggle-inactive'"
              :title="t('admin.schedule.toggleOpen')"
              @click="emit('toggleOpen', day.id)"
            >
              <span class="material-symbols-outlined toggle-icon">
                {{ day.isOpen ? 'toggle_on' : 'toggle_off' }}
              </span>
            </button>
          </div>
          <span class="day-status" :class="day.isOpen ? 'text-green' : 'text-red'">
            {{ day.isOpen ? `● ${t('admin.schedule.statusOpen')}` : `○ ${t('admin.schedule.statusClosed')}` }}
          </span>
        </div>

        <div class="shifts-col">
          <template v-if="day.isOpen">
            <div
              v-for="(shift, sIdx) in day.shifts"
              :key="shift.id"
              class="shift-time-chip"
            >
              <TimePicker v-model="shift.start" />
              <span class="material-symbols-outlined icon-arrow">arrow_forward</span>
              <TimePicker v-model="shift.end" />
              <button
                v-if="day.shifts.length > 1"
                type="button"
                class="btn-delete-shift"
                :title="t('admin.schedule.removeShift')"
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
          </template>

          <div v-else class="closed-notice">
            <span class="material-symbols-outlined icon-16 text-muted">do_not_disturb_on</span>
            <span>{{ t('admin.schedule.statusClosed') }}</span>
          </div>
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
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  flex-wrap: wrap;
}

.header-left {
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

.quick-actions-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.quick-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
}

.btn-quick-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-quick-action:hover {
  background: var(--outline);
  color: var(--pink-dark, #db2777);
}

.days-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.day-row-container {
  display: grid;
  grid-template-columns: 160px 1fr;
  align-items: center;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 16px;
  transition: border-color 0.15s ease;
}

.day-closed {
  opacity: 0.65;
  background: rgba(0, 0, 0, 0.01);
}

.day-name-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.day-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.day-name {
  font-size: 14px;
  font-weight: 700;
}

.day-toggle-btn {
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.toggle-icon {
  font-size: 26px;
}

.toggle-active {
  color: #10b981;
}

.toggle-inactive {
  color: var(--muted);
}

.day-status {
  font-size: 11px;
  font-weight: 600;
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
}

.shift-time-chip :deep(.time-picker) {
  min-width: 100px;
}

.shift-time-chip :deep(.time-picker-trigger) {
  padding: 6px 10px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 8px;
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
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px dashed var(--outline);
  background: transparent;
  color: var(--pink-dark, #db2777);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-add-shift-link:hover {
  background: rgba(219, 39, 119, 0.05);
  border-color: var(--pink-dark, #db2777);
}

.closed-notice {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

@media (max-width: 768px) {
  .day-row-container {
    grid-template-columns: 1fr;
    gap: 10px;
  }
}
</style>
