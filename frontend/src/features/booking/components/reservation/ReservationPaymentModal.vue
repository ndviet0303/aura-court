<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { bookingApi, type ReservationPaymentAttempt } from '../../api/bookingApi'
import type { Reservation } from '../../types/reservation'
import { displayCode } from '../../utils/reservation'
import { useDeadlineCountdown } from '../../composables/useDeadlineCountdown'
import CancelPaymentButton from './CancelPaymentButton.vue'
import ReservationPaymentInstructions from './ReservationPaymentInstructions.vue'

const props = defineProps<{
  show: boolean
  reservation: Reservation | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'paid'): void
  (e: 'cancelled', reservation: Reservation): void
}>()

const { t, d } = useI18n()
const loading = ref(false)
const refreshing = ref(false)
const error = ref('')
const reservationStatus = ref('held')
const releaseReason = ref<string | null>(null)
const paymentAttempt = ref<ReservationPaymentAttempt | null>(null)
let pollInterval: ReturnType<typeof setInterval> | undefined
const countdown = useDeadlineCountdown(() => props.reservation?.hold_expires_at)
// An expired attempt reads as expired even when the booking was confirmed by another payment.
const statusText = computed(() => {
  const status = paymentAttempt.value?.status
  const paid = status === 'paid' || reservationStatus.value === 'confirmed'
  // UC-4.6 A-3: a new price ended the request, which is told apart from a lapsed deadline.
  if (releaseReason.value === 'price_changed') return t('booking.statusPriceChanged')
  return t(`reservations.payment.${status === 'expired' ? 'expired' : paid ? 'paid' : 'waiting'}`)
})

function stopPolling() {
  if (pollInterval) clearInterval(pollInterval)
  pollInterval = undefined
}

function acceptStatus(response: {
  reservation: { status: string; release_reason?: string | null }
  payment_attempt: ReservationPaymentAttempt | null
}) {
  const wasPaid = paymentAttempt.value?.status === 'paid'
  reservationStatus.value = response.reservation.status
  releaseReason.value = response.reservation.release_reason ?? null
  paymentAttempt.value = response.payment_attempt
  if (!wasPaid && response.payment_attempt?.status === 'paid') emit('paid')
  if (response.payment_attempt?.status !== 'pending') stopPolling()
}

async function startPayment(code: string) {
  loading.value = true
  error.value = ''
  try {
    const response = await bookingApi.createReservationPayment(code)
    acceptStatus(response)
    if (response.payment_attempt.status === 'pending') {
      stopPolling()
      pollInterval = setInterval(() => refreshStatus(code), 5000)
    }
  } catch (cause) {
    error.value = errorMessage(cause, t('reservations.payment.startFailed'))
  } finally {
    loading.value = false
  }
}

async function refreshStatus(code = props.reservation?.code) {
  if (!code || refreshing.value) return
  refreshing.value = true
  error.value = ''
  try {
    const response = await bookingApi.getReservationPayment(code)
    acceptStatus(response)
  } catch (cause) {
    error.value = errorMessage(cause, t('reservations.payment.refreshFailed'))
  } finally {
    refreshing.value = false
  }
}

watch(
  () => (props.show ? (props.reservation?.code ?? null) : null),
  (code) => {
    stopPolling()
    paymentAttempt.value = null
    reservationStatus.value = 'held'
    releaseReason.value = null
    error.value = ''
    if (code) void startPayment(code)
  },
  { immediate: true },
)

onUnmounted(stopPolling)
</script>

