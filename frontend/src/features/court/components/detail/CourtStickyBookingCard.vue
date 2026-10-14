<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { formatCurrency } from '@/i18n'
import type { Court } from '../../types/court'

defineProps<{
  court: Court
  basePrice: number | null
  selectedDuration: number
  estimatedTotal: number | null
  durationOptions: Array<{ value: number; label: string }>
}>()

const emit = defineEmits<{
  (e: 'update:selectedDuration', val: number): void
}>()
</script>

<template>
  <div class="sticky-booking-card">
    <div v-if="basePrice !== null" class="card-price-header">
      <span class="price-lbl">{{ $t('courtDetail.rateLabel') }}</span>
      <div class="price-val">
        <strong class="price-num text-pink font-mono">{{ formatCurrency(basePrice) }}</strong>
        <span class="unit">{{ $t('courtDetail.perHour') }}</span>
      </div>
    </div>

    <!-- Duration Picker -->
    <div class="duration-picker-box">
      <label class="d-lbl">{{ $t('courtDetail.selectDuration') }}:</label>
      <div class="durations-grid">
        <button
          v-for="opt in durationOptions"
          :key="opt.value"
          type="button"
          class="duration-btn"
          :class="{ active: selectedDuration === opt.value }"
          @click="emit('update:selectedDuration', opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>

    <!-- Estimate Summary -->
    <div v-if="estimatedTotal !== null" class="estimate-box">
      <span>{{ $t('courtDetail.estimatedTotal', { duration: selectedDuration }) }}:</span>
      <strong class="estimate-amount text-pink font-mono">{{
        formatCurrency(estimatedTotal)
      }}</strong>
    </div>

    <!-- Book CTA -->
    <RouterLink :to="`/courts/${court.slug || court.id}/schedule`" class="btn-book-now">
      {{ $t('courtDetail.confirmAndBook') }}
    </RouterLink>
  </div>
</template>

<style scoped>
.sticky-booking-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.card-price-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.price-lbl {
  font-size: 13px;
  color: var(--muted);
}

.price-val {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.price-num {
  font-size: 22px;
  font-weight: 800;
}

.unit {
  font-size: 12px;
  color: var(--muted);
}

.text-pink {
  color: var(--primary);
}

.duration-picker-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.d-lbl {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.durations-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.duration-btn {
  padding: 8px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.duration-btn:hover:not(.active) {
  border-color: var(--accent);
  background: var(--soft);
}

.duration-btn.active {
  background: var(--primary);
  color: var(--on-primary);
  border-color: var(--primary);
}

.estimate-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--primary-container);
  border: 1px solid rgba(107, 45, 63, 0.15);
  font-size: 13px;
  color: var(--ink);
}

:global(html.dark) .estimate-box {
  border-color: rgba(229, 142, 159, 0.25);
}

.estimate-amount {
  font-size: 16px;
  font-weight: 800;
  color: var(--primary);
}

.btn-book-now {
  padding: 14px;
  border-radius: 12px;
  border: 0;
  background: var(--primary);
  color: var(--on-primary);
  font-weight: 800;
  font-size: 14px;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.2);
  transition: all 0.15s ease;
}

.btn-book-now:hover {
  background: var(--primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.28);
}

:global(html.dark) .btn-book-now {
  box-shadow: 0 4px 16px rgba(229, 142, 159, 0.25);
}

:global(html.dark) .btn-book-now:hover {
  box-shadow: 0 6px 20px rgba(229, 142, 159, 0.35);
}

@media (max-width: 640px) {
  .durations-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
