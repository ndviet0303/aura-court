<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Booking } from '../../types/booking'

const props = defineProps<{
  booking: Booking
}>()

const emit = defineEmits<{
  (e: 'cancel', booking: Booking): void
  (e: 'review', booking: Booking): void
  (e: 'qr', booking: Booking): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function formatDate(iso: string): string {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    return d.toLocaleDateString('vi-VN', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return iso.slice(0, 10)
  }
}

const isConfirmed = computed(() => props.booking.status === 'confirmed')
const isPendingPayment = computed(() => props.booking.status === 'pending_payment')
const isCancelled = computed(() => ['cancelled', 'expired', 'refunded'].includes(props.booking.status))

const statusMeta = computed(() => {
  if (isConfirmed.value) {
    return { label: 'ĐÃ XÁC NHẬN', icon: 'verified', class: 'status-confirmed' }
  }
  if (isPendingPayment.value) {
    return { label: 'CHỜ THANH TOÁN', icon: 'schedule', class: 'status-pending' }
  }
  if (props.booking.status === 'refunded') {
    return { label: 'ĐÃ HOÀN TIỀN', icon: 'currency_exchange', class: 'status-refunded' }
  }
  return { label: 'ĐÃ HUỶ', icon: 'cancel', class: 'status-cancelled' }
})

const sportLabel = computed(() => {
  const st = props.booking.court?.sport_zone?.sport_type || 'badminton'
  const map: Record<string, string> = {
    badminton: 'Cầu Lông',
    pickleball: 'Pickleball',
    tennis: 'Tennis',
    football_5v5: 'Bóng Đá 5v5',
    football_7v7: 'Bóng Đá 7v7',
    basketball: 'Bóng Rổ',
    volleyball: 'Bóng Chuyền',
    table_tennis: 'Bóng Bàn',
  }
  return map[st] || 'Sân Thể Thao'
})
</script>

<template>
  <div class="user-booking-card" :class="`border-${booking.status}`">
    <!-- Card Top Header -->
    <div class="card-header">
      <div class="header-main">
        <div class="code-and-type">
          <span class="booking-code font-mono">#{{ booking.code }}</span>
          <span class="sport-badge font-mono">{{ sportLabel.toUpperCase() }}</span>
        </div>
        <h3 class="court-name">
          <RouterLink
            v-if="booking.court?.slug"
            :to="`/courts/${booking.court.slug}`"
            class="court-link"
          >
            {{ booking.court?.name || 'Sân Aura Court' }}
          </RouterLink>
          <span v-else>{{ booking.court?.name || 'Sân Aura Court' }}</span>
        </h3>
        <div class="venue-info">
          <span class="material-symbols-outlined venue-icon">location_on</span>
          <span class="venue-text">{{ booking.court?.venue?.name || 'Aura Badminton Center' }}</span>
          <RouterLink
            v-if="booking.court?.slug"
            :to="`/courts/${booking.court.slug}/directions`"
            class="directions-link"
            title="Xem chỉ đường"
          >
            (Chỉ đường)
          </RouterLink>
        </div>
      </div>

      <div class="status-chip" :class="statusMeta.class">
        <span class="material-symbols-outlined status-icon">{{ statusMeta.icon }}</span>
        <span>{{ statusMeta.label }}</span>
      </div>
    </div>

    <!-- Slots Schedule Details -->
    <div class="slots-box">
      <div v-for="s in booking.slots" :key="s.starts_at" class="slot-item">
        <div class="slot-time-col">
          <span class="material-symbols-outlined slot-icon">schedule</span>
          <span class="slot-time font-mono">{{ s.starts_at.slice(11, 16) }} - {{ s.ends_at.slice(11, 16) }}</span>
        </div>
        <div class="slot-date-col">
          <span class="material-symbols-outlined slot-icon">calendar_today</span>
          <span class="slot-date">{{ formatDate(s.starts_at) }}</span>
        </div>
        <div class="slot-price-col">
          <span class="slot-price font-mono">{{ formatMoney(s.unit_price) }}</span>
        </div>
      </div>

      <div v-if="booking.discount_amount && booking.discount_amount > 0" class="discount-row">
        <span class="discount-label">Mã ưu đãi ({{ booking.coupon?.code || 'COUPON' }}):</span>
        <span class="discount-value font-mono">-{{ formatMoney(booking.discount_amount) }}</span>
      </div>

      <div v-if="booking.membership_discount_amount && booking.membership_discount_amount > 0" class="discount-row">
        <span class="discount-label">Ưu đãi Hội viên VIP:</span>
        <span class="discount-value font-mono">-{{ formatMoney(booking.membership_discount_amount) }}</span>
      </div>
    </div>

    <!-- Card Bottom & Actions -->
    <div class="card-footer">
      <div class="total-section">
        <span class="total-label">Tổng thanh toán:</span>
        <strong class="total-amount font-mono">{{ formatMoney(booking.total_amount) }}</strong>
      </div>

      <div class="actions-group">
        <!-- Pay Now (if pending) -->
        <RouterLink
          v-if="isPendingPayment"
          :to="`/payment/${booking.code}`"
          class="btn-card-pay"
        >
          <span class="material-symbols-outlined">payments</span>
          <span>Thanh Toán Ngay</span>
        </RouterLink>

        <!-- QR Check-in Pass -->
        <button
          v-if="isConfirmed || isPendingPayment"
          type="button"
          class="btn-card-secondary"
          @click="emit('qr', booking)"
        >
          <span class="material-symbols-outlined">qr_code_2</span>
          <span>Mã Check-in</span>
        </button>

        <!-- Split Bill with teammates -->
        <RouterLink
          :to="`/split-bill?booking=${booking.code}`"
          class="btn-card-secondary"
        >
          <span class="material-symbols-outlined">group</span>
          <span>Chia Tiền</span>
        </RouterLink>

        <!-- Review (if confirmed) -->
        <button
          v-if="isConfirmed"
          type="button"
          class="btn-card-secondary"
          @click="emit('review', booking)"
        >
          <span class="material-symbols-outlined">rate_review</span>
          <span>{{ booking.review ? `Đánh Giá (${booking.review.rating}★)` : 'Đánh Giá' }}</span>
        </button>

        <!-- Cancel Booking (if pending or confirmed) -->
        <button
          v-if="!isCancelled"
          type="button"
          class="btn-card-danger"
          @click="emit('cancel', booking)"
        >
          <span class="material-symbols-outlined">close</span>
          <span>Huỷ Đơn</span>
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
  padding: 22px 24px;
  border-radius: 18px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 2px 12px rgba(107, 45, 63, 0.03);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.user-booking-card:hover {
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.08);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.header-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.code-and-type {
  display: flex;
  align-items: center;
  gap: 8px;
}

.booking-code {
  font-size: 12px;
  font-weight: 800;
  color: var(--primary, #6B2D3F);
}

.sport-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.04));
  color: var(--muted);
  border: 1px solid var(--border);
}

