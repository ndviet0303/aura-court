<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ReservationPaymentAttempt } from '../../api/bookingApi'

// The transfer QR and the receiving account of a pending payment request (UC-4.6 step 4).
defineProps<{ attempt: ReservationPaymentAttempt }>()

const { t } = useI18n()
const amountFormat = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 })
</script>

<template>
  <div class="payment-grid">
    <img
      :src="attempt.qr_url"
      :alt="t('reservations.payment.qrAlt')"
      class="payment-qr"
      referrerpolicy="no-referrer"
      data-testid="reservation-payment-qr"
    />
    <dl class="payment-details">
      <div>
        <dt>{{ t('reservations.payment.receiver') }}</dt>
        <dd>{{ attempt.receiver.bank_code }}</dd>
      </div>
      <div>
        <dt>{{ t('reservations.payment.accountName') }}</dt>
        <dd>{{ attempt.receiver.account_name }}</dd>
      </div>
      <div>
        <dt>{{ t('reservations.payment.accountNumber') }}</dt>
        <dd class="font-mono">{{ attempt.receiver.account_number }}</dd>
      </div>
      <div>
        <dt>{{ t('reservations.payment.amount') }}</dt>
        <dd class="font-mono">{{ amountFormat.format(attempt.amount) }} {{ attempt.currency }}</dd>
      </div>
      <div>
        <dt>{{ t('reservations.payment.transferContent') }}</dt>
        <dd class="font-mono select-all">{{ attempt.transfer_content }}</dd>
      </div>
    </dl>
  </div>
</template>

<style scoped>
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
