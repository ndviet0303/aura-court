<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ReservationPaymentAttempt } from '../api/bookingApi'

const { t } = useI18n()

defineProps<{
  attempt: ReservationPaymentAttempt | null
  reservationStatus: string
  loading: boolean
  refreshing: boolean
  error?: string
}>()

const emit = defineEmits<{
  (e: 'refresh'): void
  (e: 'retry'): void
}>()
</script>

<template>
  <section class="owner-payment-panel" data-testid="owner-payment-instructions">
    <div class="panel-header">
      <span class="material-symbols-outlined">account_balance</span>
      <div>
        <h3>{{ t('v2Reservations.payment.title') }}</h3>
        <p>{{ t('v2Reservations.payment.instructions') }}</p>
      </div>
    </div>

    <div v-if="loading" class="panel-state">{{ t('v2Reservations.payment.loading') }}</div>
    <div v-if="error" class="panel-error" role="alert">{{ error }}</div>
    <div v-if="reservationStatus === 'confirmed' || attempt?.status === 'paid'" class="panel-state status-paid" data-testid="owner-payment-paid">
      {{ t('v2Reservations.payment.paid') }}
    </div>

    <template v-else-if="attempt">
      <div class="panel-state" :class="'status-' + attempt.status" data-testid="owner-payment-status">
        {{ attempt.status === 'expired' ? t('v2Reservations.payment.expired') : t('v2Reservations.payment.waiting') }}
      </div>
      <div v-if="attempt.status === 'pending'" class="payment-layout">
        <img :src="attempt.qr_url" :alt="t('v2Reservations.payment.qrAlt')" referrerpolicy="no-referrer" data-testid="owner-payment-qr" />
        <dl>
          <div><dt>{{ t('v2Reservations.payment.receiver') }}</dt><dd>{{ attempt.receiver.bank_code }}</dd></div>
          <div><dt>{{ t('v2Reservations.payment.accountName') }}</dt><dd>{{ attempt.receiver.account_name }}</dd></div>
          <div><dt>{{ t('v2Reservations.payment.accountNumber') }}</dt><dd class="font-mono">{{ attempt.receiver.account_number }}</dd></div>
          <div><dt>{{ t('v2Reservations.payment.amount') }}</dt><dd class="font-mono">{{ new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(attempt.amount) }} {{ attempt.currency }}</dd></div>
          <div><dt>{{ t('v2Reservations.payment.transferContent') }}</dt><dd class="font-mono select-all">{{ attempt.transfer_content }}</dd></div>
        </dl>
      </div>
      <p v-if="attempt.expires_at && attempt.status === 'pending'" class="panel-expiry">
        {{ t('v2Reservations.payment.expiresAt', { time: new Date(attempt.expires_at).toLocaleString() }) }}
      </p>
    </template>

    <div v-if="attempt?.status === 'pending'" class="panel-actions">
      <button type="button" :disabled="refreshing" data-testid="owner-payment-refresh" @click="emit('refresh')">
        {{ refreshing ? t('v2Reservations.payment.refreshing') : t('v2Reservations.payment.refresh') }}
      </button>
    </div>
    <div v-else-if="attempt?.status === 'expired' && reservationStatus === 'held'" class="panel-actions">
      <button type="button" :disabled="loading" data-testid="owner-payment-retry" @click="emit('retry')">
        {{ t('v2Reservations.payment.retry') }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.owner-payment-panel { display: flex; flex-direction: column; gap: 14px; padding: 18px; border: 1px solid var(--outline); border-radius: 16px; background: var(--surface); color: var(--ink); }
.panel-header { display: flex; align-items: flex-start; gap: 10px; }
.panel-header > .material-symbols-outlined { color: var(--primary, #6b2d3f); }
.panel-header h3 { margin: 0; font-size: 15px; font-weight: 800; }
.panel-header p, .panel-expiry { margin: 4px 0 0; color: var(--muted); font-size: 11px; }
.panel-state { padding: 9px 11px; border-radius: 8px; background: var(--surface-subtle); font-size: 12px; font-weight: 700; }
.status-pending { color: #9a5a00; background: rgba(239,108,0,.12); }
.status-paid { color: #20733c; background: rgba(46,125,50,.12); }
.status-expired { color: var(--muted); }
.panel-error { padding: 9px 11px; border-radius: 8px; color: #9d2828; background: rgba(198,40,40,.1); font-size: 11px; }
.payment-layout { display: grid; grid-template-columns: 170px 1fr; align-items: center; gap: 15px; }
.payment-layout img { width: 170px; aspect-ratio: 1; padding: 6px; border: 1px solid var(--border); border-radius: 10px; background: white; }
.payment-layout dl { display: flex; flex-direction: column; gap: 8px; margin: 0; }
.payment-layout dl div { display: flex; flex-direction: column; gap: 2px; }
.payment-layout dt { color: var(--muted); font-size: 10px; }
.payment-layout dd { margin: 0; overflow-wrap: anywhere; font-size: 12px; font-weight: 700; }
.panel-actions { display: flex; justify-content: flex-end; }
.panel-actions button { padding: 8px 12px; border: 1px solid var(--outline); border-radius: 8px; background: transparent; color: var(--ink); font-size: 11px; font-weight: 700; cursor: pointer; }
.panel-actions button:disabled { opacity: .55; cursor: wait; }
@media (max-width: 540px) { .payment-layout { grid-template-columns: 1fr; justify-items: center; } .payment-layout dl { width: 100%; } }
</style>
