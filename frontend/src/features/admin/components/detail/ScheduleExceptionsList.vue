<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { ScheduleException } from '../../types/venueDetail'

defineProps<{ exceptions: ScheduleException[] }>()

const { t } = useI18n()
const TYPE_KEYS: Record<ScheduleException['type'], string> = {
  holiday: 'admin.schedule.typeHoliday',
  special_hours: 'admin.schedule.typeSpecialHours',
  closed: 'admin.schedule.typeClosed',
}

function hoursOf(exception: ScheduleException): string {
  if (exception.type === 'closed' || !exception.intervals?.length) {
    return t('admin.venueDetail.closedDay')
  }
  return exception.intervals.map((interval) => `${interval.start}–${interval.end}`).join(', ')
}
</script>

<template>
  <ul v-if="exceptions.length" class="exception-list">
    <li v-for="exception in exceptions" :key="exception.id" class="exception-row">
      <div class="exception-info">
        <strong>{{ resolveLocalizedText(exception.name) }}</strong>
        <span class="exception-meta">
          {{ String(exception.exception_date).slice(0, 10) }} · {{ hoursOf(exception) }}
        </span>
      </div>
      <span class="exception-type" :class="`type-${exception.type}`">
        {{ t(TYPE_KEYS[exception.type]) }}
      </span>
    </li>
  </ul>
  <p v-else class="detail-empty">{{ t('admin.venueDetail.noExceptions') }}</p>
</template>

<style scoped>
.exception-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.exception-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px dashed var(--outline);
  font-size: 13px;
}

.exception-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.exception-meta {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
  color: var(--muted);
}

.exception-type {
  flex-shrink: 0;
  padding: 3px 8px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(59, 130, 246, 0.12);
  color: #2563eb;
}

.type-closed {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.type-holiday {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.detail-empty {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
</style>
