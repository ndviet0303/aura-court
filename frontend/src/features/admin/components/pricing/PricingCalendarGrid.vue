<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import { SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { PriceRuleItem } from '../../types/pricing'
import type { Court, Venue } from '@/features/court/types/court'
import PricingGridSkeleton from './PricingGridSkeleton.vue'

const props = defineProps<{
  rules: PriceRuleItem[]
  courts: Court[]
  venues: Venue[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (
    e: 'createForSlot',
    payload: { weekday: number; starts_at: string; ends_at: string; court_id?: number },
  ): void
}>()

const { t } = useI18n()

const selectedCourtId = ref<number | null>(null)
const weekOffset = ref(0) // 0 = current week, 1 = next week, -1 = prev week

// Auto select first court once courts are loaded
const activeCourt = computed(() => {
  if (selectedCourtId.value) {
    const found = props.courts.find((c) => c.id === selectedCourtId.value)
    if (found) return found
  }
  return props.courts[0] || null
})

const courtOptions = computed<DropdownOption[]>(() =>
  props.courts.map((c) => ({
    value: c.id,
    label: `${resolveLocalizedText(c.name)}${c.venue ? ` (${resolveLocalizedText(c.venue.name)})` : ''}`,
    icon: 'sports_tennis',
  })),
)

// Calculate the 7 days of the current viewed week
const weekDays = computed(() => {
  const now = new Date()
  const currentDay = now.getDay() // 0 = Sun, 1 = Mon...
  const mondayOffset = currentDay === 0 ? -6 : 1 - currentDay

  const monday = new Date(now)
  monday.setDate(now.getDate() + mondayOffset + weekOffset.value * 7)

  const days = []
  const dayNames = [
    t('admin.pricing.monday'),
    t('admin.pricing.tuesday'),
    t('admin.pricing.wednesday'),
    t('admin.pricing.thursday'),
    t('admin.pricing.friday'),
    t('admin.pricing.saturday'),
    t('admin.pricing.sunday'),
  ]

  for (let i = 0; i < 7; i++) {
    const d = new Date(monday)
    d.setDate(monday.getDate() + i)
    const yyyy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    const isoDate = `${yyyy}-${mm}-${dd}`
    const isToday = isoDate === new Date().toISOString().split('T')[0]

    days.push({
      weekday: i + 1, // 1 to 7
      name: dayNames[i],
      dateNumber: d.getDate(),
      monthNumber: d.getMonth() + 1,
      isoDate,
      isToday,
      isWeekend: i >= 5,
    })
  }

  return days
})

const weekRangeLabel = computed(() => {
  if (weekDays.value.length < 7) return ''
  const first = weekDays.value[0]
  const last = weekDays.value[6]
  if (!first || !last) return ''
  return `${first.dateNumber}/${first.monthNumber} - ${last.dateNumber}/${last.monthNumber}`
})

// Time slot blocks to render in each day
const timeSlots = [
  { starts_at: '06:00', ends_at: '09:00', label: '06:00 - 09:00' },
  { starts_at: '09:00', ends_at: '11:00', label: '09:00 - 11:00' },
  { starts_at: '11:00', ends_at: '14:00', label: '11:00 - 14:00' },
  { starts_at: '14:00', ends_at: '17:00', label: '14:00 - 17:00' },
  { starts_at: '17:00', ends_at: '21:00', label: '17:00 - 21:00' },
  { starts_at: '21:00', ends_at: '23:00', label: '21:00 - 23:00' },
]

// Compute the price and active rule for a specific day and slot
function getSlotPricing(
  day: { weekday: number; isoDate: string; isWeekend: boolean },
  slot: (typeof timeSlots)[0],
) {
  const court = activeCourt.value
  const venueId = court?.venue_id || court?.venue?.id
  const organizationId = court?.venue?.organization_id
  const activeRules = props.rules.filter((rule) => {
    if (!rule.is_active) return false
    if (rule.effective_from && day.isoDate < rule.effective_from.slice(0, 10)) return false
    if (rule.effective_until && day.isoDate > rule.effective_until.slice(0, 10)) return false
    // The quote reads only the court's own organization's rules; the courts list can span several.
    if (rule.organization_id && organizationId && rule.organization_id !== organizationId) {
      return false
    }
    if (rule.court_id && rule.court_id !== court?.id) return false
    if (rule.sport_type && rule.sport_type !== court?.sport_type) return false
    if (rule.venue_id && rule.venue_id !== venueId) return false
    return true
  })
  const compareRules = (a: PriceRuleItem, b: PriceRuleItem) => {
    const scopeRank = (rule: PriceRuleItem) =>
      rule.court_id ? 4 : rule.sport_type ? 3 : rule.venue_id ? 2 : 1
    return (
      scopeRank(b) - scopeRank(a) || b.priority - a.priority || b.version - a.version || b.id - a.id
    )
  }

  // The API sends HH:mm:ss and the quote compares HH:mm; a rule without hours covers the whole day.
  const coversSlot = (r: { starts_at?: string | null; ends_at?: string | null }) =>
    !r.starts_at ||
    !r.ends_at ||
    (r.starts_at.slice(0, 5) <= slot.starts_at && r.ends_at.slice(0, 5) >= slot.ends_at)
  const overlapsSlot = (r: { starts_at: string; ends_at: string }) =>
    r.starts_at.slice(0, 5) < slot.ends_at && r.ends_at.slice(0, 5) > slot.starts_at

  // 1. Date exception / holiday on this date
  const holidayRule = activeRules
    .filter(
      (r) =>
        (r.type === 'holiday' || r.type === 'date_exception') &&
        r.exception_date === day.isoDate &&
        coversSlot(r),
    )
    .sort(compareRules)[0]

  if (holidayRule) {
    return {
      price: holidayRule.price,
      type: 'holiday',
      ruleName: holidayRule.name || t('admin.pricing.typeHolidayOption'),
      isCustom: true,
    }
  }

  // 2. Peak; surge and special_event rules never price a quote, so they are not shown.
  const peakRule = activeRules
    .filter((r) => r.type === 'peak' && coversSlot(r))
    .sort(compareRules)[0]

  if (peakRule) {
    return {
      price: peakRule.price,
      type: 'peak',
      ruleName: peakRule.name || t('admin.pricing.typePeakOption'),
      isCustom: true,
    }
  }

  // A court's price row prices only a slot of its own length, as in the quote; an end of 00:00 or 23:59 is midnight.
  const rowMinutes = (row: { starts_at: string; ends_at: string }) => {
    const minute = (time: string) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5))
    const end = minute(row.ends_at)
    return (end === 0 || end === 1439 ? 1440 : end) - minute(row.starts_at)
  }
  // ponytail: without loaded rows only the court-wide count (inactive rows included) is known, so the hour-long rows the app writes are assumed; the planned price-coverage view replaces this.
  const hasHourlyRows = court?.price_slots
    ? court.price_slots.some(
        (row) =>
          row.weekday === day.weekday &&
          overlapsSlot(row) &&
          rowMinutes(row) === court.slot_duration,
      )
    : court?.slot_duration === 60 && Boolean(court.price_slots_count)

  // 3. Weekly rule for this weekday; the quote never matches one without a weekday. The court's
  // hourly rows are court-scoped, so only a court weekly rule outranks them.
  const weekdayRule = activeRules
    .filter((r) => r.type === 'weekly' && r.weekday === day.weekday && coversSlot(r))
    .filter((r) => Boolean(r.court_id) || !hasHourlyRows)
    .sort(compareRules)[0]

  if (weekdayRule) {
    return {
      price: weekdayRule.price,
      type: day.isWeekend ? 'weekend' : 'standard',
      ruleName: weekdayRule.name || t('admin.pricing.typeWeeklyOption'),
      isCustom: true,
    }
  }

  // 4. Configured base, but hourly court price rows on this weekday and block outrank it in the quote.
  const baseRule = activeRules.filter((r) => r.type === 'base').sort(compareRules)[0]

  if (baseRule && !hasHourlyRows) {
    return {
      price: baseRule.price,
      type: day.isWeekend ? 'weekend' : 'standard',
      ruleName: baseRule.name || t('admin.pricing.typeBaseOption'),
      isCustom: true,
    }
  }

  // Without an amount the cell names its source: the hourly table, or none, as the quote would reject it.
  const ruleName = t(hasHourlyRows ? 'admin.pricing.hourlyPriceTable' : 'admin.pricing.noPrice')
  return { price: null, type: 'unpriced', ruleName, isCustom: false }
}

