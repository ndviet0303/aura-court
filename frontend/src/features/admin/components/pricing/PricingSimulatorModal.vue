<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown, DatePicker, TimePicker } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { Court } from '@/features/court/types/court'
import type { PriceQuoteResponse, PriceRuleItem } from '../../types/pricing'

const props = defineProps<{
  show: boolean
  courts: Court[]
  rules: PriceRuleItem[]
  simulating?: boolean
  quoteResult: PriceQuoteResponse | null
  quoteError?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'simulate', payload: { courtId: number; dateStr: string; startTime: string; durationHours: number }): void
}>()

const { t } = useI18n()

// Form state
const todayStr = new Date().toISOString().split('T')[0] || '2026-09-02'
const selectedCourtId = ref<number | ''>('')
const selectedDate = ref(todayStr)
const selectedTime = ref('18:00')
const durationHours = ref(2)

const courtOptions = computed<DropdownOption[]>(() =>
  props.courts.map((c) => ({
    value: c.id,
    label: `${resolveLocalizedText(c.name)}${c.venue ? ` (${resolveLocalizedText(c.venue.name)})` : ''}`,
    icon: 'sports_tennis',
  }))
)

// Auto-select first court when courts load
watch(
  () => props.courts,
  (cList) => {
    if (cList.length > 0 && !selectedCourtId.value && cList[0]) {
      selectedCourtId.value = cList[0].id
    }
  },
  { immediate: true }
)

function runSimulation() {
  if (!selectedCourtId.value) return
  emit('simulate', {
    courtId: Number(selectedCourtId.value),
    dateStr: selectedDate.value || todayStr,
    startTime: selectedTime.value || '18:00',
    durationHours: durationHours.value || 1,
  })
}

