<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { isUnverifiedEmailError } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'
import { useBookingStore } from '@/stores/booking'
import type { Court, AvailabilitySlot } from '../../types/court'

const { t, locale } = useI18n()
const auth = useAuthStore()
const bookingStore = useBookingStore()

const props = defineProps<{
  court: Court
  date: string
  selectedSlots: AvailabilitySlot[]
  totalPrice: number
  loading?: boolean
  error?: string
  notice?: string
  resendingEmail?: boolean
}>()

const emit = defineEmits<{
  (e: 'checkout'): void
  (e: 'removeSlot', slot: AvailabilitySlot): void
  (e: 'resendVerification'): void
  (e: 'clearError'): void
}>()

const isUnverified = computed(() => isUnverifiedEmailError(props.error))

const formattedDate = computed(() => {
  try {
    const d = new Date(props.date)
    return d.toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
      weekday: 'long',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return props.date
  }
})

const durationHours = computed(() => {
  return props.selectedSlots.length
})
</script>

<template>
  <div class="schedule-summary-card">
    <!-- Header -->
    <div class="summary-hdr">
      <div class="title-row">
        <h3 class="title">
          <span class="material-symbols-outlined title-icon">receipt_long</span>
          <span>{{ $t('schedule.summaryTitle') }}</span>
        </h3>
        <span v-if="selectedSlots.length > 0" class="badge-count">
          {{ $t('schedule.slotsBadge', { count: selectedSlots.length }) }}
        </span>
      </div>

      <div class="court-meta">
        <strong class="court-name">{{ court.name }}</strong>
        <RouterLink
          :to="`/courts/${court.slug || court.id}/directions`"
          class="venue-info-link"
          :title="$t('courts.directionsBtn')"
        >
          <span class="material-symbols-outlined icon-14">location_on</span>
          <span>{{ court.venue?.name || 'Aura Court' }}</span>
          <span class="material-symbols-outlined icon-10">open_in_new</span>
        </RouterLink>
        <span class="date-info">
          <span class="material-symbols-outlined icon-14">event</span>
          <span>{{ formattedDate }}</span>
        </span>
      </div>
    </div>

    <!-- Selected Slots Section -->
    <div class="selected-slots-section">
      <div class="section-label-row">
        <span class="section-sub-label">{{ $t('schedule.selectedSlotsLabel') }}</span>
        <span v-if="durationHours > 0" class="duration-badge">
          {{ $t('schedule.totalPlayDuration', { count: durationHours }) }}
        </span>
      </div>

      <div v-if="selectedSlots.length === 0" class="no-slots-hint">
        <span class="material-symbols-outlined hint-icon">touch_app</span>
        <p class="hint-text">{{ $t('schedule.noSlotsSelectedHint') }}</p>
      </div>

      <div v-else class="slots-list-wrap">
        <div
          v-for="s in selectedSlots"
          :key="s.startsAt"
          class="slot-summary-item"
        >
          <div class="slot-time-badge">
            <span class="material-symbols-outlined icon-14">schedule</span>
            <span class="font-mono time-txt">
              {{ s.startsAt.slice(11, 16) }} - {{ s.endsAt.slice(11, 16) }}
            </span>
          </div>

          <div class="slot-price-wrap">
            <span class="font-mono item-price">{{ formatCurrency(s.price) }}</span>
            <button
              type="button"
              class="btn-remove-slot"
              :title="$t('schedule.removeSlot')"
              @click="emit('removeSlot', s)"
            >
              <span class="material-symbols-outlined icon-14">close</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Guarantees & Perks -->
    <div class="trust-perks-box">
      <div class="perk-item">
        <span class="material-symbols-outlined perk-icon">bolt</span>
        <span>{{ $t('schedule.instantHoldPerk') }}</span>
      </div>
      <div class="perk-item">
        <span class="material-symbols-outlined perk-icon">verified_user</span>
        <span>{{ $t('schedule.flexReschedulePerk') }}</span>
      </div>
    </div>

    <!-- Feedback Notice Alert -->
    <div v-if="notice" class="summary-notice-box" role="status">
      <span class="material-symbols-outlined notice-icon">check_circle</span>
      <p class="notice-msg">{{ notice }}</p>
    </div>

    <!-- Error Feedback Alert -->
    <div v-if="error" class="summary-error-box" role="alert">
      <div class="error-header">
        <div class="error-header-left">
          <span class="material-symbols-outlined error-icon">error</span>
          <strong class="error-title">{{ $t('schedule.cannotCreateBooking') }}</strong>
        </div>
        <button type="button" class="btn-dismiss-error" aria-label="Đóng" @click="emit('clearError')">
          <span class="material-symbols-outlined icon-14">close</span>
        </button>
      </div>
      <p class="error-msg">{{ error }}</p>
      <div v-if="error.toLowerCase().includes('ví') || error.toLowerCase().includes('wallet') || error.toLowerCase().includes('nạp')" class="unverified-action-row">
        <RouterLink to="/wallet" class="btn-resend-verify">
          <span class="material-symbols-outlined icon-14">add_card</span>
          <span>{{ $t('payment.topupNowCta') || 'Nạp tiền vào ví ngay' }}</span>
        </RouterLink>
      </div>
      <div v-else-if="isUnverified" class="unverified-action-row">
        <button
          type="button"
          class="btn-resend-verify"
          :disabled="resendingEmail"
          @click="emit('resendVerification')"
        >
          <span class="material-symbols-outlined icon-14">mail</span>
          <span>{{ resendingEmail ? $t('schedule.sendingEmail') : $t('schedule.resendVerificationEmail') }}</span>
        </button>
        <RouterLink to="/profile" class="btn-goto-profile">
          <span class="material-symbols-outlined icon-14">person</span>
          <span>{{ $t('schedule.goToProfile') }}</span>
        </RouterLink>
      </div>
    </div>

    <!-- Summary Total & Checkout -->
    <div class="summary-footer">
      <div class="price-breakdown">
        <div class="breakdown-row">
          <span>{{ $t('schedule.playDuration') }}</span>
          <strong>{{ $t('schedule.hours', { count: durationHours }) }}</strong>
        </div>
        <div v-if="auth.user" class="breakdown-row wallet-status-row">
          <div class="wallet-status-left">
            <span class="material-symbols-outlined icon-14">account_balance_wallet</span>
            <span>{{ $t('payment.currentBalance') || 'Số dư ví' }}:</span>
          </div>
          <div class="wallet-status-right">
            <strong class="font-mono" :class="bookingStore.walletBalance >= totalPrice ? 'text-emerald' : 'text-danger'">
              {{ formatCurrency(bookingStore.walletBalance) }}
            </strong>
            <RouterLink
              v-if="bookingStore.walletBalance < totalPrice && selectedSlots.length > 0"
              to="/wallet"
              class="badge-topup-link"
              :title="$t('payment.topupNowCta') || 'Nạp ví ngay'"
            >
              + Nạp ví
            </RouterLink>
          </div>
        </div>
        <div class="breakdown-row total-row">
          <span>{{ $t('schedule.totalPayment') }}</span>
          <strong class="total-num font-mono">{{ formatCurrency(totalPrice) }}</strong>
        </div>
      </div>

      <button
        type="button"
        class="btn-checkout"
        :disabled="selectedSlots.length === 0 || loading"
        @click="emit('checkout')"
      >
        <span v-if="loading" class="material-symbols-outlined spinner-icon">progress_activity</span>
        <span v-else class="material-symbols-outlined btn-icon">payments</span>
        <span>{{ loading ? $t('schedule.initializingBooking') : $t('schedule.confirmAndPayBtn') }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.schedule-summary-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 22px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .schedule-summary-card {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.summary-hdr {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
}

:global(html.dark) .summary-hdr {
  border-color: #2e242a;
}

.title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.title-icon {
  font-size: 20px;
  color: var(--primary);
}

:global(html.dark) .title-icon {
  color: #e58e9f;
}

.badge-count {
  font-size: 11px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--primary-container);
  color: var(--primary);
}

:global(html.dark) .badge-count {
  background: rgba(229, 142, 159, 0.15);
  color: #e58e9f;
  border: 1px solid rgba(229, 142, 159, 0.25);
}

.court-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.court-name {
  font-size: 14.5px;
  color: var(--primary);
  font-weight: 800;
}

:global(html.dark) .court-name {
  color: #f7a8b8;
}

.venue-info,
.venue-info-link,
.date-info {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--muted);
  text-decoration: none;
  width: fit-content;
  transition: color 0.15s ease;
}

