<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { DatePicker, SelectDropdown, type DropdownOption } from '@/shared/components'
import LoyaltyPointSelector from '../LoyaltyPointSelector.vue'

const { t } = useI18n()
import type { Court } from '@/features/court/types/court'
import type { V2PriceQuote } from '../../types/v2Reservation'

const props = defineProps<{
  courts: Court[]
  selectedCourtId: number | null
  selectedDate: string
  selectedTimeSlot: string
  activeQuote: V2PriceQuote | null
  formattedRemaining: string
  calculating: boolean
  holding: boolean
  money: (value: number) => string
  formatDate: (value: string) => string
  formatTimeSlot: (startsAt: string, endsAt?: string) => string
}>()

const emit = defineEmits<{
  (e: 'update:selectedCourtId', value: number): void
  (e: 'update:selectedDate', value: string): void
  (e: 'update:selectedTimeSlot', value: string): void
  (e: 'calculate'): void
  (e: 'hold'): void
  (e: 'checkout', loyaltyPayload?: any): void
  (e: 'loyaltyChange', payload: any): void
}>()

const currentLoyaltyPayload = ref<any>(null)
function onLoyaltyChange(data: any) {
  currentLoyaltyPayload.value = data
  emit('loyaltyChange', data)
}
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

const availableTimeSlots = [
  '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
  '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00'
]

const courtOptions = computed<DropdownOption[]>(() =>
  props.courts.map((c) => ({
    value: c.id,
    label: c.name,
    sublabel: c.venue?.name ? `· ${c.venue.name}` : undefined,
    icon: 'stadium',
  }))
)

const selectedCourtObj = computed(() =>
  props.courts.find((c) => c.id === props.selectedCourtId)
)

function setDatePreset(date: string) {
  emit('update:selectedDate', date)
}
</script>

