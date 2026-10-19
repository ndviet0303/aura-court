<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown, DatePicker, TimePicker } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { PriceRuleItem, PriceRuleFormPayload, PriceRuleType } from '../../types/pricing'
import type { Venue, Court, SportType } from '@/features/court/types/court'

const props = defineProps<{
  show: boolean
  rule: PriceRuleItem | null
  venues: Venue[]
  courts: Court[]
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: PriceRuleFormPayload): void
}>()

const { t } = useI18n()

// Form state
const name = ref('')
const type = ref<PriceRuleType>('peak')
const venueId = ref<number | ''>('')
const sportType = ref<SportType | ''>('')
const courtId = ref<number | ''>('')
const weekday = ref<number | null>(null)
const exceptionDate = ref<string>(new Date().toISOString().slice(0, 10))
const effectiveFrom = ref('')
const effectiveUntil = ref('')
const startsAt = ref<string>('17:00')
const endsAt = ref<string>('21:00')
const isAllDay = ref<boolean>(false)
const price = ref<number>(150000)
const priority = ref<number>(2)
const isActive = ref<boolean>(true)

// Dropdown Options
const typeOptions = computed<DropdownOption[]>(() => [
  { value: 'peak', label: t('admin.pricing.typePeakOption'), icon: 'trending_up' },
  { value: 'holiday', label: t('admin.pricing.typeHolidayOption'), icon: 'celebration' },
  { value: 'weekly', label: t('admin.pricing.typeWeeklyOption'), icon: 'calendar_view_week' },
  { value: 'standard', label: t('admin.pricing.typeStandardOption'), icon: 'check_circle' },
  { value: 'base', label: t('admin.pricing.typeBaseOption'), icon: 'layers' },
  {
    value: 'date_exception',
    label: t('admin.pricing.typeDateExceptionOption'),
    icon: 'event_busy',
  },
  { value: 'surge', label: t('admin.pricing.typeSurgeOption'), icon: 'bolt' },
  { value: 'special_event', label: t('admin.pricing.typeSpecialEventOption'), icon: 'stars' },
])

const venueOptions = computed<DropdownOption[]>(() => [
  { value: '', label: t('admin.pricing.scopeAllVenues') },
  ...props.venues.map((v) => ({
    value: v.id,
    label: resolveLocalizedText(v.name),
    icon: 'location_on',
  })),
])

const venueCourts = computed(() =>
  venueId.value
    ? props.courts.filter((c) => (c.venue_id || c.venue?.id) === venueId.value)
    : props.courts,
)

const sportOptions = computed<DropdownOption[]>(() => {
  const sports = new Set(venueId.value ? venueCourts.value.map((c) => c.sport_type) : [])
  // An edited rule keeps its sport listed even when the venue has no court of it any more.
  if (sportType.value) sports.add(sportType.value)
  return [
    { value: '', label: t('admin.pricing.allSportTypes') },
    ...[...sports].map((s) => ({ value: s, label: t(`sportTypes.${s}`), icon: 'category' })),
  ]
})

const availableCourts = computed(() =>
  sportType.value
    ? venueCourts.value.filter((c) => c.sport_type === sportType.value)
    : venueCourts.value,
)

const courtOptions = computed<DropdownOption[]>(() => [
  { value: '', label: t('admin.pricing.allCourtsInScope') },
  ...availableCourts.value.map((c) => ({
    value: c.id,
    label: resolveLocalizedText(c.name),
    icon: 'sports_tennis',
  })),
])

watch(
  () => props.rule,
  (r) => {
    if (r) {
      name.value = r.name || ''
      type.value = r.type || 'peak'
      venueId.value = r.venue_id || ''
      sportType.value = r.sport_type || ''
      courtId.value = r.court_id || ''
      weekday.value = r.weekday || null
      exceptionDate.value = r.exception_date || new Date().toISOString().slice(0, 10)
      effectiveFrom.value = r.effective_from?.slice(0, 10) || ''
      effectiveUntil.value = r.effective_until?.slice(0, 10) || ''
      if (r.starts_at && r.ends_at) {
        startsAt.value = r.starts_at.slice(0, 5)
        endsAt.value = r.ends_at.slice(0, 5)
        isAllDay.value = false
      } else {
        startsAt.value = '06:00'
        endsAt.value = '22:00'
        isAllDay.value = true
      }
      price.value = r.price || 150000
      priority.value = r.priority ?? 2
      isActive.value = r.is_active ?? true
    } else {
      name.value = ''
      type.value = 'peak'
      venueId.value = ''
      sportType.value = ''
      courtId.value = ''
      weekday.value = null
      exceptionDate.value = new Date().toISOString().slice(0, 10)
      effectiveFrom.value = ''
      effectiveUntil.value = ''
      startsAt.value = '17:00'
      endsAt.value = '21:00'
      isAllDay.value = false
      price.value = 150000
      priority.value = 2
      isActive.value = true
    }
  },
  { immediate: true },
)