.venue-info-link:hover {
  color: var(--primary);
  text-decoration: underline;
}

:global(html.dark) .venue-info-link:hover {
  color: #e58e9f;
}

.icon-10 {
  font-size: 10px;
  opacity: 0.7;
}

.icon-14 {
  font-size: 14px;
  color: var(--muted);
}

.selected-slots-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-sub-label {
  font-size: 11.5px;
  font-weight: 800;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.duration-badge {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--accent);
}

:global(html.dark) .duration-badge {
  color: #d8b46e;
}

.no-slots-hint {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 20px 14px;
  border-radius: 14px;
  border: 1px dashed var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  text-align: center;
}

:global(html.dark) .no-slots-hint {
  background: rgba(255, 255, 255, 0.02);
  border-color: #382d33;
}

.hint-icon {
  font-size: 24px;
  color: var(--muted);
  opacity: 0.7;
}

.hint-text {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.4;
}

.slots-list-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 200px;
  overflow-y: auto;
  scrollbar-width: thin;
}

.slot-summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
}

:global(html.dark) .slot-summary-item {
  background: #231c20;
  border-color: #382d33;
}

.slot-time-badge {
  display: flex;
  align-items: center;
  gap: 6px;
}

.time-txt {
  font-size: 12.5px;
  font-weight: 800;
  color: var(--ink);
}

