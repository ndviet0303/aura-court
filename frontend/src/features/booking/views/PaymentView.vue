<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { usePaymentProcessing } from '../composables/usePaymentProcessing'

import PaymentMethodSelector from '../components/PaymentMethodSelector.vue'
import PaymentSummaryCard from '../components/PaymentSummaryCard.vue'

const router = useRouter()
const store = useBookingStore()

const {
  payment,
  booking,
  court,
  playerDetails,
  error,
  copyNotice,
  selectedMethod,
  showSuccessModal,
  couponCode,
  couponLoading,
  couponError,
  couponSuccess,
  cardNumber,
  cardExpiry,
  cardCvc,
  cardName,
  isCardProcessing,
  countdownFormatted,
  applyCoupon,
  handleStripePay,
  copyToClipboard,
  startTimers,
  stopTimers,
} = usePaymentProcessing()

onMounted(() => {
  startTimers()
})

onUnmounted(() => {
  stopTimers()
})
</script>

<template>
  <div class="payment-view-page">
    <!-- Empty State if No Payment Session -->
    <div v-if="!payment" class="empty-payment-panel">
      <div class="empty-icon">🏸</div>
      <h2>Chưa Có Phiên Thanh Toán Nào</h2>
      <p>Vui lòng chọn sân và khung giờ để tiến hành đặt sân.</p>
      <RouterLink to="/booking" class="btn-choose-court">
        ← Quay Lại Chọn Sân
      </RouterLink>
    </div>

    <!-- Active Payment Session -->
    <div v-else class="payment-checkout-container">
      <!-- Stepper Header -->
      <div class="checkout-stepper">
        <div class="stepper-step completed">
          <span class="step-circle">✓</span>
          <span class="step-text">Chọn Sân & Giờ</span>
        </div>
        <div class="stepper-line"></div>
        <div class="stepper-step active">
          <span class="step-circle">2</span>
          <span class="step-text">Thanh Toán</span>
        </div>
        <div class="stepper-line"></div>
        <div class="stepper-step">
          <span class="step-circle">3</span>
          <span class="step-text">Xác Nhận Đặt Sân</span>
        </div>
      </div>

      <!-- Main Columns: Left (Player Info & Methods), Right (Summary) -->
      <div class="checkout-main-grid">
        <div class="checkout-left-col">
          <!-- Player Details Panel -->
          <div class="player-details-panel">
            <h3 class="panel-title">👤 Thông Tin Khách Hàng</h3>
            <div class="player-info-grid">
              <div class="info-item">
                <span class="info-label">Họ và tên:</span>
                <strong class="info-val">{{ playerDetails.name }}</strong>
              </div>
              <div class="info-item">
                <span class="info-label">Email:</span>
                <strong class="info-val">{{ playerDetails.email }}</strong>
              </div>
              <div class="info-item">
                <span class="info-label">Số điện thoại:</span>
                <strong class="info-val">{{ playerDetails.phone }}</strong>
              </div>
            </div>
          </div>

          <!-- Payment Methods Selector -->
          <PaymentMethodSelector
            :payment="payment"
            :selected-method="selectedMethod"
            :card-number="cardNumber"
            :card-expiry="cardExpiry"
            :card-cvc="cardCvc"
            :card-name="cardName"
            :is-card-processing="isCardProcessing"
            :copy-notice="copyNotice"
            @update:selected-method="selectedMethod = $event"
            @update:card-number="cardNumber = $event"
            @update:card-expiry="cardExpiry = $event"
            @update:card-cvc="cardCvc = $event"
            @update:card-name="cardName = $event"
            @pay-stripe="handleStripePay"
            @copy="copyToClipboard"
          />
        </div>

        <div class="checkout-right-col">
          <!-- Summary Card -->
          <PaymentSummaryCard
            :payment="payment"
            :booking="booking"
            :court="court"
            :countdown-formatted="countdownFormatted"
            :coupon-code="couponCode"
            :coupon-loading="couponLoading"
            :coupon-error="couponError"
            :coupon-success="couponSuccess"
            @update:coupon-code="couponCode = $event"
            @apply-coupon="applyCoupon"
          />
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="modal-backdrop">
      <div class="modal-card modal-sm text-center">
        <div class="modal-body success-body">
          <div class="success-icon">🎉</div>
          <h3>Thanh Toán Thành Công!</h3>
          <p>Mã đặt sân <strong>{{ payment?.code }}</strong> đã được xác nhận vào hệ thống.</p>
          <button class="btn-done" @click="store.clearPayment(); router.push('/booking')">
            Xem Lịch Của Tôi
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.payment-view-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px 60px;
  color: var(--ink);
}

.empty-payment-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
}

.empty-icon {
  font-size: 56px;
  margin-bottom: 16px;
}

.empty-payment-panel h2 {
  margin: 0 0 8px;
  font-size: 20px;
  font-weight: 700;
}

.empty-payment-panel p {
  color: var(--muted);
  margin-bottom: 24px;
}

.btn-choose-court {
  display: inline-flex;
  align-items: center;
  padding: 12px 24px;
  border-radius: 12px;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  text-decoration: none;
}

.checkout-stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 600px;
  margin: 0 auto 32px;
}

.stepper-step {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
}

.stepper-step.active {
  color: var(--pink-dark, #db2777);
  font-weight: 700;
}

.stepper-step.completed {
  color: #10b981;
}

.step-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.06));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
}

.stepper-step.active .step-circle {
  background: var(--pink-dark, #db2777);
  color: #fff;
}

.stepper-step.completed .step-circle {
  background: #10b981;
  color: #fff;
}

.step-text {
  font-size: 13px;
}

.stepper-line {
  flex: 1;
  height: 2px;
  background: var(--outline);
  margin: 0 12px;
}

.checkout-main-grid {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 24px;
  align-items: start;
}

.checkout-left-col {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.player-details-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.panel-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.player-info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 11px;
  color: var(--muted);
}

.info-val {
  font-size: 13px;
}

.success-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 30px 20px;
}

.success-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

.btn-done {
  margin-top: 20px;
  padding: 12px 24px;
  border-radius: 10px;
  border: 0;
  background: #10b981;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

@media (max-width: 900px) {
  .checkout-main-grid {
    grid-template-columns: 1fr;
  }
}
</style>
