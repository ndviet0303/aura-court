<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { OccurrenceRow } from '../../types/recurring'
import { HALF_HOURS, validTimes } from '../../utils/recurringDates'

// UC-8.5: one occurrence's own time. Confirming applies it (and checks that occurrence again); closing keeps the
// time it had (A-2). An invalid time cannot be confirmed (E-1).
const props = defineProps<{ row: OccurrenceRow; label: string }>()
const emit = defineEmits<{ confirm: [start: string, end: string]; close: [] }>()
const { t } = useI18n()

const start = ref(props.row.start)
const end = ref(props.row.end)
const valid = computed(() => validTimes(start.value, end.value))
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="occurrence-time-title">
      <div class="modal-header">
        <h3 id="occurrence-time-title" class="modal-title">
          {{ t('recurring.editTimeTitle', { date: label }) }}
        </h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="modal-body time-body">
        <p>{{ t('recurring.currentTime', { time: `${row.start} – ${row.end}` }) }}</p>
        <div class="time-fields">
          <label>
            {{ t('recurring.start') }}
            <select v-model="start" data-testid="occurrence-time-start">
              <option v-for="time in HALF_HOURS" :key="time" :value="time">{{ time }}</option>
            </select>
          </label>
          <label>
            {{ t('recurring.end') }}
            <select v-model="end" data-testid="occurrence-time-end">
              <option v-for="time in HALF_HOURS" :key="time" :value="time">{{ time }}</option>
            </select>
          </label>
        </div>
        <p v-if="!valid" class="time-error" role="alert">{{ t('recurring.timeInvalid') }}</p>
      </div>
      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">{{ t('common.cancel') }}</AuraButton>
        <AuraButton
          :disabled="!valid"
          data-testid="occurrence-time-confirm"
          @click="emit('confirm', start, end)"
        >
          {{ t('recurring.confirmTime') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.time-body {
  display: grid;
  gap: 12px;
}
.time-body p {
  margin: 0;
}
.time-fields {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.time-fields label {
  display: grid;
  gap: 4px;
  color: var(--muted);
  font-size: 12px;
}
.time-error {
  color: #dc2626;
  font-size: 12px;
}
</style>
