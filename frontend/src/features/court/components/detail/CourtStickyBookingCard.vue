<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { formatCurrency } from '@/i18n'
import type { Court } from '../../types/court'

defineProps<{
  court: Court
  basePrice: number | null
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
</style>
