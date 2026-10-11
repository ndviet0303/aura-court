<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { AuraButton, SkeletonLoader } from '@/shared/components'
import { usePaymentProcessing } from '../composables/usePaymentProcessing'

import WalletPayButton from '../components/WalletPayButton.vue'
import PaymentSummaryCard from '../components/PaymentSummaryCard.vue'
import OwnerPaymentInstructionsPanel from '../components/OwnerPaymentInstructionsPanel.vue'

const router = useRouter()
const store = useBookingStore()

const {
  loading,
  isPaying,
  isTournament,
  tournament,
  payment,
  booking,
  court,
  playerDetails,
  walletBalance,
  paymentAmount,
  directPaymentAttempt,
  directPaymentError,
  directPaymentLoading,
  directPaymentRefreshing,
  isDirectOwnerPayment,
  status,
  error,
  showSuccessModal,
  couponCode,
  couponLoading,
  couponError,
  couponSuccess,
  applyCoupon,
  handleWalletPay,
  refreshDirectPaymentStatus,
  createDirectPaymentAttempt,
  startTimers,
  stopTimers,
} = usePaymentProcessing()

onMounted(() => {
  startTimers()
})

onUnmounted(() => {
  stopTimers()
})

function handleDoneTournament() {
  const targetId = tournament.value?.uuid || tournament.value?.id
  store.clearPayment()
  if (targetId) {
    router.push(`/tournaments/${targetId}/bracket`)
  } else {
    router.push('/tournaments')
  }
}

function handleViewSchedule() {
  store.clearPayment()
  router.push('/booking')
}

function retryDirectPayment() {
  const code = booking.value?.code
  if (code) void createDirectPaymentAttempt(code)
}
</script>

