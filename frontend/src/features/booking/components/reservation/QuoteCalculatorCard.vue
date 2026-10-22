<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { DatePicker, SelectDropdown, type DropdownOption } from '@/shared/components'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { venueClockRange } from '@/core/utils/venueTime'
import BookingAgreement from '../BookingAgreement.vue'

const { t } = useI18n()
const auth = useAuthStore()
import type { Court } from '@/features/court/types/court'
import type { PriceChange, PriceQuote } from '../../types/reservation'
import { formatDuration, validBookerPhone } from '../../utils/reservation'

const props = defineProps<{
  courts: Court[]
  selectedCourtId: number | null
  selectedDate: string
  selectedTimeSlot: string
  activeQuote: PriceQuote | null
  formattedRemaining: string
  calculating: boolean
  holding: boolean
  priceChange?: PriceChange | null
}>()

const emit = defineEmits<{
  (e: 'update:selectedCourtId', value: number): void
  (e: 'update:selectedDate', value: string): void
  (e: 'update:selectedTimeSlot', value: string): void
  (e: 'calculate', minutes: number): void
  (e: 'hold'): void
  (e: 'checkout'): void
  (e: 'confirmPrice'): void
}>()

const getLocalDateString = (offsetDays = 0): string => {
  const d = new Date()
  d.setDate(d.getDate() + offsetDays)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const todayStr = getLocalDateString(0)
const tomorrowStr = getLocalDateString(1)
const dayAfterStr = getLocalDateString(2)

const availableTimeSlots = [6, 7, 8, 9, 10, 11, 14, 15, 16, 17, 18, 19, 20, 21].map(
  (hour) => `${String(hour).padStart(2, '0')}:00`,
)

const courtOptions = computed<DropdownOption[]>(() =>
  props.courts.map((c) => ({
    value: c.id,
    label: c.code,
    sublabel: c.venue?.name ? `· ${c.venue.name}` : undefined,
    icon: 'stadium',
  })),
)

const selectedCourtObj = computed(() => props.courts.find((c) => c.id === props.selectedCourtId))
// No submission without a booker phone or while a changed price awaits confirmation.
const blocked = computed(
  () => props.holding || !validBookerPhone(auth.user?.phone) || !!props.priceChange,
)
// Booking length from the hourly start, so the end stays on the 30-minute grid (design D14).
const duration = ref(60)
const quotedRange = computed(() => {
  const parts = props.activeQuote?.slot_snapshot ?? []
  return venueClockRange(parts[0]?.starts_at, parts[parts.length - 1]?.ends_at)
})
</script>

<template>
  <section id="quote-calculator-section" class="calculator-card">
    <div class="card-header">
      <div class="header-icon-box">
        <span class="material-symbols-outlined icon">receipt_long</span>
      </div>
      <div>
        <h2 class="card-title">{{ t('reservations.calculator.title') }}</h2>
        <p class="card-desc">
          {{ t('reservations.calculator.desc') }}
        </p>
      </div>
    </div>

    <!-- Inputs Row -->
    <div class="calculator-form-grid">
      <!-- Court Selection -->
      <div class="form-group">
        <label class="field-label">
          <span class="material-symbols-outlined field-icon">stadium</span>
          <span>{{ t('reservations.calculator.courtLabel') }}</span>
        </label>
        <SelectDropdown
          :model-value="selectedCourtId ?? undefined"
          :options="courtOptions"
          variant="bordered"
          :placeholder="t('reservations.calculator.courtPlaceholder')"
          :aria-label="t('reservations.calculator.courtLabel')"
          @update:model-value="emit('update:selectedCourtId', Number($event))"
        />
        <span v-if="selectedCourtObj" class="court-sub-info">
          {{ selectedCourtObj.venue?.address || t('reservations.calculator.courtSubInfoDefault') }}
        </span>
      </div>

      <!-- Date Selection -->
      <div class="form-group">
        <label class="field-label">
          <span class="material-symbols-outlined field-icon">calendar_today</span>
          <span>{{ t('reservations.calculator.dateLabel') }}</span>
        </label>
        <div class="date-quick-presets">
          <button
            type="button"
            class="preset-btn"
            :class="{ active: selectedDate === todayStr }"
            @click="emit('update:selectedDate', todayStr)"
          >
            {{ t('reservations.calculator.today') }}
          </button>
          <button
            type="button"
            class="preset-btn"
            :class="{ active: selectedDate === tomorrowStr }"
            @click="emit('update:selectedDate', tomorrowStr)"
          >
            {{ t('reservations.calculator.tomorrow') }}
          </button>
          <button
            type="button"
            class="preset-btn"
            :class="{ active: selectedDate === dayAfterStr }"
            @click="emit('update:selectedDate', dayAfterStr)"
          >
            {{ t('reservations.calculator.dayAfter') }}
          </button>
        </div>
        <DatePicker
          :model-value="selectedDate"
          :min="todayStr"
          :placeholder="t('reservations.calculator.datePlaceholder')"
          @update:model-value="emit('update:selectedDate', $event)"
        />
      </div>

      <!-- Time Slot Selection -->
      <div class="form-group">
        <label class="field-label">
          <span class="material-symbols-outlined field-icon">schedule</span>
          <span>{{ t('reservations.calculator.timeLabel') }}</span>
        </label>
        <div class="slots-pill-grid">
          <button
            v-for="time in availableTimeSlots"
            :key="time"
            type="button"
            class="slot-chip"
            :class="{ active: selectedTimeSlot === time }"
            @click="emit('update:selectedTimeSlot', time)"
          >
            {{ time }}
          </button>
        </div>
        <span class="field-label">{{ t('reservations.calculator.durationLabel') }}</span>
        <div class="slots-pill-grid">
          <button
            v-for="minutes in [30, 60, 90, 120]"
            :key="minutes"
            type="button"
            class="slot-chip"
            :class="{ active: duration === minutes }"
            @click="duration = minutes"
          >
            {{ formatDuration(minutes) }}
          </button>
        </div>
      </div>
    </div>

    <!-- Action Button -->
    <div class="form-submit-row">
      <button
        class="btn btn-primary btn-calculate"
        :disabled="calculating || !selectedCourtId"
        @click="emit('calculate', duration)"
      >
        <span class="material-symbols-outlined" :class="{ spinning: calculating }">
          {{ calculating ? 'refresh' : 'price_change' }}
        </span>
        <span>{{
          calculating
            ? t('reservations.calculator.calculating')
            : t('reservations.calculator.calculateBtn')
        }}</span>
      </button>
    </div>

    <!-- Live Quote Preview Banner -->
    <transition name="fade">
      <div v-if="activeQuote" class="quote-preview-panel">
        <div class="quote-panel-header">
          <div class="quote-badge">
            <span class="material-symbols-outlined badge-icon">lock_clock</span>
            <span>{{ t('reservations.calculator.lockedBadge') }}</span>
          </div>
          <div class="quote-timer">
            <span class="timer-label">{{ t('reservations.calculator.expiresIn') }}</span>
            <span class="timer-countdown font-mono">{{ formattedRemaining }}</span>
          </div>
        </div>

        <div class="quote-breakdown-box">
          <div class="quote-row">
            <span class="row-label">{{ t('reservations.calculator.courtRow') }}</span>
            <span class="row-value font-bold">{{ selectedCourtObj?.code || 'Aura Court' }}</span>
          </div>
          <div class="quote-row">
            <span class="row-label">{{ t('reservations.calculator.timeRow') }}</span>
            <span class="row-value font-mono">{{ selectedDate }} · {{ quotedRange }}</span>
          </div>
          <div class="quote-row">
            <span class="row-label">{{ t('reservations.calculator.tokenRow') }}</span>
            <span class="row-value font-mono token-text"
              >#{{ activeQuote.quote_token.slice(0, 16) }}...</span
            >
          </div>
        </div>

        <BookingAgreement
          :rental="activeQuote.subtotal ?? activeQuote.total_amount"
          :payable="activeQuote.total_amount"
          :price-change="priceChange"
          :busy="holding"
          @confirm-price="emit('confirmPrice')"
        />

        <div class="quote-actions-row">
          <p class="quote-note">
            {{ t('reservations.calculator.instantNote') }}
          </p>
          <div class="quote-action-buttons">
            <button class="btn btn-checkout-slot" :disabled="blocked" @click="emit('checkout')">
              <span class="material-symbols-outlined" :class="{ spinning: holding }">
                {{ holding ? 'hourglass_top' : 'account_balance_wallet' }}
              </span>
              <span>{{
                holding
                  ? t('reservations.calculator.paying')
                  : t('reservations.calculator.payAndBookNow')
              }}</span>
            </button>
            <button class="btn btn-hold-slot" :disabled="blocked" @click="emit('hold')">
              <span class="material-symbols-outlined" :class="{ spinning: holding }">
                {{ holding ? 'hourglass_top' : 'bookmark_add' }}
              </span>
              <span>{{
                holding
                  ? t('reservations.calculator.holding')
                  : t('reservations.calculator.holdTemporary')
              }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </section>
</template>

<style scoped>
.calculator-card {
  padding: 26px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.03);
}

.card-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 22px;
}

