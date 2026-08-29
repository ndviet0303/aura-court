<script setup lang="ts">
import type { ResaleListing } from '../types/marketplace'

defineProps<{
  listing: ResaleListing
}>()

const emit = defineEmits<{
  (e: 'buy', listing: ResaleListing): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="resale-card" :class="{ 'is-featured': listing.isFeatured }">
    <div class="card-top">
      <div class="venue-info">
        <span class="venue-name font-mono">🏢 {{ listing.venueName }}</span>
        <h3 class="court-title">{{ listing.courtName }}</h3>
      </div>

      <span
        v-if="listing.badge"
        class="badge-pill"
        :class="`badge-${listing.badge}`"
      >
        {{ listing.badge === 'hot' ? '🔥 GIỜ VÀNG' : listing.badge === 'urgent' ? '⚡ CẦN PASS GẤP' : '🏷️ GIÁ TỐT' }}
      </span>
    </div>

    <div class="slot-details">
      <div class="detail-row">
        <span>📅 Ngày chơi:</span>
        <strong>{{ listing.dateStr }}</strong>
      </div>
      <div class="detail-row">
        <span>⏰ Khung giờ:</span>
        <strong class="text-pink font-mono">{{ listing.timeSlot }}</strong>
      </div>
      <div class="detail-row">
        <span>👤 Người pass:</span>
        <span>{{ listing.sellerName }} (Uy tín 5.0 ⭐)</span>
      </div>
    </div>

    <div class="pricing-bottom">
      <div class="price-box">
        <span class="orig-price">{{ formatMoney(listing.originalPrice) }}</span>
        <strong class="resale-price text-pink font-mono">{{ formatMoney(listing.resalePrice) }}</strong>
      </div>

      <button class="btn-buy-slot" @click="emit('buy', listing)">
        ⚡ Nhận Lịch Ngay
      </button>
    </div>
  </div>
</template>

<style scoped>
.resale-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: transform 0.15s, box-shadow 0.15s;
}

.resale-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.resale-card.is-featured {
  border-color: rgba(236, 72, 153, 0.4);
  box-shadow: 0 4px 16px rgba(236, 72, 153, 0.08);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}

.venue-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.venue-name {
  font-size: 11px;
  color: var(--muted);
}

.court-title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
}

.badge-pill {
  font-size: 10px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 6px;
}

.badge-hot { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.badge-urgent { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.badge-deal { background: rgba(16, 185, 129, 0.1); color: #10b981; }

.slot-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
}

.pricing-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid var(--outline);
}

.price-box {
  display: flex;
  flex-direction: column;
}

.orig-price {
  font-size: 11px;
  text-decoration: line-through;
  color: var(--muted);
}

.resale-price {
  font-size: 18px;
}

.text-pink { color: var(--pink-dark, #db2777); }

.btn-buy-slot {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
</style>