.court-name {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.court-link {
  color: var(--ink);
  text-decoration: none;
}

.court-link:hover {
  color: var(--primary, #6B2D3F);
  text-decoration: underline;
}

.venue-info {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12.5px;
  color: var(--muted);
}

.venue-icon {
  font-size: 15px;
  color: var(--primary, #6B2D3F);
}

.directions-link {
  color: var(--accent, #C9A15A);
  text-decoration: none;
  font-size: 12px;
  font-weight: 600;
  margin-left: 4px;
}

.directions-link:hover {
  text-decoration: underline;
}

.status-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 800;
  padding: 4px 10px;
  border-radius: 8px;
  white-space: nowrap;
}

.status-icon {
  font-size: 15px;
}

.status-confirmed {
  background: var(--success-bg, rgba(46, 125, 50, 0.12));
  color: var(--success-text, #2e7d32);
}

.status-pending {
  background: var(--warning-bg, rgba(239, 108, 0, 0.12));
  color: var(--warning-text, #ef6c00);
}

.status-cancelled {
  background: var(--error-bg, rgba(198, 40, 40, 0.12));
  color: var(--error-text, #c62828);
}

.status-refunded {
  background: var(--purple-bg, rgba(123, 31, 162, 0.12));
  color: var(--purple-text, #7b1fa2);
}

.slots-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 16px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--border);
}

.slot-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
  gap: 12px;
  flex-wrap: wrap;
}

.slot-time-col, .slot-date-col {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--ink);
}

.slot-icon {
  font-size: 15px;
  color: var(--muted);
}

.slot-price-col {
  font-weight: 700;
  color: var(--primary, #6B2D3F);
}

.discount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: var(--success-text, #2e7d32);
  padding-top: 6px;
  border-top: 1px dashed var(--border);
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--outline);
}

.total-section {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.total-label {
  font-size: 12.5px;
  color: var(--muted);
}

.total-amount {
  font-size: 18px;
  font-weight: 800;
  color: var(--primary, #6B2D3F);
}

.actions-group {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-card-pay {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 10px;
  border: 0;
  background: var(--primary, #6B2D3F);
  color: var(--on-primary, #fff);
  font-size: 12.5px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.2);
}

.btn-card-secondary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-card-secondary:hover {
  border-color: var(--primary, #6B2D3F);
  color: var(--primary, #6B2D3F);
}

.btn-card-danger {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid rgba(198, 40, 40, 0.2);
  background: transparent;
  color: var(--error-text, #c62828);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-card-danger:hover {
  background: var(--error-bg, rgba(198, 40, 40, 0.08));
}

.btn-card-pay .material-symbols-outlined,
.btn-card-secondary .material-symbols-outlined,
.btn-card-danger .material-symbols-outlined {
  font-size: 16px;
}
</style>
