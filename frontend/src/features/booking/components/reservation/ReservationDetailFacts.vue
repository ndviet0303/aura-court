<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Reservation } from '../../types/reservation'

// Report FR-79 detail facts: who uses the booking (D6), voucher, payment deadline and result, check-in and check-out,
// incident handling.
const props = defineProps<{ reservation: Reservation }>()
const { t, d } = useI18n()

const time = (value?: string | null) => (value ? d(new Date(value), 'datetime') : '')

const paymentText = computed(() => {
  const payment = props.reservation.payment
  if (!payment) return ''
  if (payment.status === 'paid') {
    return t('reservations.detail.paymentPaid', { time: time(payment.paid_at) })
  }
  // A late, short or excess transfer waits for the venue to reconcile it.
  if (payment.receipt && payment.receipt.status !== 'allocated') {
    return t('reservations.detail.paymentReview')
  }
  return t(
    `reservations.detail.${payment.status === 'pending' ? 'paymentPending' : 'paymentExpired'}`,
  )
})

// How the venue handled a venue or court change that hit this booking, with when it closed.
const incidentText = computed(() => {
  const incident = props.reservation.latest_affected_case
  if (!incident) return ''
  const status = t(`reservations.detail.incidentStatus.${incident.status}`)
  return incident.handled_at ? `${status} · ${time(incident.handled_at)}` : status
})

const facts = computed(() => {
  const r = props.reservation
  const holder = r.holder ? [r.holder.name, r.holder.phone, r.holder.email] : []
  const rows: [string, string][] = [
    ['holder', holder.filter(Boolean).join(' · ')],
    ['voucher', r.voucher?.code ?? r.voucher?.name ?? r.quote?.benefit_snapshot?.coupon_code ?? ''],
    ['deadline', r.status === 'held' ? time(r.hold_expires_at) : ''],
    ['payment', paymentText.value],
    ['checkIn', time(r.attendance?.checked_in_at)],
    ['checkOut', time(r.attendance?.checked_out_at)],
    ['incident', incidentText.value],
  ]
  return rows.filter(([, value]) => value)
})
</script>

<template>
  <dl v-if="facts.length" class="detail-facts" data-testid="reservation-detail-facts">
    <div v-for="[key, value] in facts" :key="key" class="fact-row" :data-testid="`detail-${key}`">
      <dt>{{ t(`reservations.detail.${key}`) }}</dt>
      <dd>{{ value }}</dd>
    </div>
  </dl>
</template>

<style scoped>
.detail-facts {
  margin: 0;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12.5px;
}

.fact-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.fact-row dt {
  color: var(--muted);
}

.fact-row dd {
  margin: 0;
  font-weight: 700;
  text-align: right;
}
</style>