function handleVenueChange(val: string | number) {
  venueId.value = val ? Number(val) : ''
  sportType.value = ''
  courtId.value = ''
}

function handleSportChange(val: string | number) {
  sportType.value = val as SportType | ''
  courtId.value = ''
}

function handleCourtChange(val: string | number) {
  courtId.value = val ? Number(val) : ''
}

function setTimePreset(start: string, end: string) {
  startsAt.value = start
  endsAt.value = end
  isAllDay.value = false
}

function setAllDay() {
  isAllDay.value = true
}

function handleSave() {
  if (price.value < 0) return

  emit('save', {
    name: name.value.trim() || undefined,
    type: type.value,
    venue_id: venueId.value ? Number(venueId.value) : null,
    sport_type: sportType.value || null,
    court_id: courtId.value ? Number(courtId.value) : null,
    weekday: type.value === 'weekly' ? weekday.value || 1 : null,
    exception_date: ['holiday', 'date_exception', 'special_event'].includes(type.value)
      ? exceptionDate.value
      : null,
    effective_from: effectiveFrom.value || null,
    effective_until: effectiveUntil.value || null,
    starts_at: isAllDay.value ? null : startsAt.value,
    ends_at: isAllDay.value ? null : endsAt.value,
    price: Math.round(price.value),
    priority: priority.value || 1,
    is_active: isActive.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">
            {{ rule ? 'edit' : 'add_circle' }}
          </span>
          <h3 class="modal-title">
            {{ rule ? t('admin.pricing.editRuleTitle') : t('admin.pricing.createRuleTitle') }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <form class="rule-form" @submit.prevent="handleSave">
          <!-- Row 1: Rule Name & Rule Type -->
          <div class="form-row">
            <div class="form-group flex-2">
              <label class="form-label">{{ t('admin.pricing.ruleNameLabel') }}</label>
              <input
                v-model="name"
                type="text"
                class="form-input"
                :placeholder="t('admin.pricing.ruleNamePlaceholder')"
              />
            </div>

            <div class="form-group flex-1">
              <label class="form-label required">{{ t('admin.pricing.ruleTypeLabel') }}</label>
              <SelectDropdown v-model="type" :options="typeOptions" variant="bordered" />
            </div>
          </div>

          <!-- Row 2: Target Scope (Venue -> Sport -> Court) -->
          <div class="form-section">
            <h4 class="section-heading">{{ t('admin.pricing.scopeHeading') }}</h4>
            <div class="form-row">
              <div class="form-group flex-1">
                <label class="form-label">{{ t('admin.pricing.venueLabel') }}</label>
                <SelectDropdown
                  :model-value="venueId"
                  :options="venueOptions"
                  variant="bordered"
                  @update:model-value="handleVenueChange"
                />
              </div>

              <div class="form-group flex-1">
                <label class="form-label">{{ t('admin.pricing.sportTypeLabel') }}</label>
                <SelectDropdown
                  :model-value="sportType"
                  :options="sportOptions"
                  :disabled="!venueId"
                  variant="bordered"
                  :aria-label="t('admin.pricing.sportTypeLabel')"
                  @update:model-value="handleSportChange"
                />
              </div>

              <div class="form-group flex-1">
                <label class="form-label">{{ t('admin.pricing.courtLabel') }}</label>
                <SelectDropdown
                  :model-value="courtId"
                  :options="courtOptions"
                  variant="bordered"
                  @update:model-value="handleCourtChange"
                />
              </div>
            </div>
          </div>

          <!-- Row 3: Conditional Date / Weekday -->
          <div v-if="type === 'weekly'" class="form-group">
            <label class="form-label required">{{ t('admin.pricing.selectWeekdayLabel') }}</label>
            <div class="weekday-buttons">
              <button
                v-for="d in 7"
                :key="d"
                type="button"
                class="weekday-btn"
                :class="{ active: weekday === d }"
                @click="weekday = d"
              >
                {{ d === 7 ? t('admin.pricing.sundayShort') : `T${d + 1}` }}
              </button>
            </div>
          </div>

          <div
            v-else-if="['holiday', 'date_exception', 'special_event'].includes(type)"
            class="form-group"
          >
            <label class="form-label required">{{ t('admin.pricing.exceptionDateLabel') }}</label>
            <DatePicker v-model="exceptionDate" />
          </div>

          <!-- Row 4: Time Window & Presets -->
          <div class="form-section">
            <div class="time-header-row">
              <label class="form-label">{{ t('admin.pricing.applicableHoursLabel') }}</label>
              <div class="time-presets">
                <button
                  type="button"
                  class="preset-pill"
                  :class="{ active: isAllDay }"
                  @click="setAllDay"
                >
                  {{ t('admin.pricing.presetAllDay') }}
                </button>
                <button type="button" class="preset-pill" @click="setTimePreset('06:00', '11:00')">
                  06:00 - 11:00
                </button>
                <button type="button" class="preset-pill" @click="setTimePreset('11:00', '14:00')">
                  11:00 - 14:00
                </button>
                <button type="button" class="preset-pill" @click="setTimePreset('17:00', '21:00')">
                  17:00 - 21:00
                </button>
                <button type="button" class="preset-pill" @click="setTimePreset('21:00', '23:00')">
                  21:00 - 23:00
                </button>
              </div>
            </div>

            <div v-if="!isAllDay" class="time-range-row">
              <div class="time-picker-wrapper">
                <span class="picker-addon">{{ t('admin.pricing.fromTime') }}</span>
                <TimePicker v-model="startsAt" />
              </div>
              <span class="material-symbols-outlined time-arrow">arrow_forward</span>
              <div class="time-picker-wrapper">
                <span class="picker-addon">{{ t('admin.pricing.toTime') }}</span>
                <TimePicker v-model="endsAt" />
              </div>
            </div>
            <div v-else class="all-day-banner">
              <span class="material-symbols-outlined icon-16 text-pink">schedule</span>
              <span>{{ t('admin.pricing.presetAllDay') }} (00:00 - 23:59)</span>
            </div>
          </div>

          <div class="form-section">
            <h4 class="section-heading">{{ t('admin.pricing.effectiveDatesHeading') }}</h4>
            <div class="form-row">
              <div class="form-group flex-1">
                <label class="form-label">{{ t('admin.pricing.effectiveFromLabel') }}</label>
                <DatePicker v-model="effectiveFrom" data-testid="price-rule-effective-from" />
              </div>
              <div class="form-group flex-1">
                <label class="form-label">{{ t('admin.pricing.effectiveUntilLabel') }}</label>
                <DatePicker v-model="effectiveUntil" data-testid="price-rule-effective-until" />
              </div>
            </div>
            <span class="field-hint">{{ t('admin.pricing.effectiveDatesHint') }}</span>
          </div>

          <!-- Row 5: Price VND & Priority -->
          <div class="form-row">
            <div class="form-group flex-2">
              <label class="form-label required">{{ t('admin.pricing.priceAmountLabel') }}</label>
              <div class="price-input-wrap">
                <input
                  v-model.number="price"
                  type="number"
                  min="0"
                  step="10000"
                  class="form-input font-mono price-input"
                  required
                />
                <span class="price-preview font-mono">
                  {{ formatCurrency(price || 0) }} / {{ t('admin.pricing.slotUnit') }}
                </span>
              </div>
            </div>

            <div class="form-group flex-1">
              <label class="form-label">{{ t('admin.pricing.priorityLabel') }}</label>
              <input
                v-model.number="priority"
                type="number"
                min="0"
                max="10000"
                class="form-input font-mono"
              />
              <span class="field-hint">{{ t('admin.pricing.priorityHint') }}</span>
            </div>
          </div>

          <!-- Row 6: Active Switch -->
          <div class="form-group switch-group">
            <label class="toggle-switch">
              <input v-model="isActive" type="checkbox" />
              <span class="slider round"></span>
            </label>
            <span class="switch-label">{{ t('admin.pricing.activeStatusLabel') }}</span>
          </div>
        </form>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="outline" :disabled="saving" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="primary"
          :disabled="saving || price < 0"
          :loading="saving"
          @click="handleSave"
        >
          {{ t('admin.pricing.saveRuleBtn') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  width: 100%;
  max-width: 680px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
}

.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.rule-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-row {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
}

.flex-1 {
  flex: 1;
  min-width: 180px;
}
.flex-2 {
  flex: 2;
  min-width: 220px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-radius: 12px;
  border: 1px solid var(--outline);
}

.section-heading {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.form-label.required::after {
  content: ' *';
  color: #ef4444;
}

.form-input {
  padding: 9px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  transition: border-color 0.15s ease;
  width: 100%;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.weekday-buttons {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}

.weekday-btn {
  padding: 8px 4px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.weekday-btn.active {
  background: var(--pink-dark, #db2777);
  border-color: var(--pink-dark, #db2777);
  color: #fff;
}

.time-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.time-presets {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.preset-pill {
  font-size: 11px;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  font-family: monospace;
}

.preset-pill:hover,
.preset-pill.active {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.08);
}

.time-range-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.time-picker-wrapper {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
}

.picker-addon {
  font-size: 11px;
  color: var(--muted);
  font-weight: 700;
  white-space: nowrap;
}

.time-arrow {
  color: var(--muted);
  font-size: 18px;
}

.all-day-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: rgba(219, 39, 119, 0.08);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--pink-dark, #db2777);
}

.price-input-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}

.price-input {
  flex: 1;
  font-size: 15px;
  font-weight: 700;
}

.price-preview {
  font-size: 13px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  white-space: nowrap;
}

.field-hint {
  font-size: 11px;
  color: var(--muted);
}

.switch-group {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 12px;
  margin-top: 4px;
}

.switch-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 42px;
  height: 24px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--outline);
  transition: 0.2s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: '';
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.2s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #10b981;
}

input:checked + .slider:before {
  transform: translateX(18px);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
}
</style>
