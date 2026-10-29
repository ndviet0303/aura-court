<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  AuraButton,
  CurrencyInput,
  DatePicker,
  DateTimePicker,
  SelectDropdown,
} from '@/shared/components'
import { venueClock, venueDate, venueInstant } from '@/core/utils/venueTime'
import type {
  CreatePriceTablePayload,
  CreatePriceTableVersionPayload,
  PriceTableDetail,
  PriceTableInterval,
  PriceTableKind,
} from '../../types/priceTable'
import { ISO_WEEKDAYS, usePriceTableLabels } from '../../composables/usePriceTableLabels'

type IntervalDraft = {
  weekdays: number[]
  dates: string[]
  start: string
  end: string
  price: number | null
}

const props = defineProps<{
  saving: boolean
  errors: Record<string, string[]>
  /** Set to publish a new version of this table instead of creating a table. */
  table?: PriceTableDetail
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: CreatePriceTablePayload): void
  (e: 'publish', payload: CreatePriceTableVersionPayload): void
}>()

const { t } = useI18n()
const { kind: kindLabel, weekday, calendarDate } = usePriceTableLabels()

const blankInterval = (): IntervalDraft => ({
  weekdays: [...ISO_WEEKDAYS],
  dates: [],
  start: '00:00',
  end: '23:59',
  price: null,
})

const toDraft = (interval: PriceTableInterval): IntervalDraft => ({
  weekdays: interval.weekdays ?? [],
  dates: interval.dates ?? [],
  start: interval.start,
  end: interval.end,
  price: interval.price_per_hour,
})

// A new version starts from the newest version's intervals, effective at publication.
const latest = props.table?.versions[0]
const now = new Date()
const name = ref('')
const kind = ref<PriceTableKind>(props.table?.kind ?? 'normal')
const effectiveAt = ref(props.table ? '' : `${venueDate(now.toISOString())}T${venueClock(now)}`)
const intervals = ref<IntervalDraft[]>(latest ? latest.intervals.map(toDraft) : [blankInterval()])

const kindOptions = computed(() =>
  (['normal', 'holiday'] as const).map((value) => ({ value, label: kindLabel(value) })),
)

function toggleWeekday(interval: IntervalDraft, day: number) {
  interval.weekdays = interval.weekdays.includes(day)
    ? interval.weekdays.filter((d) => d !== day)
    : [...interval.weekdays, day].sort()
}

function addDate(interval: IntervalDraft, date: string) {
  if (date && !interval.dates.includes(date)) interval.dates = [...interval.dates, date].sort()
}

const intervalErrors = (index: number) =>
  Object.entries(props.errors)
    .filter(([key]) => key === `intervals.${index}` || key.startsWith(`intervals.${index}.`))
    .flatMap(([, messages]) => messages)

