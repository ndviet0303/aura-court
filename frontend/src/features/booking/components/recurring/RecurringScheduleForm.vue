<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import type { Court } from '@/features/court/types/court'
import type { RecurringMode, SkippedDay } from '../../types/recurring'
import { HALF_HOURS } from '../../utils/recurringDates'
import RecurringDatePicker from './RecurringDatePicker.vue'

// UC-8.1..UC-8.4: the court, how the days are picked (BR-162..BR-165) and the common time (FR-135).
const courtId = defineModel<number | null>('courtId', { required: true })
const mode = defineModel<RecurringMode>('mode', { required: true })
const month = defineModel<string>('month', { required: true })
const weekdays = defineModel<number[]>('weekdays', { required: true })
const fromMonth = defineModel<string>('fromMonth', { required: true })
const toMonth = defineModel<string>('toMonth', { required: true })
const days = defineModel<number[]>('days', { required: true })
const dates = defineModel<string[]>('dates', { required: true })
const start = defineModel<string>('start', { required: true })
const end = defineModel<string>('end', { required: true })

const props = defineProps<{
  courts: Court[]
  today: string
  skipped: SkippedDay[]
  count: number
  monthRangeInvalid: boolean
  timeValid: boolean
  busy: boolean
}>()
const emit = defineEmits<{ (e: 'applyTime'): void }>()

const { t } = useI18n()
const MODES: RecurringMode[] = ['weekdays', 'monthDays', 'dates']
const courtOptions = computed(() =>
  props.courts.map((court) => ({
    value: court.id,
    label: court.code,
    sublabel: court.venue?.name,
  })),
)

const toggle = (list: number[], value: number) =>
  list.includes(value) ? list.filter((v) => v !== value) : [...list, value]
</script>

<template>
  <section class="schedule-form" data-testid="recurring-schedule-form">
    <label class="field">
      <span>{{ t('recurring.court') }}</span>
      <SelectDropdown
        :model-value="courtId"
        :options="courtOptions"
        :placeholder="t('recurring.courtPlaceholder')"
        :aria-label="t('recurring.court')"
        :disabled="busy"
        variant="bordered"
        @update:model-value="courtId = Number($event)"
      />
    </label>

    <div class="mode-tabs" role="tablist">
      <button
        v-for="item in MODES"
        :key="item"
        type="button"
        role="tab"
        :aria-selected="mode === item"
        :class="{ active: mode === item }"
        :data-testid="`mode-${item}`"
        @click="mode = item"
      >
        {{ t(`recurring.mode.${item}`) }}
      </button>
    </div>

    <div v-if="mode === 'weekdays'" class="mode-body">
      <label class="field">
        <span>{{ t('recurring.month') }}</span>
        <input v-model="month" type="month" :min="today.slice(0, 7)" required />
      </label>
      <div class="chips" :aria-label="t('recurring.weekdays')">
        <button
          v-for="n in 7"
          :key="n"
          type="button"
          :class="{ active: weekdays.includes(n) }"
          :aria-pressed="weekdays.includes(n)"
          @click="weekdays = toggle(weekdays, n)"
        >
          {{ t(`recurring.weekday.${n}`) }}
        </button>
      </div>
    </div>

    <div v-else-if="mode === 'monthDays'" class="mode-body">
      <div class="row">
        <label class="field">
          <span>{{ t('recurring.fromMonth') }}</span>
          <input v-model="fromMonth" type="month" :min="today.slice(0, 7)" required />
        </label>
        <label class="field">
          <span>{{ t('recurring.toMonth') }}</span>
          <input v-model="toMonth" type="month" :min="fromMonth" required />
        </label>
      </div>
      <p v-if="monthRangeInvalid" class="form-error" role="alert">
        {{ t('recurring.monthRangeInvalid') }}
      </p>
      <div class="chips day-grid" :aria-label="t('recurring.monthDays')">
        <button
          v-for="n in 31"
          :key="n"
          type="button"
          :class="{ active: days.includes(n) }"
          :aria-pressed="days.includes(n)"
          @click="days = toggle(days, n)"
        >
          {{ n }}
        </button>
      </div>
    </div>

    <RecurringDatePicker v-else v-model="dates" :today="today" />

    <ul v-if="skipped.length" class="skipped" data-testid="recurring-skipped">
      <li v-for="item in skipped" :key="`${item.month}-${item.day}`">
        {{
          t('recurring.skipped', {
            day: item.day,
            month: item.month.slice(5),
            year: item.month.slice(0, 4),
          })
        }}
      </li>
    </ul>

    <div class="row time-row">
      <label class="field">
        <span>{{ t('recurring.start') }}</span>
        <select v-model="start">
          <option v-for="time in HALF_HOURS" :key="time" :value="time">{{ time }}</option>
        </select>
      </label>
      <label class="field">
        <span>{{ t('recurring.end') }}</span>
        <select v-model="end">
          <option v-for="time in HALF_HOURS" :key="time" :value="time">{{ time }}</option>
        </select>
      </label>
      <AuraButton
        variant="secondary"
        :disabled="!timeValid || count === 0 || busy"
        data-testid="apply-time"
        @click="emit('applyTime')"
      >
        {{ t('recurring.applyTime') }}
      </AuraButton>
    </div>
    <p v-if="!timeValid" class="form-error" role="alert">{{ t('recurring.timeInvalid') }}</p>
    <p class="count" data-testid="recurring-count">
      {{ count ? t('recurring.count', { count }) : t('recurring.noDays') }}
    </p>
  </section>
</template>

<style scoped>
.schedule-form {
  display: grid;
  gap: 14px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
}
.field {
  display: grid;
  gap: 5px;
  color: var(--muted);
  font-size: 12px;
}
.field input,
.field select {
  min-width: 0;
  min-height: 0;
  padding: 8px 10px;
  border: 1px solid var(--outline);
  border-radius: 8px;
  background: var(--surface);
  color: var(--ink);
  font: inherit;
}
.field select {
  padding-right: 34px;
  background-position: right 10px center;
}
.row > .field {
  flex: 0 1 200px;
}
.row {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-start;
  gap: 10px;
  align-items: flex-end;
}
.mode-tabs,
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.mode-tabs button,
.chips button {
  padding: 7px 12px;
  border: 1px solid var(--outline);
  border-radius: 999px;
  background: var(--surface);
  color: var(--ink);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}
.mode-tabs button.active,
.chips button.active {
  border-color: var(--primary);
  background: var(--primary);
  color: #fff;
}
.day-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 44px));
}
.day-grid button {
  padding: 7px 0;
  border-radius: 8px;
}
.mode-body {
  display: grid;
  gap: 10px;
}
.skipped {
  margin: 0;
  padding: 10px 12px 10px 28px;
  border-radius: 8px;
  background: color-mix(in srgb, #f59e0b 12%, transparent);
  color: var(--ink);
  font-size: 12px;
}
.form-error {
  margin: 0;
  color: #dc2626;
  font-size: 12px;
}
.count {
  margin: 0;
  color: var(--muted);
  font-size: 12px;
}
</style>