.slot-price-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.item-price {
  font-size: 12px;
  font-weight: 800;
  color: var(--ink);
}

.btn-remove-slot {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-remove-slot:hover {
  background: var(--error-bg, #ffebee);
  color: var(--error-text, #c62828);
}

:global(html.dark) .btn-remove-slot:hover {
  background: rgba(248, 113, 113, 0.2);
  color: #f87171;
}

.trust-perks-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

:global(html.dark) .trust-perks-box {
  background: #151113;
  border-color: #2b2227;
}

.perk-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--muted);
}

.perk-icon {
  font-size: 14px;
  color: var(--accent);
}

:global(html.dark) .perk-icon {
  color: #d8b46e;
}

.summary-footer {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
}

:global(html.dark) .summary-footer {
  border-color: #2e242a;
}

.price-breakdown {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
  color: var(--muted);
}

.wallet-status-row {
  padding: 6px 10px;
  border-radius: 10px;
  background: rgba(219, 39, 119, 0.05);
  border: 1px dashed rgba(219, 39, 119, 0.2);
}

:global(html.dark) .wallet-status-row {
  background: rgba(236, 72, 153, 0.08);
  border-color: rgba(236, 72, 153, 0.25);
}

.wallet-status-left {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--ink);
}

.wallet-status-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.text-emerald {
  color: #10b981 !important;
}

.text-danger {
  color: #ef4444 !important;
}

.badge-topup-link {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 6px;
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

.badge-topup-link:hover {
  opacity: 0.92;
  transform: translateY(-1px);
}

.total-row {
  padding-top: 4px;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
}

.total-num {
  font-size: 20px;
  font-weight: 800;
  color: var(--primary);
}

:global(html.dark) .total-num {
  color: #f7a8b8;
}

.btn-checkout {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px;
  border-radius: 14px;
  border: 0;
  background: linear-gradient(145deg, var(--primary) 0%, var(--primary-hover, #562332) 100%);
  color: var(--on-primary);
  font-weight: 800;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 4px 18px rgba(107, 45, 63, 0.25);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-checkout:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 22px rgba(107, 45, 63, 0.35);
}

.btn-checkout:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

:global(html.dark) .btn-checkout {
  background: linear-gradient(145deg, #e58e9f 0%, #c46d80 100%);
  color: #1c0a10;
  box-shadow: 0 4px 20px rgba(229, 142, 159, 0.35);
}

:global(html.dark) .btn-checkout:hover:not(:disabled) {
  background: linear-gradient(145deg, #f0a3b2 0%, #d87d90 100%);
  box-shadow: 0 6px 26px rgba(229, 142, 159, 0.5);
}

.spinner-icon {
  font-size: 18px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Feedback Notice & Error Boxes */
.summary-notice-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(34, 197, 94, 0.12);
  border: 1px solid rgba(34, 197, 94, 0.28);
  color: #15803d;
}

:global(html.dark) .summary-notice-box {
  background: rgba(34, 197, 94, 0.18);
  border-color: rgba(34, 197, 94, 0.35);
  color: #4ade80;
}

.notice-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.notice-msg {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.4;
}

.summary-error-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border-radius: 14px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #b91c1c;
  animation: shakeIn 0.3s ease-out;
}

:global(html.dark) .summary-error-box {
  background: rgba(239, 68, 68, 0.18);
  border-color: rgba(239, 68, 68, 0.4);
  color: #fca5a5;
}

@keyframes shakeIn {
  0% { transform: translateY(-4px); opacity: 0; }
  100% { transform: translateY(0); opacity: 1; }
}

.error-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.error-header-left {
  display: flex;
  align-items: center;
  gap: 6px;
}

.error-icon {
  font-size: 18px;
  color: #ef4444;
}

.error-title {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.btn-dismiss-error {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2px;
  background: transparent;
  border: 0;
  color: inherit;
  opacity: 0.7;
  cursor: pointer;
  border-radius: 6px;
  transition: opacity 0.15s;
}

.btn-dismiss-error:hover {
  opacity: 1;
  background: rgba(0, 0, 0, 0.05);
}

.error-msg {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.45;
  color: inherit;
  opacity: 0.95;
}

.unverified-action-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
}

.btn-resend-verify,
.btn-goto-profile {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-resend-verify {
  border: 1px solid #ef4444;
  background: #ef4444;
  color: #ffffff;
}

.btn-resend-verify:hover:not(:disabled) {
  background: #dc2626;
  border-color: #dc2626;
}

.btn-resend-verify:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-goto-profile {
  border: 1px solid rgba(239, 68, 68, 0.35);
  background: rgba(239, 68, 68, 0.08);
  color: inherit;
}

.btn-goto-profile:hover {
  background: rgba(239, 68, 68, 0.18);
}
</style>