<template>
  <div class="payment-view-page">
    <!-- Loading State -->
    <div v-if="loading" class="payment-loading-wrap">
      <SkeletonLoader type="rect" width="100%" height="40px" rounded="12px" class="mb-4" />
      <div class="checkout-main-grid">
        <div class="checkout-left-col">
          <SkeletonLoader type="rect" width="100%" height="160px" rounded="16px" />
          <SkeletonLoader type="rect" width="100%" height="320px" rounded="16px" />
        </div>
        <div class="checkout-right-col">
          <SkeletonLoader type="rect" width="100%" height="380px" rounded="16px" />
        </div>
      </div>
    </div>

    <!-- Empty State if No Payment Session -->
    <div v-else-if="!payment && !booking" class="empty-payment-panel">
      <span class="material-symbols-outlined empty-icon">account_balance_wallet</span>
      <h2>{{ $t('payment.noSessionTitle') || 'Không có phiên thanh toán nào' }}</h2>
      <p>{{ $t('payment.noSessionDesc') || 'Vui lòng chọn sân và khung giờ để bắt đầu đặt lịch.' }}</p>
      <div class="empty-actions">
        <AuraButton to="/courts" variant="primary" size="md" icon="sports_tennis">
          {{ $t('payment.selectCourtCta') }}
        </AuraButton>
        <AuraButton to="/tournaments" variant="outline" size="md" icon="emoji_events">
          {{ $t('payment.viewTournamentsCta') }}
        </AuraButton>
      </div>
    </div>

    <!-- Active Payment Session -->
    <div v-else class="payment-checkout-container">
      <!-- Stepper Header -->
      <div class="checkout-stepper">
        <div class="stepper-step completed">
          <span class="step-circle"><span class="material-symbols-outlined step-check-icon">check</span></span>
          <span class="step-text">{{ isTournament ? $t('payment.stepRegisterAthlete') : $t('payment.stepSelectCourt') }}</span>
        </div>
        <div class="stepper-line"></div>
        <div class="stepper-step active">
          <span class="step-circle">2</span>
          <span class="step-text">{{ isTournament ? $t('payment.stepTournamentFee') : $t('payment.stepPayment') }}</span>
        </div>
        <div class="stepper-line"></div>
        <div class="stepper-step" :class="{ completed: showSuccessModal }">
          <span class="step-circle">{{ showSuccessModal ? '✓' : '3' }}</span>
          <span class="step-text">{{ isTournament ? $t('payment.stepConfirmTournament') : $t('payment.stepConfirmBooking') }}</span>
        </div>
      </div>

      <!-- Main Columns: Left (Player Info & Methods), Right (Summary) -->
      <div class="checkout-main-grid">
        <div class="checkout-left-col">
          <!-- Player / Athlete Details Panel -->
          <div class="player-details-panel">
            <h3 class="panel-title">
              <span class="material-symbols-outlined panel-icon">
                {{ isTournament ? 'sports_handball' : 'person' }}
              </span>
              <span>{{ isTournament ? $t('payment.athleteInfo') : $t('payment.customerInfo') }}</span>
            </h3>
            <div class="player-info-grid">
              <div class="info-item">
                <span class="info-label">{{ isTournament ? $t('payment.athleteFullName') : $t('payment.fullName') }}</span>
                <strong class="info-val">{{ playerDetails.name }}</strong>
              </div>
              <div class="info-item">
                <span class="info-label">{{ $t('payment.phone') }}</span>
                <strong class="info-val">{{ playerDetails.phone }}</strong>
              </div>
              <div v-if="isTournament && playerDetails.partnerName" class="info-item">
                <span class="info-label">{{ $t('payment.partner') }}</span>
                <strong class="info-val">{{ playerDetails.partnerName }}</strong>
              </div>
              <div v-else class="info-item">
                <span class="info-label">{{ $t('payment.email') }}</span>
                <strong class="info-val">{{ playerDetails.email }}</strong>
              </div>
            </div>
          </div>

          <!-- Error Feedback -->
          <div v-if="error" class="alert-box-error">
            <span class="material-symbols-outlined">error</span>
            <span>{{ error }}</span>
          </div>

          <!-- Wallet Pay Button / Component -->
          <OwnerPaymentInstructionsPanel
            v-if="isDirectOwnerPayment"
            :attempt="directPaymentAttempt"
            :reservation-status="booking?.status || status"
            :loading="directPaymentLoading"
            :refreshing="directPaymentRefreshing"
            :error="directPaymentError"
            @refresh="refreshDirectPaymentStatus"
            @retry="retryDirectPayment"
          />
          <WalletPayButton
            v-else
            :amount="paymentAmount"
            :wallet-balance="walletBalance"
            :loading="loading"
            :processing="isPaying"
            @pay="handleWalletPay"
          />
        </div>

        <div class="checkout-right-col">
          <!-- Summary Card -->
          <PaymentSummaryCard
            v-if="payment || booking"
            :payment="payment || { code: booking?.code || '', amount: booking?.total_amount || 0, type: 'booking', bank: '', accountNumber: '', qrUrl: '', expiresAt: '' }"
            :booking="booking"
            :court="court"
            :direct-owner-payment="isDirectOwnerPayment"
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
          <span class="material-symbols-outlined success-icon">
            {{ isTournament ? 'emoji_events' : 'verified' }}
          </span>
          <h3>{{ isTournament ? $t('payment.tournamentSuccessTitle') : $t('payment.successTitle') }}</h3>
          <p>
            {{ $t('payment.txCodeConfirmed', { code: booking?.code || payment?.code }) }}
          </p>

          <div class="success-actions">
            <template v-if="isTournament">
              <AuraButton variant="primary" size="md" icon="account_tree" @click="handleDoneTournament">
                {{ $t('payment.viewBracketCta') }}
              </AuraButton>
              <AuraButton to="/tournaments" variant="ghost" size="sm" @click="store.clearPayment()">
                {{ $t('payment.viewTournamentsCta') }}
              </AuraButton>
            </template>
            <template v-else>
              <AuraButton variant="primary" size="md" icon="calendar_month" @click="handleViewSchedule">
                {{ $t('payment.viewScheduleCta') }}
              </AuraButton>
            </template>
          </div>
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

.payment-loading-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.mb-4 {
  margin-bottom: 16px;
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
  color: var(--pink-dark, #db2777);
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

.empty-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: center;
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

.step-check-icon {
  font-size: 16px;
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
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.panel-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.player-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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

.alert-box-error {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.25);
  color: #ef4444;
  font-size: 13px;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 440px;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
}

.success-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 32px 24px;
}

.success-icon {
  font-size: 56px;
  color: #10b981;
  margin-bottom: 12px;
}

.success-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  margin-top: 20px;
}

@media (max-width: 900px) {
  .checkout-main-grid {
    grid-template-columns: 1fr;
  }
}
</style>
