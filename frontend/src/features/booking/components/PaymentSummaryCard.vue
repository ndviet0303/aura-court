<script setup lang="ts">
import { computed } from 'vue'
import type { PaymentInfo, Booking } from '../types/booking'
import type { Court } from '@/features/court/types/court'

const props = defineProps<{
  payment: PaymentInfo
  booking: Booking | null
  court: Court | null
  countdownFormatted: string
  couponCode: string
  couponLoading?: boolean
  couponError?: string
  couponSuccess?: string
}>()

const emit = defineEmits<{
  (e: 'update:couponCode', val: string): void
  (e: 'applyCoupon'): void
}>()

const isTournament = computed(() => {
  return props.payment.type === 'tournament' || !!props.payment.tournament
})

const tournament = computed(() => props.payment.tournament)
const athleteDetails = computed(() => props.payment.athleteDetails)

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="summary-card-dark">
    <!-- Header -->
    <div class="summary-header">
      <h3 class="summary-title">
        <span class="material-symbols-outlined summary-icon">
          {{ isTournament ? 'emoji_events' : 'receipt_long' }}
        </span>
        <span>{{ isTournament ? 'Thông Tin Lệ Phí Giải Đấu' : 'Thông Tin Đơn Đặt Sân' }}</span>
      </h3>
      <div class="countdown-tag">
        <span class="material-symbols-outlined timer-icon">timer</span>
        <span>Hết hạn:</span>
        <strong class="font-mono">{{ countdownFormatted }}</strong>
      </div>
    </div>

    <!-- Tournament Specific Meta Box -->
    <div v-if="isTournament" class="tourney-summary-meta">
      <div v-if="tournament?.image_url" class="tourney-mini-thumb">
        <img :src="tournament.image_url" :alt="tournament.name" class="thumb-img" />
        <div class="thumb-badge">{{ tournament.category || tournament.sport }}</div>
      </div>

      <div class="tourney-info-block">
        <h4 class="tourney-heading">{{ tournament?.name || payment.title || 'Giải Đấu Aura Circuit' }}</h4>
        <div class="meta-row">
          <span class="material-symbols-outlined mini-ico">location_on</span>
          <span>{{ tournament?.venue || 'Aura Sport Complex' }} ({{ tournament?.city || 'TP. Hồ Chí Minh' }})</span>
        </div>
        <div class="meta-row">
          <span class="material-symbols-outlined mini-ico">calendar_month</span>
          <span>{{ tournament?.dates || 'Tháng 09/2026' }}</span>
        </div>
      </div>

      <div class="athlete-meta-list">
        <div class="summary-row">
          <span>Mã ghi danh:</span>
          <strong class="font-mono text-pink">{{ payment.code }}</strong>
        </div>
        <div v-if="athleteDetails?.name" class="summary-row">
          <span>VĐV đăng ký:</span>
          <strong>{{ athleteDetails.name }}</strong>
        </div>
        <div v-if="athleteDetails?.partnerName" class="summary-row">
          <span>Đồng đội (Partner):</span>
          <strong>{{ athleteDetails.partnerName }}</strong>
        </div>
        <div v-if="tournament?.prize_pool" class="summary-row">
          <span>Tổng quỹ thưởng:</span>
          <strong class="prize-tag">{{ tournament.prize_pool }}</strong>
        </div>
      </div>
    </div>

    <!-- Standard Court Booking Meta Info -->
    <div v-else class="summary-meta-info">
      <div class="summary-row">
        <span>Mã đặt sân:</span>
        <strong class="font-mono text-pink">{{ payment.code }}</strong>
      </div>
      <div v-if="court" class="summary-row">
        <span>Sân đặt:</span>
        <strong>{{ court.name }}</strong>
      </div>
      <div v-if="booking?.slots?.length" class="summary-row">
        <span>Số khung giờ:</span>
        <strong>{{ booking.slots.length }} Slot</strong>
      </div>
    </div>

    <!-- Coupon Input Block -->
    <div class="summary-coupon-block">
      <div class="coupon-input-group">
        <input
          :value="couponCode"
          type="text"
          class="coupon-text-input"
          :placeholder="isTournament ? 'Nhập mã ưu đãi giải (Vd: AURA2026)...' : 'Nhập mã ưu đãi...'"
          @input="emit('update:couponCode', ($event.target as HTMLInputElement).value)"
        />
        <button
          class="btn-apply-coupon"
          :disabled="couponLoading || !couponCode.trim()"
          @click="emit('applyCoupon')"
        >
          <span v-if="couponLoading">...</span>
          <span v-else>Áp Dụng</span>
        </button>
      </div>

      <div v-if="couponSuccess" class="coupon-msg-success">{{ couponSuccess }}</div>
      <div v-if="couponError" class="coupon-msg-error">{{ couponError }}</div>
    </div>

    <!-- Price Breakdown -->
    <div class="summary-price-breakdown">
      <div class="breakdown-row">
        <span>{{ isTournament ? 'Lệ phí tham gia:' : 'Tiền thuê sân:' }}</span>
        <span>{{ formatMoney(payment.amount + (booking?.discount_amount || 0)) }}</span>
      </div>
      <div v-if="booking?.discount_amount" class="breakdown-row discount">
        <span>Khuyến mãi / Giảm giá:</span>
        <span>-{{ formatMoney(booking.discount_amount) }}</span>
      </div>
      <div class="breakdown-row total">
        <strong class="total-label">TỔNG THANH TOÁN:</strong>
        <strong class="total-amount">{{ formatMoney(payment.amount) }}</strong>
      </div>
    </div>
  </div>
</template>

<style scoped>
.summary-card-dark {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.summary-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--outline);
}

.summary-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}

.summary-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.countdown-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
}

.timer-icon {
  font-size: 14px;
}

/* Tournament mini metadata box */
.tourney-summary-meta {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tourney-mini-thumb {
  position: relative;
  width: 100%;
  height: 90px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--outline);
}

.thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.thumb-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
  color: #fff;
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.tourney-info-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.tourney-heading {
  margin: 0;
  font-size: 14.5px;
  font-weight: 800;
  color: var(--ink);
}

.meta-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

.mini-ico {
  font-size: 15px;
  color: var(--pink-dark, #db2777);
}

.athlete-meta-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  font-size: 12.5px;
}

.prize-tag {
  color: var(--accent, #c9a15a);
}

.summary-meta-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 13px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  color: var(--muted);
}

.summary-row strong {
  color: var(--ink);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.summary-coupon-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.coupon-input-group {
  display: flex;
  gap: 8px;
}

.coupon-text-input {
  flex: 1;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  text-transform: uppercase;
}

.btn-apply-coupon {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.coupon-msg-success {
  font-size: 12px;
  color: #10b981;
}

.coupon-msg-error {
  font-size: 12px;
  color: #ef4444;
}

.summary-price-breakdown {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px dashed var(--outline);
  font-size: 13px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
}

.breakdown-row.discount {
  color: #10b981;
}

.breakdown-row.total {
  margin-top: 6px;
  padding-top: 8px;
  border-top: 1px solid var(--outline);
  font-size: 16px;
}

.total-amount {
  color: var(--pink-dark, #db2777);
}
</style>
