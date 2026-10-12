<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useV2Reservations } from '../composables/useV2Reservations'
import type { V2Reservation } from '../types/v2Reservation'

const { t } = useI18n()

import V2ReservationHero from '../components/v2/V2ReservationHero.vue'
import V2QuoteCalculatorCard from '../components/v2/V2QuoteCalculatorCard.vue'
import V2HeldReservationBanner from '../components/v2/V2HeldReservationBanner.vue'
import V2ReservationTabs from '../components/v2/V2ReservationTabs.vue'
import V2ReservationCard from '../components/v2/V2ReservationCard.vue'
import V2ReservationDetailModal from '../components/v2/V2ReservationDetailModal.vue'
import V2ReservationQrModal from '../components/v2/V2ReservationQrModal.vue'
import V2ReservationPaymentModal from '../components/v2/V2ReservationPaymentModal.vue'
import BookingCancelModal from '../components/history/BookingCancelModal.vue'
import BookingRescheduleModal from '../components/history/BookingRescheduleModal.vue'

const {
  courts,
  selectedCourtId,
  selectedDate,
  selectedTimeSlot,
  activeQuote,
  formattedQuoteRemaining,
  loading,
  calculatingQuote,
  holdingReservation,
  confirmingCode,
  activeTab,
  searchQuery,
  notice,
  error,
  selectedReservationForDetails,
  selectedReservationForQr,
  selectedReservationForPayment,
  filteredReservations,
  totalCount,
  heldCount,
  confirmedCount,
  cancelledCount,
  activeHeldReservation,
  money,
  formatDate,
  formatTimeSlot,
  loadData,
  calculateQuote,
  holdReservation,
  checkoutReservation,
  confirmReservation,
  openDetails,
  closeDetails,
  openQr,
  closeQr,
  openPayment,
  closePayment,
} = useV2Reservations()

function scrollToCalculator() {
  const el = document.getElementById('quote-calculator-section')
  if (el) el.scrollIntoView({ behavior: 'smooth' })
}

const cancelReservation = ref<V2Reservation | null>(null)
const rescheduleReservation = ref<V2Reservation | null>(null)

function openCancel(reservation: V2Reservation) {
  closeDetails()
  cancelReservation.value = reservation
}
function openReschedule(reservation: V2Reservation) {
  closeDetails()
  rescheduleReservation.value = reservation
}
function onChangeApplied() {
  cancelReservation.value = null
  rescheduleReservation.value = null
  loadData()
}
</script>

