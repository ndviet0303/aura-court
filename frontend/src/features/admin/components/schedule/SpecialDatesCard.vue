<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { SpecialDateItem } from '../../composables/useAdminSchedule'

defineProps<{
  specialDates: SpecialDateItem[]
}>()

const emit = defineEmits<{
  (e: 'add'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="special-dates-card">
    <div class="card-header">
      <div>
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">event</span>
          <h3 class="title">{{ t('admin.schedule.specialDatesTitle') }}</h3>
        </div>
        <p class="sub">{{ t('admin.schedule.specialDatesSub') }}</p>
      </div>

      <button type="button" class="btn-add-circle" @click="emit('add')">
        <span class="material-symbols-outlined icon-16">add</span>
        <span>{{ t('admin.schedule.addSpecialDateBtn') }}</span>
      </button>
    </div>

    <div v-if="specialDates.length === 0" class="empty-dates">
      {{ t('admin.schedule.noSpecialDates') }}
    </div>

    <div v-else class="dates-list">
      <div v-for="d in specialDates" :key="d.id" class="date-row">
        <div class="date-info">
          <strong class="date-name">{{ d.name }}</strong>
          <span class="date-val font-mono">
            <span class="material-symbols-outlined icon-14">calendar_today</span>
            <span>{{ d.date }} · {{ d.hours || t('admin.schedule.standardHours') }}</span>
          </span>
        </div>
        <span class="type-tag" :class="`type-${d.type}`">
          {{ d.type === 'holiday' ? t('admin.schedule.typeHoliday') : t('admin.schedule.typeSpecialHours') }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.special-dates-card {
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
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.btn-add-circle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.empty-dates {
  padding: 24px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
}

.dates-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.date-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.date-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.date-val {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted);
}

.type-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
}

.type-holiday { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.type-special_hours { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}
</style>