const formatRate = ({ price, ruleName }: { price: number | null; ruleName: string }) =>
  price === null ? ruleName : formatCurrency(price)

function handleSlotClick(weekday: number, starts_at: string, ends_at: string) {
  emit('createForSlot', {
    weekday,
    starts_at,
    ends_at,
    court_id: activeCourt.value?.id,
  })
}
</script>

<template>
  <div class="pricing-calendar-container">
    <!-- Skeleton loader -->
    <PricingGridSkeleton v-if="loading" />

    <template v-else>
      <!-- Header & Court Selector -->
      <div class="calendar-header">
        <div class="title-col">
          <div class="title-with-icon">
            <span class="material-symbols-outlined card-title-icon">calendar_view_week</span>
            <h3 class="section-title">{{ t('admin.pricing.calendarViewTitle') }}</h3>
          </div>
          <p class="section-sub">{{ t('admin.pricing.calendarViewSub') }}</p>
        </div>

        <div class="header-controls">
          <!-- Court dropdown selector -->
          <div v-if="courts.length > 0" class="court-selector-wrap">
            <label class="control-label">{{ t('admin.pricing.selectCourtToView') }}:</label>
            <div class="court-select-container">
              <SelectDropdown
                :model-value="activeCourt?.id"
                :options="courtOptions"
                variant="bordered"
                @update:model-value="selectedCourtId = Number($event)"
              />
            </div>
          </div>

          <!-- Week navigator -->
          <div class="week-nav">
            <button
              type="button"
              class="btn-nav"
              :title="t('admin.pricing.prevWeek')"
              @click="weekOffset--"
            >
              <span class="material-symbols-outlined icon-16">chevron_left</span>
            </button>
            <span class="week-range-text font-mono">{{ weekRangeLabel }}</span>
            <button
              type="button"
              class="btn-nav"
              :title="t('admin.pricing.nextWeek')"
              @click="weekOffset++"
            >
              <span class="material-symbols-outlined icon-16">chevron_right</span>
            </button>
            <button v-if="weekOffset !== 0" type="button" class="btn-today" @click="weekOffset = 0">
              {{ t('admin.pricing.currentWeek') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Legend -->
      <div class="calendar-legend">
        <span class="legend-item"
          ><span class="legend-dot dot-peak"></span> {{ t('admin.pricing.typePeakOption') }}</span
        >
        <span class="legend-item"
          ><span class="legend-dot dot-weekend"></span> {{ t('admin.pricing.legendWeekend') }}</span
        >
        <span class="legend-item"
          ><span class="legend-dot dot-holiday"></span> {{ t('admin.pricing.legendHoliday') }}</span
        >
        <span class="legend-item"
          ><span class="legend-dot dot-standard"></span>
          {{ t('admin.pricing.legendStandard') }}</span
        >
      </div>

      <!-- 7-Day Grid Matrix -->
      <div class="week-grid">
        <div
          v-for="day in weekDays"
          :key="day.isoDate"
          class="day-column"
          :class="{ 'is-today': day.isToday, 'is-weekend': day.isWeekend }"
        >
          <!-- Day Header -->
          <div class="day-header">
            <span class="day-name">{{ day.name }}</span>
            <span class="day-date" :class="{ 'date-badge-today': day.isToday }">
              {{ day.dateNumber }}/{{ day.monthNumber }}
            </span>
          </div>

          <!-- Time Slots List -->
          <div class="day-slots">
            <div
              v-for="slot in timeSlots"
              :key="slot.starts_at"
              class="slot-card"
              :class="[
                `slot-${getSlotPricing(day, slot).type}`,
                { 'slot-custom-rule': getSlotPricing(day, slot).isCustom },
              ]"
              :title="`${getSlotPricing(day, slot).ruleName} • ${t('admin.pricing.clickToConfigureSlot')}`"
              @click="handleSlotClick(day.weekday, slot.starts_at, slot.ends_at)"
            >
              <div class="slot-time-row font-mono">
                <span class="slot-time">{{ slot.label }}</span>
                <span v-if="getSlotPricing(day, slot).type === 'peak'" class="slot-chip chip-peak"
                  >PEAK</span
                >
              </div>

              <div class="slot-rate font-mono">
                {{ formatRate(getSlotPricing(day, slot)) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.pricing-calendar-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.title-col {
  display: flex;
  flex-direction: column;
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

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.section-sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.header-controls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}

.court-selector-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.court-select-container {
  min-width: 240px;
}

.control-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  white-space: nowrap;
}

.week-nav {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  border-radius: 8px;
}

.btn-nav {
  background: transparent;
  border: none;
  color: var(--ink);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 4px;
}

.btn-nav:hover {
  background: var(--surface);
}

.week-range-text {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  padding: 0 4px;
}

.btn-today {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--pink-dark, #db2777);
  cursor: pointer;
  margin-left: 4px;
}

.calendar-legend {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  padding: 10px 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-radius: 10px;
  font-size: 12px;
  color: var(--muted);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}

.dot-peak {
  background: #db2777;
}
.dot-weekend {
  background: #8b5cf6;
}
.dot-holiday {
  background: #f59e0b;
}
.dot-standard {
  background: #10b981;
}

.week-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 10px;
  overflow-x: auto;
}

.day-column {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 120px;
}

.day-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-radius: 8px;
  border: 1px solid var(--outline);
}

