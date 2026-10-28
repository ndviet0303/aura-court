<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { WeeklyHours } from '../../types/venueDetail'

const props = defineProps<{ weeklyHours?: WeeklyHours | null; emptyText?: string }>()

const { t } = useI18n()
const DAYS = [1, 2, 3, 4, 5, 6, 7]

/** "06:00–12:00, 14:00–22:00", or null for a closed day. */
function dayHours(day: number): string | null {
  const entry = props.weeklyHours?.[day]
  if (!entry || entry.closed || !entry.intervals?.length) return null
  return entry.intervals.map((interval) => `${interval.start}–${interval.end}`).join(', ')
}
</script>

<template>
  <dl v-if="weeklyHours" class="weekly-hours">
    <div v-for="day in DAYS" :key="day" class="hours-row">
      <dt>{{ t(`admin.schedule.days.${day}`) }}</dt>
      <dd :class="{ 'is-closed': !dayHours(day) }">
        {{ dayHours(day) ?? t('admin.venueDetail.closedDay') }}
      </dd>
    </div>
  </dl>
  <p v-else class="detail-empty">{{ emptyText ?? t('admin.venueDetail.noHours') }}</p>
</template>

<style scoped>
.weekly-hours {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
}

.hours-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 6px 0;
  border-bottom: 1px dashed var(--outline);
  font-size: 13px;
}

.hours-row dt {
  font-weight: 700;
}

.hours-row dd {
  margin: 0;
  font-family: var(--font-mono, monospace);
  text-align: right;
}

.hours-row dd.is-closed {
  color: var(--muted);
  font-family: inherit;
}

.detail-empty {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
</style>
