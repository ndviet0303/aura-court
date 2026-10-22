<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { DatePicker, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { Court, Venue } from '@/features/court/types/court'
import type { FinancePeriodKind, OwnerFinanceFilters } from '../types/ownerFinance'
import { periodRange, shiftPeriod } from '../utils/financePeriod'

defineProps<{ venues: Venue[]; courts: Court[]; showCourt: boolean }>()
// Every change replaces the object, so the tabs reload once per change.
const filters = defineModel<OwnerFinanceFilters>({ required: true })

const { t, d } = useI18n()
const KINDS: FinancePeriodKind[] = ['day', 'month', 'quarter', 'year', 'range']

const update = (patch: Partial<OwnerFinanceFilters>) => {
  filters.value = { ...filters.value, ...patch }
}

// The admin lists send translatable names as their translations.
const optionsOf = (items: { id: number; name: unknown }[], allKey: string): DropdownOption[] => [
  { value: 0, label: t(allKey) },
  ...items.map((item) => ({ value: item.id, label: resolveLocalizedText(item.name) })),
]
const kindOptions = computed(() =>
  KINDS.map((kind) => ({ value: kind, label: t(`admin.ownerFinance.period.kinds.${kind}`) })),
)

const dateLabel = (date: string) => d(new Date(date), 'date')
const periodLabel = computed(() => {
  const { kind, from, to } = filters.value
  const [year, month = 1] = from.split('-').map(Number)
  if (kind === 'day') return dateLabel(from)
  if (kind === 'month') return t('admin.ownerFinance.period.month', { month, year })
  if (kind === 'quarter')
    return t('admin.ownerFinance.period.quarter', { quarter: Math.ceil(month / 3), year })
  if (kind === 'year') return t('admin.ownerFinance.period.year', { year })
  return t('admin.ownerFinance.period.range', { from: dateLabel(from), to: dateLabel(to) })
})

function setKind(value: string | number) {
  const kind = value as FinancePeriodKind
  update(kind === 'range' ? { kind } : { kind, ...periodRange(kind, filters.value.from) })
}

// The picker's clear button sends ''; every period needs a date, so a cleared picker keeps the old one.
function pickDate(date: string) {
  const { kind } = filters.value
  if (date && kind !== 'range') update(periodRange(kind, date))
}

function step(direction: 1 | -1) {
  const { kind, from } = filters.value
  if (kind !== 'range') update(periodRange(kind, shiftPeriod(kind, from, direction)))
}
</script>

<template>
  <div class="filter-bar" role="group" :aria-label="t('admin.ownerFinance.filters.label')">
    <div class="form-group">
      <span class="form-label">{{ t('admin.ownerFinance.filters.venue') }}</span>
      <SelectDropdown
        :model-value="filters.venueId"
        :options="optionsOf(venues, 'admin.ownerFinance.filters.allVenues')"
        variant="bordered"
        :aria-label="t('admin.ownerFinance.filters.venue')"
        data-testid="owner-finance-venue"
        @update:model-value="(value) => update({ venueId: Number(value), courtId: 0 })"
      />
    </div>

    <div v-if="showCourt" class="form-group">
      <span class="form-label">{{ t('admin.ownerFinance.filters.court') }}</span>
      <SelectDropdown
        :model-value="filters.courtId"
        :options="filters.venueId ? optionsOf(courts, 'admin.ownerFinance.filters.allCourts') : []"
        variant="bordered"
        :disabled="!filters.venueId"
        :placeholder="t('admin.ownerFinance.filters.pickVenueFirst')"
        :aria-label="t('admin.ownerFinance.filters.court')"
        data-testid="owner-finance-court"
        @update:model-value="(value) => update({ courtId: Number(value) })"
      />
    </div>

    <div class="form-group">
      <span class="form-label">{{ t('admin.ownerFinance.filters.kind') }}</span>
      <SelectDropdown
        :model-value="filters.kind"
        :options="kindOptions"
        variant="bordered"
        :aria-label="t('admin.ownerFinance.filters.kind')"
        data-testid="owner-finance-kind"
        @update:model-value="setKind"
      />
    </div>

    <template v-if="filters.kind === 'range'">
      <div class="form-group" role="group" :aria-label="t('admin.ownerFinance.filters.from')">
        <span class="form-label">{{ t('admin.ownerFinance.filters.from') }}</span>
        <DatePicker
          :model-value="filters.from"
          :max="filters.to"
          data-testid="owner-finance-from"
          @update:model-value="
            (from) => {
              if (from) update({ from })
            }
          "
        />
      </div>
      <div class="form-group" role="group" :aria-label="t('admin.ownerFinance.filters.to')">
        <span class="form-label">{{ t('admin.ownerFinance.filters.to') }}</span>
        <DatePicker
          :model-value="filters.to"
          :min="filters.from"
          data-testid="owner-finance-to"
          @update:model-value="
            (to) => {
              if (to) update({ to })
            }
          "
        />
      </div>
    </template>
    <div
      v-else
      class="form-group"
      role="group"
      :aria-label="t('admin.ownerFinance.filters.anchor')"
    >
      <span class="form-label">{{ t('admin.ownerFinance.filters.anchor') }}</span>
      <div class="period-step">
        <button
          type="button"
          class="step-btn"
          :aria-label="t('admin.ownerFinance.filters.previous')"
          data-testid="owner-finance-previous"
          @click="step(-1)"
        >
          <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
        </button>
        <DatePicker
          :model-value="filters.from"
          data-testid="owner-finance-anchor"
          @update:model-value="pickDate"
        />
        <button
          type="button"
          class="step-btn"
          :aria-label="t('admin.ownerFinance.filters.next')"
          data-testid="owner-finance-next"
          @click="step(1)"
        >
          <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        </button>
      </div>
    </div>

    <p class="period-summary" aria-live="polite" data-testid="owner-finance-period">
      <strong>{{ periodLabel }}</strong>
      <span v-if="filters.kind !== 'day' && filters.kind !== 'range'" class="period-dates">
        {{
          t('admin.ownerFinance.period.range', {
            from: dateLabel(filters.from),
            to: dateLabel(filters.to),
          })
        }}
      </span>
    </p>
  </div>
</template>

<style scoped>
.filter-bar {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  align-items: end;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.filter-bar .form-group {
  margin-bottom: 0;
}

.period-step {
  display: flex;
  align-items: center;
  gap: 6px;
}

.step-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border: 1px solid var(--outline);
  border-radius: 10px;
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
}

.period-summary {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 0;
  font-size: 14px;
}

.period-dates {
  font-size: 12px;
  color: var(--muted);
}
</style>