<template>
  <div
    v-if="show && reservation"
    class="modal-backdrop"
    data-testid="reservation-payment-modal"
    @click.self="emit('close')"
  >
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      aria-labelledby="reservation-payment-title"
    >
      <header class="modal-header">
        <div>
          <h3 id="reservation-payment-title" class="modal-title">
            {{ t('reservations.payment.title') }}
          </h3>
          <p class="modal-subtitle font-mono">{{ displayCode(reservation) }}</p>
        </div>
        <button
          type="button"
          class="modal-close"
          :aria-label="t('reservations.payment.close')"
          @click="emit('close')"
        >
          ×
        </button>
      </header>

      <div class="modal-body">
        <p class="payment-intro">{{ t('reservations.payment.instructions') }}</p>

        <div v-if="loading" class="payment-state" data-testid="payment-loading">
          {{ t('reservations.payment.loading') }}
        </div>
        <div v-if="error" class="payment-error" role="alert" data-testid="payment-error">
          {{ error }}
        </div>

        <template v-if="paymentAttempt">
          <div
            class="payment-status"
            :class="`status-${paymentAttempt.status}`"
            data-testid="reservation-payment-status"
          >
            {{ statusText }}
          </div>

          <ReservationPaymentInstructions
            v-if="paymentAttempt.status === 'pending'"
            :attempt="paymentAttempt"
          />
          <p
            v-if="releaseReason === 'price_changed'"
            class="payment-expiry"
            data-testid="payment-price-changed"
          >
            {{ t('reservations.detail.priceChangedNote') }}
          </p>

          <p
            v-if="countdown && paymentAttempt.status === 'pending'"
            class="payment-expiry"
            data-testid="payment-countdown"
          >
            {{ t('reservations.payment.countdown', { time: countdown }) }}
          </p>
          <p
            v-if="paymentAttempt.expires_at && paymentAttempt.status === 'pending'"
            class="payment-expiry"
          >
            {{
              t('reservations.payment.expiresAt', {
                time: d(new Date(paymentAttempt.expires_at), 'datetime'),
              })
            }}
          </p>
        </template>
      </div>

      <footer class="modal-footer">
        <CancelPaymentButton
          v-if="reservationStatus === 'held'"
          :code="reservation.code"
          @cancelled="emit('cancelled', reservation)"
        />
        <button
          v-if="paymentAttempt?.status === 'expired' && reservationStatus === 'held'"
          type="button"
          class="btn btn-secondary"
          :disabled="loading"
          data-testid="retry-reservation-payment"
          @click="reservation && startPayment(reservation.code)"
        >
          {{ loading ? t('reservations.payment.loading') : t('reservations.payment.retry') }}
        </button>
        <button
          v-if="paymentAttempt?.status === 'pending'"
          type="button"
          class="btn btn-secondary"
          :disabled="refreshing"
          data-testid="refresh-payment-status"
          @click="refreshStatus()"
        >
          {{
            refreshing ? t('reservations.payment.refreshing') : t('reservations.payment.refresh')
          }}
        </button>
        <button
          type="button"
          class="btn btn-primary"
          data-testid="close-payment-modal"
          @click="emit('close')"
        >
          {{ t('reservations.payment.close') }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(0, 0, 0, 0.62);
  backdrop-filter: blur(4px);
}
.modal-card {
  width: 100%;
  max-width: 560px;
  overflow: hidden;
  border: 1px solid var(--outline);
  border-radius: 18px;
  background: var(--surface);
  color: var(--ink);
  box-shadow: 0 20px 48px rgba(0, 0, 0, 0.25);
}
.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}
.modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--border);
  border-bottom: 0;
}
.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}
.modal-subtitle {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 12px;
}
.modal-close {
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 25px;
  cursor: pointer;
}
.modal-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px;
}
.payment-intro,
.payment-expiry {
  margin: 0;
  color: var(--muted);
  font-size: 12px;
}
.payment-state,
.payment-status {
  padding: 10px 12px;
  border-radius: 9px;
  background: var(--surface-subtle);
  font-size: 13px;
  font-weight: 700;
}
.status-pending {
  color: #9a5a00;
  background: rgba(239, 108, 0, 0.12);
}
.status-paid {
  color: #20733c;
  background: rgba(46, 125, 50, 0.12);
}
.status-expired {
  color: var(--muted);
}
.payment-error {
  padding: 10px 12px;
  border-radius: 9px;
  color: #9d2828;
  background: rgba(198, 40, 40, 0.1);
  font-size: 12px;
}
.btn {
  padding: 8px 14px;
  border: 1px solid var(--outline);
  border-radius: 9px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.btn-primary {
  border-color: transparent;
  background: var(--primary, #6b2d3f);
  color: white;
}
.btn-secondary {
  background: var(--surface);
  color: var(--ink);
}
.btn:disabled {
  opacity: 0.55;
  cursor: wait;
}
</style>