<template>
  <section id="quote-calculator-section" class="v2-calculator-card">
    <div class="card-header">
      <div class="header-icon-box">
        <span class="material-symbols-outlined icon">receipt_long</span>
      </div>
      <div>
        <h2 class="card-title">{{ t('v2Reservations.calculator.title') }}</h2>
        <p class="card-desc">
          {{ t('v2Reservations.calculator.desc') }}
        </p>
      </div>
    </div>

    <!-- Inputs Row -->
    <div class="calculator-form-grid">
      <!-- Court Selection -->
      <div class="form-group">
        <label class="field-label">
          <span class="material-symbols-outlined field-icon">stadium</span>
          <span>{{ t('v2Reservations.calculator.courtLabel') }}</span>
        </label>
        <SelectDropdown
          :model-value="selectedCourtId ?? undefined"
          :options="courtOptions"
          variant="bordered"
          :placeholder="t('v2Reservations.calculator.courtPlaceholder')"
          :aria-label="t('v2Reservations.calculator.courtLabel')"
          @update:model-value="emit('update:selectedCourtId', Number($event))"
        />
        <span v-if="selectedCourtObj" class="court-sub-info">
          {{ selectedCourtObj.venue?.address || t('v2Reservations.calculator.courtSubInfoDefault') }}
        </span>
      </div>

      <!-- Date Selection -->
      <div class="form-group">
        <label class="field-label">
          <span class="material-symbols-outlined field-icon">calendar_today</span>
          <span>{{ t('v2Reservations.calculator.dateLabel') }}</span>
        </label>
        <div class="date-quick-presets">
          <button
            type="button"
            class="preset-btn"
            :class="{ active: selectedDate === todayStr }"
            @click="setDatePreset(todayStr)"
          >
            {{ t('v2Reservations.calculator.today') }}
          </button>
          <button
            type="button"
            class="preset-btn"
            :class="{ active: selectedDate === tomorrowStr }"
            @click="setDatePreset(tomorrowStr)"
          >
            {{ t('v2Reservations.calculator.tomorrow') }}
          </button>
          <button
            type="button"
            class="preset-btn"
            :class="{ active: selectedDate === dayAfterStr }"
            @click="setDatePreset(dayAfterStr)"
          >
            {{ t('v2Reservations.calculator.dayAfter') }}
          </button>
        </div>
        <DatePicker
          :model-value="selectedDate"
          :min="todayStr"
          :placeholder="t('v2Reservations.calculator.datePlaceholder')"
          @update:model-value="emit('update:selectedDate', $event)"
        />
      </div>

      <!-- Time Slot Selection -->
      <div class="form-group">
        <label class="field-label">
          <span class="material-symbols-outlined field-icon">schedule</span>
          <span>{{ t('v2Reservations.calculator.timeLabel') }}</span>
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
      </div>
    </div>

    <!-- Action Button -->
    <div class="form-submit-row">
      <button
        class="btn btn-primary btn-calculate"
        :disabled="calculating || !selectedCourtId"
        @click="emit('calculate')"
      >
        <span class="material-symbols-outlined" :class="{ spinning: calculating }">
          {{ calculating ? 'refresh' : 'price_change' }}
        </span>
        <span>{{ calculating ? t('v2Reservations.calculator.calculating') : t('v2Reservations.calculator.calculateBtn') }}</span>
      </button>
    </div>

    <!-- Live Quote Preview Banner -->
    <transition name="fade">
      <div v-if="activeQuote" class="quote-preview-panel">
        <div class="quote-panel-header">
          <div class="quote-badge">
            <span class="material-symbols-outlined badge-icon">lock_clock</span>
            <span>{{ t('v2Reservations.calculator.lockedBadge') }}</span>
          </div>
          <div class="quote-timer">
            <span class="timer-label">{{ t('v2Reservations.calculator.expiresIn') }}</span>
            <span class="timer-countdown font-mono">{{ formattedRemaining }}</span>
          </div>
        </div>

        <div class="quote-breakdown-box">
          <div class="quote-row">
            <span class="row-label">{{ t('v2Reservations.calculator.courtRow') }}</span>
            <span class="row-value font-bold">{{ selectedCourtObj?.name || 'Aura Court' }}</span>
          </div>
          <div class="quote-row">
            <span class="row-label">{{ t('v2Reservations.calculator.timeRow') }}</span>
            <span class="row-value font-mono">
              {{ selectedDate }} · {{ selectedTimeSlot }} - {{ String(Number(selectedTimeSlot.slice(0, 2)) + 1).padStart(2, '0') }}:00
            </span>
          </div>
          <div class="quote-row">
            <span class="row-label">{{ t('v2Reservations.calculator.tokenRow') }}</span>
            <span class="row-value font-mono token-text">#{{ activeQuote.quote_token.slice(0, 16) }}...</span>
          </div>
          <div class="quote-divider"></div>
          <div v-if="activeQuote.benefit_snapshot?.membership_discount_amount" class="quote-row discount">
            <span class="row-label">{{ t('v2Reservations.calculator.membershipDiscount') }}</span>
            <span class="row-value font-mono">-{{ money(activeQuote.benefit_snapshot.membership_discount_amount) }}</span>
          </div>
          <div class="quote-row total">
            <span class="total-label">{{ t('v2Reservations.calculator.totalCost') }}</span>
            <span class="total-value font-mono">{{ money(activeQuote.total_amount) }}</span>
          </div>
        </div>

        <!-- Customer Loyalty Point Selector -->
        <LoyaltyPointSelector
          :quote-token="activeQuote.quote_token"
          @change="onLoyaltyChange"
        />

        <div class="quote-actions-row">
          <p class="quote-note">
            {{ t('v2Reservations.calculator.instantNote') }}
          </p>
          <div class="quote-action-buttons">
            <button
              class="btn btn-checkout-slot"
              :disabled="holding"
              @click="emit('checkout', currentLoyaltyPayload)"
            >
              <span class="material-symbols-outlined" :class="{ spinning: holding }">
                {{ holding ? 'hourglass_top' : 'account_balance_wallet' }}
              </span>
              <span>{{ holding ? t('v2Reservations.calculator.paying') : t('v2Reservations.calculator.payAndBookNow') }}</span>
            </button>
            <button
              class="btn btn-hold-slot"
              :disabled="holding"
              @click="emit('hold')"
            >
              <span class="material-symbols-outlined" :class="{ spinning: holding }">
                {{ holding ? 'hourglass_top' : 'bookmark_add' }}
              </span>
              <span>{{ holding ? t('v2Reservations.calculator.holding') : t('v2Reservations.calculator.holdTemporary') }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </section>
</template>

<style scoped>
.v2-calculator-card {
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
  color: var(--primary, #6B2D3F);
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
  color: var(--primary, #6B2D3F);
}

.form-select, .form-input {
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

.form-select:focus, .form-input:focus {
  border-color: var(--primary, #6B2D3F);
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
  background: var(--primary, #6B2D3F);
  color: #ffffff;
  border-color: var(--primary, #6B2D3F);
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
  background: var(--primary, #6B2D3F);
  color: #ffffff;
  border-color: var(--primary, #6B2D3F);
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
  border: 1.5px solid var(--accent, #C9A15A);
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
  color: var(--on-accent-container, #684F17);
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

.quote-divider {
  height: 1px;
  background: var(--border);
  margin: 4px 0;
}

.quote-row.total {
  font-size: 16px;
}

.total-label {
  font-weight: 800;
  color: var(--ink);
}

.total-value {
  font-size: 19px;
  font-weight: 900;
  color: var(--primary, #6B2D3F);
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
  background: var(--primary, #6B2D3F);
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
