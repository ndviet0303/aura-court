<script setup lang="ts">
import { computed } from 'vue'
import type { ResaleListing } from '../types/marketplace'
import { AuraButton } from '@/shared/components'

const props = defineProps<{
  listing: ResaleListing
}>()

const emit = defineEmits<{
  (e: 'buy', listing: ResaleListing): void
}>()

const discountPercent = computed(() => {
  if (!props.listing.originalPrice || props.listing.originalPrice <= props.listing.resalePrice) {
    return 0
  }
  return Math.round(((props.listing.originalPrice - props.listing.resalePrice) / props.listing.originalPrice) * 100)
})

function formatVND(amount?: number): string {
  if (!amount) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
}
</script>

<template>
  <div class="resale-card" :class="{ 'is-featured': listing.isFeatured }">
    <!-- Card Header -->
    <div class="card-header">
      <div class="venue-info">
        <span class="material-symbols-outlined venue-icon">location_on</span>
        <span class="venue-name">{{ listing.venueName || 'Aura Court' }}</span>
      </div>

      <!-- Badge (Hot / Deal / Urgent) -->
      <div v-if="listing.badge" class="badge-chip" :class="listing.badge">
        <span class="material-symbols-outlined badge-icon">
          {{ listing.badge === 'hot' ? 'local_fire_department' : listing.badge === 'urgent' ? 'bolt' : 'savings' }}
        </span>
        <span class="badge-text">
          {{ listing.badge === 'hot' ? 'Giờ Vàng' : listing.badge === 'urgent' ? 'Pass Gấp' : 'Giá Hời' }}
        </span>
      </div>
    </div>

    <!-- Court Title -->
    <h3 class="court-title">{{ listing.courtName }}</h3>

    <!-- Schedule Info Box -->
    <div class="schedule-box">
      <div class="meta-row">
        <div class="meta-item">
          <span class="material-symbols-outlined meta-icon">calendar_today</span>
          <span class="meta-label">Ngày chơi</span>
          <span class="meta-value highlight">{{ listing.dateStr }}</span>
        </div>
        <div class="meta-item">
          <span class="material-symbols-outlined meta-icon">schedule</span>
          <span class="meta-label">Khung giờ</span>
          <span class="meta-value">{{ listing.timeSlot }}</span>
        </div>
      </div>

      <!-- Seller & Escrow Guarantee -->
      <div class="seller-row">
        <div class="seller-info">
          <span class="material-symbols-outlined seller-avatar-icon">account_circle</span>
          <span class="seller-name">{{ listing.sellerName }}</span>
        </div>
        <div class="escrow-chip">
          <span class="material-symbols-outlined escrow-icon">verified_user</span>
          <span>Aura Escrow</span>
        </div>
      </div>
    </div>

    <!-- Seller Note -->
    <p v-if="listing.notes" class="seller-note">
      "{{ listing.notes }}"
    </p>

    <!-- Card Footer: Pricing & Action -->
    <div class="card-footer">
      <div class="pricing-wrap">
        <div v-if="discountPercent > 0" class="original-price-wrap">
          <span class="original-price">{{ formatVND(listing.originalPrice) }}</span>
          <span class="discount-pill">-{{ discountPercent }}%</span>
        </div>
        <div class="resale-price">{{ formatVND(listing.resalePrice) }}</div>
      </div>

      <AuraButton
        variant="primary"
        size="sm"
        icon="payments"
        class="btn-buy"
        @click="emit('buy', listing)"
      >
        Mua Lại
      </AuraButton>
    </div>
  </div>
</template>

<style scoped>
.resale-card {
  position: relative;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: var(--shadow-sm);
}

.resale-card:hover {
  transform: translateY(-3px);
  border-color: var(--primary, #6B2D3F);
  box-shadow: 0 12px 28px rgba(107, 45, 63, 0.12);
}

:global(html.dark) .resale-card:hover {
  border-color: var(--accent, #C9A15A);
  box-shadow: 0 12px 28px rgba(201, 161, 90, 0.12);
}

.resale-card.is-featured {
  border-color: rgba(201, 161, 90, 0.4);
  background: linear-gradient(180deg, var(--surface) 0%, var(--surface-subtle, rgba(201, 161, 90, 0.03)) 100%);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.venue-info {
  display: flex;
  align-items: center;
  gap: 4px;
  color: var(--muted);
  font-size: 12px;
  font-weight: 500;
}

.venue-icon {
  font-size: 16px;
  color: var(--accent, #C9A15A);
}

.badge-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
}

.badge-chip.hot {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.badge-chip.urgent {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.25);
}

.badge-chip.deal {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.badge-icon {
  font-size: 14px;
}

.court-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
  margin: 0;
  line-height: 1.3;
}

.schedule-box {
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  border-radius: 14px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.meta-icon {
  font-size: 14px;
  color: var(--muted);
  display: none;
}

.meta-label {
  font-size: 10.5px;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  font-weight: 600;
}

.meta-value {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.meta-value.highlight {
  color: var(--primary, #6B2D3F);
}

:global(html.dark) .meta-value.highlight {
  color: var(--on-surface, #FAF7F3);
}

.seller-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding-top: 8px;
  border-top: 1px dashed var(--outline);
}

.seller-info {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--ink);
  font-weight: 600;
}

.seller-avatar-icon {
  font-size: 18px;
  color: var(--muted);
}

.escrow-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  color: #10b981;
}

.escrow-icon {
  font-size: 14px;
}

.seller-note {
  font-size: 12px;
  color: var(--muted);
  font-style: italic;
  margin: 0;
  line-height: 1.4;
}

.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: auto;
  padding-top: 12px;
}

.pricing-wrap {
  display: flex;
  flex-direction: column;
}

.original-price-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.original-price {
  font-size: 11.5px;
  color: var(--muted);
  text-decoration: line-through;
}

.discount-pill {
  font-size: 10.5px;
  font-weight: 800;
  color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
  padding: 1px 5px;
  border-radius: 4px;
}

.resale-price {
  font-size: 17px;
  font-weight: 850;
  color: var(--ink);
  letter-spacing: -0.01em;
}

:global(html.dark) .resale-price {
  color: #ffffff;
}

.btn-buy {
  padding: 8px 16px;
}
</style>
