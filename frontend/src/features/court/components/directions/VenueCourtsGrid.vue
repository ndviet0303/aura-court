<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { formatCurrency } from '@/i18n'
import type { Court } from '../../types/court'

defineProps<{
  courts: Court[]
}>()

// No invented price: a court without a price shows none.
function getCourtPrice(c: Court): number | null {
  return c.from_price ?? null
}
</script>

<template>
  <div class="venue-courts-section">
    <div class="section-header">
      <h3 class="section-title">
        <span class="material-symbols-outlined icon-20">sports_tennis</span>
        <span>{{ $t('directions.courtsAtLocation') }}</span>
      </h3>
      <span class="court-count-badge">{{
        $t('directions.readyCourts', { count: courts.length })
      }}</span>
    </div>

    <div class="courts-grid">
      <div v-for="c in courts" :key="c.id" class="court-item-card">
        <div class="card-top-info">
          <span class="sport-tag">{{ $t(`sportTypes.${c.sport_type}`) }}</span>
          <strong class="court-name">{{ c.name }}</strong>
        </div>

        <div v-if="getCourtPrice(c) !== null" class="card-price-row">
          <span class="price-lbl">{{ $t('directions.priceFrom') }}</span>
          <strong class="price-val font-mono">{{ formatCurrency(getCourtPrice(c)!) }} /h</strong>
        </div>

        <div class="card-actions-row">
          <RouterLink :to="`/courts/${c.slug || c.id}/schedule`" class="btn-schedule-action">
            <span class="material-symbols-outlined icon-14">event_available</span>
            <span>{{ $t('directions.bookNow') }}</span>
          </RouterLink>

          <RouterLink :to="`/courts/${c.slug || c.id}`" class="btn-detail-action">
            <span>{{ $t('directions.details') }}</span>
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.venue-courts-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .venue-courts-section {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.icon-20 {
  font-size: 20px;
  color: var(--primary);
}

:global(html.dark) .icon-20 {
  color: #e58e9f;
}

.court-count-badge {
  font-size: 11.5px;
  font-weight: 700;
  padding: 2px 10px;
  border-radius: 999px;
  background: var(--primary-container);
  color: var(--primary);
}

:global(html.dark) .court-count-badge {
  background: rgba(229, 142, 159, 0.15);
  color: #e58e9f;
}

.courts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 14px;
}

.court-item-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 12px;
  padding: 16px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

:global(html.dark) .court-item-card {
  background: #231c20;
  border-color: #382d33;
}

.court-item-card:hover {
  border-color: var(--accent);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
}

:global(html.dark) .court-item-card:hover {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
  border-color: #d8b46e;
}

.card-top-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sport-tag {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--accent);
}

:global(html.dark) .sport-tag {
  color: #d8b46e;
}

.court-name {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink);
}

.card-price-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  font-size: 12px;
  color: var(--muted);
  padding-top: 4px;
  border-top: 1px solid var(--outline);
}

:global(html.dark) .card-price-row {
  border-color: #30262c;
}

.price-val {
  font-size: 13.5px;
  color: var(--primary);
  font-weight: 800;
}

:global(html.dark) .price-val {
  color: #e58e9f;
}

.card-actions-row {
  display: flex;
  gap: 8px;
}

.btn-schedule-action {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 8px 12px;
  border-radius: 10px;
  background: var(--primary);
  color: var(--on-primary);
  font-size: 12px;
  font-weight: 800;
  text-decoration: none;
  box-shadow: 0 2px 10px rgba(107, 45, 63, 0.2);
  transition: all 0.2s ease;
}

.btn-schedule-action:hover {
  background: var(--primary-hover);
  transform: translateY(-1px);
}

:global(html.dark) .btn-schedule-action {
  background: #e58e9f;
  color: #1c0a10;
}

.btn-detail-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-detail-action {
  background: #1a1518;
  border-color: #382d33;
  color: #e8e2e5;
}

.btn-detail-action:hover {
  border-color: var(--accent);
  color: var(--accent);
}

.icon-14 {
  font-size: 14px;
}
</style>