.day-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.day-date {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
}

.date-badge-today {
  background: var(--pink-dark, #db2777);
  color: #fff;
  padding: 1px 6px;
  border-radius: 6px;
  font-weight: 800;
}

.day-slots {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.slot-card {
  padding: 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 4px;
  transition:
    transform 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.slot-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.slot-time-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  color: var(--muted);
}

.slot-chip {
  font-size: 9px;
  font-weight: 800;
  padding: 1px 4px;
  border-radius: 4px;
}

.chip-peak {
  background: rgba(219, 39, 119, 0.15);
  color: var(--pink-dark, #db2777);
}

.slot-rate {
  font-size: 13px;
  font-weight: 800;
  color: var(--ink);
}

/* Card type color styling */
.slot-peak {
  border-color: rgba(219, 39, 119, 0.3);
  background: rgba(219, 39, 119, 0.03);
}

.slot-peak .slot-rate {
  color: var(--pink-dark, #db2777);
}

.slot-weekend {
  border-color: rgba(139, 92, 246, 0.3);
  background: rgba(139, 92, 246, 0.02);
}

.slot-holiday {
  border-color: rgba(245, 158, 11, 0.3);
  background: rgba(245, 158, 11, 0.04);
}

.slot-holiday .slot-rate {
  color: #d97706;
}

.slot-unpriced {
  border-style: dashed;
}

.slot-unpriced .slot-rate {
  color: var(--muted);
  font-weight: 600;
}

@media (max-width: 900px) {
  .week-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 600px) {
  .week-grid {
    grid-template-columns: 1fr;
  }
}
</style>
