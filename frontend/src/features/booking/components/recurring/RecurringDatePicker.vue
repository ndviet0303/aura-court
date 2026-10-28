<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

// BR-165: the customer picks the exact days on a month calendar; a past day cannot be picked.
const dates = defineModel<string[]>({ required: true })
const props = defineProps<{ today: string }>()

const { t } = useI18n()
const month = ref(props.today.slice(0, 7))
const pad = (n: number) => String(n).padStart(2, '0')

const cells = computed(() => {
  const [year, m] = month.value.split('-').map(Number) as [number, number]
  const lead = (new Date(Date.UTC(year, m - 1, 1)).getUTCDay() + 6) % 7
  const count = new Date(Date.UTC(year, m, 0)).getUTCDate()
  return [
    ...Array.from({ length: lead }, () => null),
    ...Array.from({ length: count }, (_, i) => `${month.value}-${pad(i + 1)}`),
  ]
})

function shift(by: number) {
  const [year, m] = month.value.split('-').map(Number) as [number, number]
  const next = new Date(Date.UTC(year, m - 1 + by, 1))
  month.value = `${next.getUTCFullYear()}-${pad(next.getUTCMonth() + 1)}`
}

function toggle(date: string) {
  dates.value = dates.value.includes(date)
    ? dates.value.filter((d) => d !== date)
    : [...dates.value, date]
}
</script>

<template>
  <div class="date-picker" data-testid="recurring-date-picker">
    <div class="picker-head">
      <button
        type="button"
        :aria-label="t('recurring.prevMonth')"
        :disabled="month <= today.slice(0, 7)"
        @click="shift(-1)"
      >
        ‹
      </button>
      <strong>{{
        t('recurring.monthLabel', { month: month.slice(5), year: month.slice(0, 4) })
      }}</strong>
      <button type="button" :aria-label="t('recurring.nextMonth')" @click="shift(1)">›</button>
    </div>
    <div class="picker-grid">
      <span v-for="n in 7" :key="`w${n}`" class="picker-weekday">{{
        t(`recurring.weekday.${n}`)
      }}</span>
      <template v-for="(date, i) in cells" :key="date ?? `blank${i}`">
        <span v-if="!date" />
        <button
          v-else
          type="button"
          class="picker-day"
          :class="{ picked: dates.includes(date) }"
          :disabled="date < today"
          :aria-pressed="dates.includes(date)"
          :data-testid="`pick-${date}`"
          @click="toggle(date)"
        >
          {{ Number(date.slice(8)) }}
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.date-picker {
  display: grid;
  gap: 8px;
  max-width: 320px;
}
.picker-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: var(--ink);
  font-size: 13px;
}
.picker-head button {
  width: 30px;
  height: 30px;
  border: 1px solid var(--outline);
  border-radius: 8px;
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
}
.picker-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}
.picker-weekday {
  color: var(--muted);
  font-size: 11px;
  text-align: center;
}
.picker-day {
  height: 34px;
  border: 1px solid var(--outline);
  border-radius: 8px;
  background: var(--surface);
  color: var(--ink);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}
.picker-day.picked {
  border-color: var(--primary);
  background: var(--primary);
  color: #fff;
}
.picker-day:disabled,
.picker-head button:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}
</style>
