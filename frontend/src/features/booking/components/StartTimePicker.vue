<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { SkeletonLoader } from '@/shared/components'
import type { AvailabilitySlot } from '@/features/court/types/court'
import { formatDuration } from '../utils/reservation'
import { startTimes, type StartPeriod } from '../utils/startTimes'

// Duration first, then one tap on a start time: the run it emits is the booking interval (BR-75).
const props = defineProps<{
  slots: AvailabilitySlot[]
  selection: { startsAt: string; minutes: number } | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'pick', run: AvailabilitySlot[]): void
  (e: 'nextDay'): void
}>()

const { t } = useI18n()

const DURATIONS = [30, 60, 90, 120, 180]
const PERIODS: StartPeriod[] = ['morning', 'afternoon', 'evening']
const minutes = ref(
  props.selection && DURATIONS.includes(props.selection.minutes) ? props.selection.minutes : 60,
)

const groups = computed(() => {
  const starts = startTimes(props.slots, minutes.value)
  return PERIODS.map((period) => ({
    period,
    times: starts.filter((s) => s.period === period),
  })).filter((g) => g.times.length)
})
// Every time of the day has started: nothing is left to book today.
const dayOver = computed(
  () => props.slots.length > 0 && props.slots.every((s) => Date.parse(s.startsAt) <= Date.now()),
)
const emptyText = computed(() => {
  if (dayOver.value) return t('booking.startPicker.dayOver')
  if (!props.slots.length) return t('booking.startPicker.noTimes')
  return t('booking.startPicker.noneForDuration', { duration: formatDuration(minutes.value) })
})

function chooseDuration(value: number) {
  minutes.value = value
  if (props.selection) emit('pick', [])
}

const isPicked = (startsAt: string) =>
  props.selection?.startsAt === startsAt && props.selection.minutes === minutes.value
</script>

<template>
  <div class="start-picker">
    <div class="duration-row" role="radiogroup" :aria-label="t('booking.startPicker.duration')">
      <span class="duration-label">{{ t('booking.startPicker.duration') }}</span>
      <button
        v-for="value in DURATIONS"
        :key="value"
        type="button"
        role="radio"
        class="duration-chip"
        :class="{ active: minutes === value }"
        :aria-checked="minutes === value"
        @click="chooseDuration(value)"
      >
        {{ formatDuration(value) }}
      </button>
    </div>

    <SkeletonLoader v-if="loading" type="slot" :count="8" />

    <div v-else-if="!groups.length" class="start-empty">
      <p>{{ emptyText }}</p>
      <button type="button" class="next-day-btn" @click="emit('nextDay')">
        {{ t('booking.startPicker.nextDay') }}
        <span class="material-symbols-outlined icon-16">arrow_forward</span>
      </button>
    </div>

    <div v-else class="start-groups">
      <section v-for="group in groups" :key="group.period">
        <h4 class="group-title">
          {{ t(`booking.startPicker.${group.period}`) }}
          <span class="group-count">{{ group.times.length }}</span>
        </h4>
        <div class="start-chips">
          <button
            v-for="start in group.times"
            :key="start.startsAt"
            type="button"
            class="start-chip"
            :class="{ picked: isPicked(start.startsAt) }"
            :aria-pressed="isPicked(start.startsAt)"
            @click="emit('pick', isPicked(start.startsAt) ? [] : start.run)"
          >
            <span class="start-clock font-mono">{{ start.clock }}</span>
            <span class="start-price">{{ formatCurrency(start.price) }}</span>
            <span
              v-if="start.peak"
              class="peak-dot"
              :title="t('booking.startPicker.peak')"
              :aria-label="t('booking.startPicker.peak')"
            ></span>
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.start-picker {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.duration-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.duration-label {
  margin-right: 4px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}

.duration-chip,
.start-chip {
  border: 1px solid var(--outline);
  background: var(--surface-subtle);
  color: var(--ink);
  cursor: pointer;
  transition:
    border-color 0.15s ease,
    background 0.15s ease;
}

.duration-chip {
  padding: 7px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
}

.duration-chip:hover,
.start-chip:hover {
  border-color: var(--primary);
}

.duration-chip.active,
.start-chip.picked {
  border-color: var(--primary);
  background: var(--primary);
  color: #fff;
}

.start-groups {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.group-title {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 700;
  color: var(--muted);
}

.group-count {
  font-size: 11px;
  font-weight: 600;
  opacity: 0.8;
}

.start-chips {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
  gap: 8px;
}

.start-chip {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 9px 6px;
  border-radius: 12px;
}

.start-clock {
  font-size: 15px;
  font-weight: 700;
}

.start-price {
  font-size: 11px;
  color: var(--muted);
}

.start-chip.picked .start-price {
  color: rgba(255, 255, 255, 0.85);
}

.peak-dot {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--accent);
}

.start-empty {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px;
  border: 1px dashed var(--outline);
  border-radius: 14px;
  color: var(--muted);
}

.start-empty p {
  margin: 0;
}

.next-day-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 0;
  border-radius: 999px;
  background: var(--primary);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