function submit() {
  const rows = intervals.value.map((interval) => ({
    ...(kind.value === 'normal' ? { weekdays: interval.weekdays } : { dates: interval.dates }),
    start: interval.start,
    end: interval.end,
    price_per_hour: interval.price ?? 0,
  }))
  if (props.table) {
    const at = effectiveAt.value
    emit('publish', { effective_at: at ? venueInstant(at) : undefined, intervals: rows })
    return
  }
  emit('submit', {
    name: name.value.trim(),
    kind: kind.value,
    effective_at: venueInstant(effectiveAt.value),
    intervals: rows,
  })
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <form
      class="modal-card modal-lg"
      :data-testid="table ? 'price-table-version-create' : 'price-table-create'"
      @submit.prevent="submit"
    >
      <div class="modal-header">
        <h3 class="modal-title">
          {{
            table
              ? t('admin.priceTables.version.title', { name: table.name })
              : t('admin.priceTables.form.title')
          }}
        </h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('admin.priceTables.form.cancel')"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <div class="modal-body">
        <p v-if="table" class="form-hint">{{ t('admin.priceTables.version.note') }}</p>
        <p v-for="message in errors.price_table" :key="message" class="form-error">
          {{ message }}
        </p>
        <div v-if="!table" class="form-row">
          <label class="form-group">
            <span class="form-label">{{ t('admin.priceTables.form.nameLabel') }}</span>
            <input
              v-model="name"
              class="form-input"
              name="name"
              maxlength="120"
              :placeholder="t('admin.priceTables.form.namePlaceholder')"
            />
            <span v-for="message in errors.name" :key="message" class="form-error">{{
              message
            }}</span>
          </label>
          <div class="form-group">
            <span class="form-label">{{ t('admin.priceTables.form.kindLabel') }}</span>
            <SelectDropdown
              :model-value="kind"
              :options="kindOptions"
              variant="bordered"
              :aria-label="t('admin.priceTables.form.kindLabel')"
              @update:model-value="kind = $event as PriceTableKind"
            />
            <span v-for="message in errors.kind" :key="message" class="form-error">{{
              message
            }}</span>
          </div>
        </div>

        <div class="form-group">
          <span class="form-label">{{ t('admin.priceTables.form.effectiveAtLabel') }}</span>
          <DateTimePicker v-model="effectiveAt" />
          <span v-if="table" class="form-hint">{{ t('admin.priceTables.version.nowHint') }}</span>
          <span class="form-hint">{{ t('admin.priceTables.form.effectiveAtHint') }}</span>
          <span v-for="message in errors.effective_at" :key="message" class="form-error">{{
            message
          }}</span>
        </div>

        <h4 class="intervals-title">{{ t('admin.priceTables.form.intervalsTitle') }}</h4>
        <p v-for="message in errors.intervals" :key="message" class="form-error">{{ message }}</p>
        <fieldset
          v-for="(interval, index) in intervals"
          :key="index"
          class="interval-card"
          data-testid="price-interval"
        >
          <legend>{{ t('admin.priceTables.form.intervalLabel', { n: index + 1 }) }}</legend>
          <div v-if="kind === 'normal'" class="day-chips">
            <button
              v-for="day in ISO_WEEKDAYS"
              :key="day"
              type="button"
              class="day-chip"
              :class="{ 'is-on': interval.weekdays.includes(day) }"
              :aria-pressed="interval.weekdays.includes(day)"
              :aria-label="weekday(day)"
              @click="toggleWeekday(interval, day)"
            >
              {{ weekday(day, 'short') }}
            </button>
          </div>
          <div v-else class="day-chips">
            <span v-for="date in interval.dates" :key="date" class="day-chip is-on">
              {{ calendarDate(date) }}
              <button
                type="button"
                class="chip-remove"
                :aria-label="t('admin.priceTables.form.removeDate', { date: calendarDate(date) })"
                @click="interval.dates = interval.dates.filter((d) => d !== date)"
              >
                ✕
              </button>
            </span>
            <DatePicker
              model-value=""
              :placeholder="t('admin.priceTables.form.addDate')"
              @update:model-value="addDate(interval, $event)"
            />
          </div>
          <div class="interval-fields">
            <label class="form-group">
              <span class="form-label">{{ t('admin.priceTables.form.startLabel') }}</span>
              <input v-model="interval.start" type="time" class="form-input" required />
            </label>
            <label class="form-group">
              <span class="form-label">{{ t('admin.priceTables.form.endLabel') }}</span>
              <input v-model="interval.end" type="time" class="form-input" required />
            </label>
            <label class="form-group">
              <span class="form-label">{{ t('admin.priceTables.form.priceLabel') }}</span>
              <CurrencyInput v-model="interval.price" />
            </label>
          </div>
          <p class="form-hint">{{ t('admin.priceTables.form.endHint') }}</p>
          <p v-for="message in intervalErrors(index)" :key="message" class="form-error">
            {{ message }}
          </p>
          <button
            v-if="intervals.length > 1"
            type="button"
            class="interval-remove"
            @click="intervals.splice(index, 1)"
          >
            {{ t('admin.priceTables.form.removeInterval') }}
          </button>
        </fieldset>
        <AuraButton variant="ghost" size="sm" icon="add" @click="intervals.push(blankInterval())">
          {{ t('admin.priceTables.form.addInterval') }}
        </AuraButton>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" @click="emit('close')">
          {{ t('admin.priceTables.form.cancel') }}
        </AuraButton>
        <AuraButton type="submit" variant="primary" :loading="saving">
          {{ t(table ? 'admin.priceTables.version.submit' : 'admin.priceTables.form.submit') }}
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.form-row,
.interval-fields {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(160px, 100%), 1fr));
  gap: 0 12px;
}

.intervals-title {
  margin: 4px 0 8px;
  font-size: 14px;
}

.interval-card {
  margin: 0 0 12px;
  padding: 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
}

.interval-card legend {
  padding: 0 6px;
  font-size: 13px;
  font-weight: 700;
}

.day-chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
}

.day-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border: 1px solid var(--outline);
  border-radius: 999px;
  background: transparent;
  color: var(--ink);
  font-size: 12px;
  cursor: pointer;
}

.day-chip.is-on {
  border-color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.1);
}

.chip-remove,
.interval-remove {
  border: 0;
  background: transparent;
  color: var(--danger, #ef4444);
  font-size: 12px;
  cursor: pointer;
}
</style>
