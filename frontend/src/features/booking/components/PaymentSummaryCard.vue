<script setup lang="ts">
import type { PaymentInfo, Booking } from '../types/booking'
import type { Court } from '@/features/court/types/court'

defineProps<{
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

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="summary-card-dark">
    <div class="summary-header">
      <h3 class="summary-title">📋 Thông Tin Đơn Đặt Sân</h3>
      <div class="countdown-tag">
        ⏱️ Hết hạn trong: <strong>{{ countdownFormatted }}</strong>
      </div>
    </div>

    <div class="summary-meta-info">
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
          placeholder="Nhập mã ưu đãi..."
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
        <span>Tiền thuê sân:</span>
        <span>{{ formatMoney(payment.amount + (booking?.discount_amount || 0)) }}</span>
      </div>
      <div v-if="booking?.discount_amount" class="breakdown-row discount">
        <span>Khuyến mãi / Giảm giá:</span>
        <span>-{{ formatMoney(booking.discount_amount) }}</span>
      </div>
      <div class="breakdown-row total">
        <strong class="total-label">TỔNG TIỀN:</strong>
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
}

.countdown-tag {
  font-size: 12px;
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
  padding: 2px 8px;
  border-radius: 6px;
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