.header-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--primary-container, rgba(107, 45, 63, 0.1));
  color: var(--primary, #6b2d3f);
  flex-shrink: 0;
}

.card-title {
  font-size: 18px;
  font-weight: 800;
  margin: 0 0 4px;
  color: var(--ink);
}

.card-desc {
  font-size: 13.5px;
  color: var(--muted);
  margin: 0;
  line-height: 1.5;
}

.calculator-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1.2fr;
  gap: 18px;
  margin-bottom: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.field-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.field-icon {
  font-size: 16px;
  color: var(--primary, #6b2d3f);
}

.form-select,
.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  color: var(--ink);
  font-size: 13.5px;
  box-sizing: border-box;
  outline: none;
  transition: all 0.2s ease;
}

.form-select:focus,
.form-input:focus {
  border-color: var(--primary, #6b2d3f);
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.12);
}

.form-group :deep(.custom-dropdown),
.form-group :deep(.date-picker) {
  width: 100%;
  min-width: 0;
}

.form-group :deep(.custom-dropdown.bordered .custom-dropdown-trigger) {
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  min-height: 42px;
  padding: 10px 14px;
}

.form-group :deep(.date-picker-trigger) {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 500;
  box-sizing: border-box;
  min-height: 42px;
  transition: all 0.2s ease;
}