<template>
  <div class="v2-reservations-page">
    <!-- Hero Header -->
    <V2ReservationHero
      :total-count="totalCount"
      :held-count="heldCount"
      :confirmed-count="confirmedCount"
      :cancelled-count="cancelledCount"
      :loading="loading"
      @refresh="loadData"
      @create-quote="scrollToCalculator"
    />

    <!-- Feedback Notice / Error Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success">
        <span class="material-symbols-outlined alert-icon">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error">
        <span class="material-symbols-outlined alert-icon">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Active Held Reservation Alert Banner (if any) -->
    <V2HeldReservationBanner
      v-if="activeHeldReservation"
      :reservation="activeHeldReservation"
      :confirming-code="confirmingCode"
      :money="money"
      :format-date="formatDate"
      :format-time-slot="formatTimeSlot"
      @confirm="confirmReservation"
      @pay="openPayment"
      @details="openDetails"
    />

    <!-- Dynamic Quote Calculator Card -->
    <V2QuoteCalculatorCard
      :courts="courts"
      :selected-court-id="selectedCourtId"
      :selected-date="selectedDate"
      :selected-time-slot="selectedTimeSlot"
      :active-quote="activeQuote"
      :formatted-remaining="formattedQuoteRemaining"
      :calculating="calculatingQuote"
      :holding="holdingReservation"
      :money="money"
      :format-date="formatDate"
      :format-time-slot="formatTimeSlot"
      @update:selected-court-id="selectedCourtId = $event"
      @update:selected-date="selectedDate = $event"
      @update:selected-time-slot="selectedTimeSlot = $event"
      @calculate="calculateQuote"
      @hold="holdReservation"
      @checkout="checkoutReservation"
    />

    <!-- Reservations List Section -->
    <section class="reservations-list-section">
      <div class="section-title-row">
        <div>
          <h2 class="section-title">{{ t('v2Reservations.view.myReservationsTitle') }}</h2>
          <p class="section-subtitle">{{ t('v2Reservations.view.myReservationsSubtitle') }}</p>
        </div>
      </div>

      <!-- Filter Tabs & Search -->
      <V2ReservationTabs
        :active-tab="activeTab"
        :search-query="searchQuery"
        :total-count="totalCount"
        :held-count="heldCount"
        :confirmed-count="confirmedCount"
        :cancelled-count="cancelledCount"
        @update:active-tab="activeTab = $event"
        @update:search-query="searchQuery = $event"
      />

      <!-- Loading Skeletons -->
      <div v-if="loading" class="skeletons-container">
        <div v-for="i in 3" :key="i" class="skeleton-card">
          <div class="skeleton-line title"></div>
          <div class="skeleton-box"></div>
          <div class="skeleton-line footer"></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredReservations.length === 0" class="empty-state-card">
        <span class="material-symbols-outlined empty-icon">event_busy</span>
        <h3 class="empty-title">{{ t('v2Reservations.view.emptyTitle') }}</h3>
        <p class="empty-desc">
          {{
            searchQuery
              ? t('v2Reservations.view.emptySearchDesc')
              : t('v2Reservations.view.emptyDesc')
          }}
        </p>
        <button class="btn btn-primary" @click="scrollToCalculator">
          <span class="material-symbols-outlined">calculate</span>
          <span>{{ t('v2Reservations.view.createQuoteNow') }}</span>
        </button>
      </div>

      <!-- Reservations Grid -->
      <div v-else class="reservations-grid">
        <V2ReservationCard
          v-for="r in filteredReservations"
          :key="r.id"
          :reservation="r"
          :confirming-code="confirmingCode"
          :money="money"
          :format-date="formatDate"
          :format-time-slot="formatTimeSlot"
          @confirm="confirmReservation"
          @pay="openPayment"
          @qr="openQr"
          @details="openDetails"
        />
      </div>
    </section>

    <!-- Details Modal -->
    <V2ReservationDetailModal
      :show="!!selectedReservationForDetails"
      :reservation="selectedReservationForDetails"
      :money="money"
      :format-date="formatDate"
      :format-time-slot="formatTimeSlot"
      @close="closeDetails"
      @cancel="openCancel"
      @reschedule="openReschedule"
    />

    <!-- QR Pass Modal -->
    <V2ReservationQrModal
      :show="!!selectedReservationForQr"
      :reservation="selectedReservationForQr"
      :format-date="formatDate"
      :format-time-slot="formatTimeSlot"
      @close="closeQr"
    />

    <V2ReservationPaymentModal
      :show="!!selectedReservationForPayment"
      :reservation="selectedReservationForPayment"
      @close="closePayment"
      @paid="loadData"
    />

    <!-- Cancellation / partial-cancel refund modal -->
    <BookingCancelModal
      :show="!!cancelReservation"
      :booking="cancelReservation"
      @close="cancelReservation = null"
      @updated="onChangeApplied"
    />

    <!-- Reschedule modal -->
    <BookingRescheduleModal
      :show="!!rescheduleReservation"
      :booking="rescheduleReservation"
      @close="rescheduleReservation = null"
      @updated="onChangeApplied"
    />
  </div>
</template>

<style scoped>
.v2-reservations-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1100px;
  margin: 0 auto;
  padding: 28px 20px 60px;
  color: var(--ink);
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 12px;
  background: var(--success-bg, rgba(46, 125, 50, 0.12));
  color: var(--success-text, #2e7d32);
  font-size: 13.5px;
  font-weight: 600;
  border: 1px solid rgba(46, 125, 50, 0.2);
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 12px;
  background: var(--error-bg, rgba(198, 40, 40, 0.12));
  color: var(--error-text, #c62828);
  font-size: 13.5px;
  font-weight: 600;
  border: 1px solid rgba(198, 40, 40, 0.2);
}

.alert-icon {
  font-size: 20px;
}

.reservations-list-section {
  display: flex;
  flex-direction: column;
  gap: 18px;
  margin-top: 10px;
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.section-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 800;
  color: var(--ink);
}

.section-subtitle {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.reservations-grid {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.skeletons-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.skeleton-card {
  padding: 24px;
  border-radius: 18px;
  border: 1px solid var(--border);
  background: var(--surface);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.skeleton-line {
  height: 20px;
  border-radius: 6px;
  background: linear-gradient(
    90deg,
    var(--surface-subtle) 25%,
    var(--border) 50%,
    var(--surface-subtle) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.skeleton-line.title {
  width: 35%;
}

.skeleton-line.footer {
  width: 50%;
}

.skeleton-box {
  height: 50px;
  border-radius: 10px;
  background: var(--surface-subtle);
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  text-align: center;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.02);
}

.empty-icon {
  font-size: 44px;
  color: var(--muted);
  margin-bottom: 12px;
}

.empty-title {
  margin: 0 0 8px;
  font-size: 17px;
  font-weight: 800;
}

.empty-desc {
  margin: 0 0 20px;
  font-size: 13.5px;
  color: var(--muted);
  max-width: 460px;
  line-height: 1.5;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
