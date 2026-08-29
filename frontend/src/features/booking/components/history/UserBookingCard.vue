<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { Booking } from '../../types/booking'

defineProps<{
  booking: Booking
}>()

const emit = defineEmits<{
  (e: 'cancel', booking: Booking): void
  (e: 'review', booking: Booking): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="user-booking-card">
    <div class="card-top">
      <div class="code-venue">
        <span class="booking-code font-mono">#{{ booking.code }}</span>
        <h3 class="court-name">{{ booking.court?.name || 'Sân Cầu Lông Aura' }}</h3>
        <span class="venue-name">📍 {{ booking.court?.venue?.name || 'Aura Badminton Center' }}</span>
      </div>

      <span
        class="status-chip"
        :class="`status-${booking.status}`"
      >
        {{ booking.status === 'confirmed' ? '● ĐÃ XÁC NHẬN' : booking.status === 'pending_payment' ? '⏳ CHỜ THANH TOÁN' : '✕ ĐÃ HUỶ' }}
      </span>
    </div>

    <!-- Slots breakdown -->
    <div class="slots-list">
      <div v-for="s in booking.slots" :key="s.starts_at" class="slot-item">
        <span class="slot-time font-mono">⏰ {{ s.starts_at.slice(11, 16) }} - {{ s.ends_at.slice(11, 16) }}</span>
        <span class="slot-date">📅 {{ s.starts_at.slice(0, 10) }}</span>
        <strong class="slot-price text-pink font-mono">{{ formatMoney(s.unit_price) }}</strong>
      </div>
    </div>

    <div class="card-bottom">
      <div class="total-wrap">
        <span class="total-lbl">Tổng thanh toán:</span>
        <strong class="total-val text-pink font-mono">{{ formatMoney(booking.total_amount) }}</strong>
      </div>

      <div class="card-actions">
        <RouterLink
          v-if="booking.status === 'pending_payment'"
          :to="`/payment/${booking.code}`"
          class="btn-pay-now"
        >
          ⚡ Thanh Toán Ngay
        </RouterLink>

        <RouterLink
          :to="`/split-bill?booking=${booking.code}`"
          class="btn-split"
        >
          👥 Chia Tiền Nhóm
        </RouterLink>

        <button
          v-if="booking.status === 'confirmed'"
          class="btn-secondary"
          @click="emit('review', booking)"
        >
          ⭐ Đánh Giá
        </button>

        <button
          v-if="['pending_payment', 'confirmed'].includes(booking.status)"
          class="btn-danger"
          @click="emit('cancel', booking)"
        >
          Huỷ Đơn
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-booking-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.code-venue {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.booking-code {
  font-size: 11px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.court-name {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.venue-name {
  font-size: 12px;
  color: var(--muted);
}

.status-chip {
  font-size: 10px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 6px;
}

.status-confirmed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-pending_payment { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.status-cancelled { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

.slots-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.slot-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}

.card-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
}

.total-wrap {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.total-lbl { font-size: 12px; color: var(--muted); }
.total-val { font-size: 16px; }
.text-pink { color: var(--pink-dark, #db2777); }

.card-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-pay-now {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
}

.btn-split, .btn-secondary {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.btn-danger {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid rgba(239, 68, 68, 0.2);
  background: transparent;
  color: #ef4444;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
</style>