// Fallback dynamic local preview if API is awaiting
const localEstimatedQuote = computed(() => {
  if (props.quoteResult) return null

  const baseRate = 120000
  const [hStr] = selectedTime.value.split(':')
  const hour = parseInt(hStr || '18', 10)

  let hourly = baseRate
  if (hour >= 17 && hour <= 21) {
    hourly = Math.round(baseRate * 1.5)
  } else if (hour >= 11 && hour <= 14) {
    hourly = Math.round(baseRate * 0.75)
  }

  return {
    total: hourly * durationHours.value,
    hourly,
  }
})
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">calculate</span>
          <h3 class="modal-title">{{ t('admin.pricing.simulatorModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <p class="sim-intro">{{ t('admin.pricing.simIntro') }}</p>

        <!-- Form Grid -->
        <div class="sim-form-grid">
          <!-- Court Selector -->
          <div class="form-group span-2">
            <label class="form-label">{{ t('admin.pricing.courtLabel') }}</label>
            <SelectDropdown
              v-model="selectedCourtId"
              :options="courtOptions"
              variant="bordered"
              :placeholder="t('admin.pricing.allCourtsInScope')"
            />
          </div>

          <!-- Date Selector -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.selectBookingDate') }}</label>
            <DatePicker v-model="selectedDate" />
          </div>

          <!-- Start Time -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.selectStartTime') }}</label>
            <TimePicker v-model="selectedTime" />
          </div>

          <!-- Duration -->
          <div class="form-group span-2">
            <label class="form-label">{{ t('admin.pricing.rentalDuration') }}</label>
            <div class="duration-buttons">
              <button
                v-for="hrs in [1, 2, 3, 4]"
                :key="hrs"
                type="button"
                class="duration-btn"
                :class="{ active: durationHours === hrs }"
                @click="durationHours = hrs"
              >
                {{ hrs }} {{ t('admin.pricing.hourUnit') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Run button -->
        <div class="action-btn-row">
          <AuraButton
            variant="primary"
            class="w-full"
            :disabled="simulating || !selectedCourtId"
            :loading="simulating"
            @click="runSimulation"
          >
            <span class="material-symbols-outlined icon-18">play_arrow</span>
            <span>{{ simulating ? t('admin.pricing.simCalculating') : t('admin.pricing.simCalculateBtn') }}</span>
          </AuraButton>
        </div>

        <!-- Error Banner -->
        <div v-if="quoteError" class="error-banner">
          <span class="material-symbols-outlined icon-16">error</span>
          <span>{{ quoteError }}</span>
        </div>

        <!-- Quote Result Card -->
        <div v-if="quoteResult" class="quote-result-card">
          <div class="result-header">
            <span class="quote-lbl">{{ t('admin.pricing.estimatedQuote') }}</span>
            <strong class="quote-price text-pink font-mono">
              {{ formatCurrency(quoteResult.total_amount) }}
            </strong>
          </div>

          <!-- Slot by slot breakdown -->
          <div v-if="quoteResult.slot_snapshot && quoteResult.slot_snapshot.length > 0" class="slots-breakdown">
            <div
              v-for="(slot, idx) in quoteResult.slot_snapshot"
              :key="idx"
              class="breakdown-row"
            >
              <span class="font-mono slot-time-badge">
                {{ slot.starts_at ? new Date(slot.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : `Slot ${idx + 1}` }}
                -
                {{ slot.ends_at ? new Date(slot.ends_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '' }}
              </span>
              <span class="applied-rule-tag">
                {{ slot.rule?.type ? slot.rule.type.toUpperCase() : 'BASE RULE' }}
              </span>
              <strong class="slot-amt font-mono">{{ formatCurrency(slot.unit_price) }}</strong>
            </div>
          </div>

          <div class="result-footer">
            <span class="material-symbols-outlined icon-14 text-green">verified</span>
            <span class="quote-note">{{ t('admin.pricing.calculatedNote') }}</span>
          </div>
        </div>

        <!-- Local preview fallback -->
        <div v-else-if="localEstimatedQuote" class="quote-result-card preview-card">
          <div class="result-header">
            <span class="quote-lbl">{{ t('admin.pricing.estimatedQuotePreview') }}</span>
            <strong class="quote-price text-pink font-mono">
              {{ formatCurrency(localEstimatedQuote.total) }}
            </strong>
          </div>
          <span class="quote-note">{{ t('admin.pricing.previewFormula', { duration: durationHours, rate: formatCurrency(localEstimatedQuote.hourly) }) }}</span>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">{{ t('common.close') }}</AuraButton>
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
  max-width: 580px;
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

.sim-intro {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  line-height: 1.5;
}

.sim-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.span-2 {
  grid-column: span 2;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.duration-buttons {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.duration-btn {
  padding: 9px 8px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.duration-btn.active {
  background: var(--pink-dark, #db2777);
  border-color: var(--pink-dark, #db2777);
  color: #fff;
}

.action-btn-row {
  margin-top: 4px;
}

.w-full {
  width: 100%;
  justify-content: center;
}

.error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: rgba(239, 68, 68, 0.1);
  border-radius: 8px;
  color: #ef4444;
  font-size: 13px;
}

.quote-result-card {
  padding: 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.preview-card {
  border-style: dashed;
}

.result-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.quote-lbl {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.quote-price {
  font-size: 22px;
  font-weight: 900;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.slots-breakdown {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px;
  background: var(--surface);
  border-radius: 8px;
  border: 1px solid var(--outline);
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  padding: 4px 0;
  border-bottom: 1px dashed var(--outline);
}

.breakdown-row:last-child {
  border-bottom: none;
}

.slot-time-badge {
  font-weight: 600;
  color: var(--ink);
}

.applied-rule-tag {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  border-radius: 4px;
}

.slot-amt {
  font-weight: 700;
  color: var(--ink);
}

.result-footer {
  display: flex;
  align-items: center;
  gap: 6px;
}

.quote-note {
  font-size: 12px;
  color: var(--muted);
}

.text-green {
  color: #10b981;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
}
</style>
