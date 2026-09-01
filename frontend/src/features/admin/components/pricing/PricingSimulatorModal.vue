<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { t } = useI18n()

const selectedDate = ref('2026-08-30')
const selectedTime = ref('18:00')
const durationHours = ref(2)
const isHoliday = ref(false)

const calculatedQuote = computed(() => {
  let baseHourly = 120000
  const hour = parseInt(selectedTime.value.slice(0, 2), 10)
  if (hour >= 17 && hour <= 21) {
    baseHourly = 180000
  } else if (hour >= 11 && hour <= 14) {
    baseHourly = 90000
  }
  if (isHoliday.value) {
    baseHourly = Math.round(baseHourly * 1.3)
  }
  return baseHourly * durationHours.value
})
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">calculate</span>
          <h3 class="modal-title">{{ t('admin.pricing.simulatorModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p class="sim-intro">{{ t('admin.pricing.simIntro') }}</p>

        <div class="sim-form-grid">
          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.selectBookingDate') }}</label>
            <input v-model="selectedDate" type="date" class="form-input font-mono" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.selectStartTime') }}</label>
            <input v-model="selectedTime" type="time" class="form-input font-mono" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.rentalDuration') }}</label>
            <input v-model.number="durationHours" type="number" min="1" max="12" class="form-input font-mono" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.isHolidayLabel') }}</label>
            <label class="check-lbl">
              <input v-model="isHoliday" type="checkbox" />
              <span>{{ t('admin.pricing.holidaySurchargeCheckbox') }}</span>
            </label>
          </div>
        </div>

        <div class="quote-result-card">
          <span class="quote-lbl">{{ t('admin.pricing.estimatedQuote') }}</span>
          <strong class="quote-price text-pink font-mono">{{ formatCurrency(calculatedQuote) }}</strong>
          <span class="quote-note">{{ t('admin.pricing.calculatedNote') }}</span>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.close') }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.sim-intro {
  margin: 0 0 16px;
  font-size: 13px;
  color: var(--muted);
}

.sim-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.check-lbl {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  font-size: 13px;
  cursor: pointer;
}

.quote-result-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px;
  border-radius: 12px;
  background: rgba(236, 72, 153, 0.06);
  border: 1px solid rgba(236, 72, 153, 0.2);
  gap: 6px;
}

.quote-lbl {
  font-size: 12px;
  color: var(--muted);
}

.quote-price {
  font-size: 24px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.quote-note {
  font-size: 11px;
  color: var(--muted);
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