.form-group :deep(.date-picker-popover) {
  z-index: 100;
  left: 0;
  right: auto;
}

.court-sub-info {
  font-size: 11.5px;
  color: var(--muted);
  margin-top: 2px;
}

.date-quick-presets {
  display: flex;
  gap: 6px;
  margin-bottom: 4px;
}

.preset-btn {
  flex: 1;
  padding: 5px 8px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  color: var(--muted);
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.preset-btn:hover {
  background: var(--soft);
  color: var(--ink);
}

.preset-btn.active {
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  border-color: var(--primary, #6b2d3f);
}

.slots-pill-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}

.slot-chip {
  padding: 7px 2px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 700;
  text-align: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.slot-chip:hover {
  background: var(--soft);
  border-color: var(--outline);
}

.slot-chip.active {
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  border-color: var(--primary, #6b2d3f);
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.25);
}

.form-submit-row {
  display: flex;
  justify-content: flex-end;
}

.btn-calculate {
  padding: 10px 24px;
  font-size: 14px;
}

.quote-preview-panel {
  margin-top: 24px;
  padding: 20px;
  background: linear-gradient(135deg, rgba(201, 161, 90, 0.08) 0%, rgba(107, 45, 63, 0.06) 100%);
  border: 1.5px solid var(--accent, #c9a15a);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.quote-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.quote-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 9999px;
  background: var(--accent-container, rgba(201, 161, 90, 0.2));
  color: var(--on-accent-container, #684f17);
  font-size: 11.5px;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.badge-icon {
  font-size: 15px;
}

.quote-timer {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}

.timer-label {
  color: var(--muted);
}

.timer-countdown {
  font-size: 15px;
  font-weight: 800;
  color: #c62828;
}

.quote-breakdown-box {
  background: var(--surface);
  border-radius: 12px;
  padding: 14px 18px;
  border: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.quote-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13.5px;
}

.row-label {
  color: var(--muted);
}

.row-value {
  color: var(--ink);
}

.token-text {
  font-size: 11.5px;
  color: var(--muted);
}

.quote-actions-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.quote-note {
  font-size: 12px;
  color: var(--muted);
  margin: 0;
}

.quote-action-buttons {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-checkout-slot {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 9999px;
  background: var(--primary, #6b2d3f);
  color: #fff;
  font-size: 13.5px;
  font-weight: 800;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.3);
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-checkout-slot:hover:not(:disabled) {
  filter: brightness(1.1);
  transform: translateY(-1px);
}

.btn-hold-slot {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 9999px;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  border: 1px solid var(--border);
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-hold-slot:hover:not(:disabled) {
  background: var(--surface-hover, rgba(0, 0, 0, 0.04));
  color: var(--ink);
}

@media (max-width: 900px) {
  .calculator-form-grid {
    grid-template-columns: 1fr;
  }
  .slots-pill-grid {
    grid-template-columns: repeat(4, 1fr);
  }
  .quote-actions-row {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
