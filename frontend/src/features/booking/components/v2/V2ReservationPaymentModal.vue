<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { bookingApi, type ReservationPaymentAttempt } from '../../api/bookingApi'
import type { V2Reservation } from '../../types/v2Reservation'
import { displayCode } from '../../utils/reservation'

const props = defineProps<{
  show: boolean
  reservation: V2Reservation | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'paid'): void
}>()

const { t } = useI18n()
const loading = ref(false)
const refreshing = ref(false)
const error = ref('')
const reservationStatus = ref('held')
const paymentAttempt = ref<ReservationPaymentAttempt | null>(null)
let pollInterval: ReturnType<typeof setInterval> | undefined

function stopPolling() {
  if (pollInterval) clearInterval(pollInterval)
  pollInterval = undefined
}

function acceptStatus(response: {
  reservation: { status: string }
  payment_attempt: ReservationPaymentAttempt | null
}) {
  const wasPaid = paymentAttempt.value?.status === 'paid'
  reservationStatus.value = response.reservation.status
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
    error.value = errorMessage(cause, t('v2Reservations.payment.startFailed'))
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
    error.value = errorMessage(cause, t('v2Reservations.payment.refreshFailed'))
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
            {{ t('v2Reservations.payment.title') }}
          </h3>
          <p class="modal-subtitle font-mono">{{ displayCode(reservation) }}</p>
        </div>
        <button
          type="button"
          class="modal-close"
          :aria-label="t('v2Reservations.payment.close')"
          @click="emit('close')"
        >
          ×
        </button>
      </header>

      <div class="modal-body">
        <p class="payment-intro">{{ t('v2Reservations.payment.instructions') }}</p>

        <div v-if="loading" class="payment-state" data-testid="payment-loading">
          {{ t('v2Reservations.payment.loading') }}
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
            {{
              paymentAttempt.status === 'paid'
                ? t('v2Reservations.payment.paid')
                : paymentAttempt.status === 'expired'
                  ? t('v2Reservations.payment.expired')
                  : reservationStatus === 'confirmed'
                    ? t('v2Reservations.payment.paid')
                    : t('v2Reservations.payment.waiting')
            }}
          </div>

          <div v-if="paymentAttempt.status === 'pending'" class="payment-grid">
            <img
              :src="paymentAttempt.qr_url"
              :alt="t('v2Reservations.payment.qrAlt')"
              class="payment-qr"
              referrerpolicy="no-referrer"
              data-testid="reservation-payment-qr"
            />
            <dl class="payment-details">
              <div>
                <dt>{{ t('v2Reservations.payment.receiver') }}</dt>
                <dd>{{ paymentAttempt.receiver.bank_code }}</dd>
              </div>
              <div>
                <dt>{{ t('v2Reservations.payment.accountName') }}</dt>
                <dd>{{ paymentAttempt.receiver.account_name }}</dd>
              </div>
              <div>
                <dt>{{ t('v2Reservations.payment.accountNumber') }}</dt>
                <dd class="font-mono">{{ paymentAttempt.receiver.account_number }}</dd>
              </div>
              <div>
                <dt>{{ t('v2Reservations.payment.amount') }}</dt>
                <dd class="font-mono">
                  {{
                    new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(
                      paymentAttempt.amount,
                    )
                  }}
                  {{ paymentAttempt.currency }}
                </dd>
              </div>
              <div>
                <dt>{{ t('v2Reservations.payment.transferContent') }}</dt>
                <dd class="font-mono select-all">{{ paymentAttempt.transfer_content }}</dd>
              </div>
            </dl>
          </div>

          <p
            v-if="paymentAttempt.expires_at && paymentAttempt.status === 'pending'"
            class="payment-expiry"
          >
            {{
              t('v2Reservations.payment.expiresAt', {
                time: new Date(paymentAttempt.expires_at).toLocaleString(),
              })
            }}
          </p>
        </template>
      </div>

      <footer class="modal-footer">
        <button
          v-if="paymentAttempt?.status === 'expired' && reservationStatus === 'held'"
          type="button"
          class="btn btn-secondary"
          :disabled="loading"
          data-testid="retry-reservation-payment"
          @click="reservation && startPayment(reservation.code)"
        >
          {{ loading ? t('v2Reservations.payment.loading') : t('v2Reservations.payment.retry') }}
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
            refreshing
              ? t('v2Reservations.payment.refreshing')
              : t('v2Reservations.payment.refresh')
          }}
        </button>
        <button
          type="button"
          class="btn btn-primary"
          data-testid="close-payment-modal"
          @click="emit('close')"
        >
          {{ t('v2Reservations.payment.close') }}
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
.payment-grid {
  display: grid;
  grid-template-columns: 190px 1fr;
  align-items: center;
  gap: 18px;
}
.payment-qr {
  width: 190px;
  aspect-ratio: 1;
  padding: 7px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: white;
}
.payment-details {
  display: flex;
  flex-direction: column;
  gap: 9px;
  margin: 0;
}
.payment-details div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.payment-details dt {
  color: var(--muted);
  font-size: 10px;
}
.payment-details dd {
  margin: 0;
  overflow-wrap: anywhere;
  font-size: 12px;
  font-weight: 700;
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
@media (max-width: 520px) {
  .payment-grid {
    grid-template-columns: 1fr;
    justify-items: center;
  }
  .payment-details {
    width: 100%;
  }
}
</style>
