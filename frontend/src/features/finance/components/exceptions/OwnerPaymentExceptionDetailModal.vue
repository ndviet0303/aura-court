<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { statusLabel } from '@/features/booking/utils/reservation'
import type { OwnerPaymentExceptionDetail } from '../../types/finance'

// UC-4.12 steps 4-5: the transfer and, when its code named one, the booking with what the owner needs to call the
// booker. Read-only: no refund or re-confirmation here (A-3).
const props = defineProps<{
  detail: OwnerPaymentExceptionDetail | null
  loading: boolean
  error: string | null
}>()
const emit = defineEmits<{ (e: 'close'): void }>()

const { t, te, d } = useI18n()

const time = (value: string | null) => (value ? d(new Date(value), 'datetime') : '—')
const label = (group: string, key: string | null) => {
  const path = `admin.paymentExceptions.${group}.${key}`
  return key && te(path) ? t(path) : (key ?? '—')
}

const transfer = computed(() => {
  const row = props.detail
  if (!row) return []
  return [
    ['eventAt', time(row.event_at)],
    ['code', label('codes', row.code)],
    ['amount', formatCurrency(row.amount)],
    ['paymentCode', row.payment_code ?? '—'],
    ['status', label('statuses', row.status)],
  ]
})

const booking = computed(() => {
  const b = props.detail?.booking
  if (!b) return []
  const status = statusLabel(
    { code: b.code, status: b.status, metadata: { release_reason: b.release_reason ?? undefined } },
    t,
  )
  const play =
    b.starts_at && b.ends_at ? `${time(b.starts_at)}–${d(new Date(b.ends_at), 'time')}` : '—'
  return [
    ['bookingCode', b.code],
    ['bookingStatus', status],
    ['totalAmount', formatCurrency(b.total_amount)],
    ['deadline', time(b.payment_deadline)],
    ['court', [b.venue, b.court].filter(Boolean).join(' · ') || '—'],
    ['playTime', play],
    ['bookerName', b.booker.name ?? '—'],
    ['bookerPhone', b.booker.phone ?? '—'],
    ['bookerEmail', b.booker.email ?? '—'],
  ]
})
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      aria-labelledby="payment-exception-title"
      data-testid="owner-payment-exception-detail"
    >
      <div class="modal-header">
        <h3 id="payment-exception-title" class="modal-title">
          {{ t('admin.paymentExceptions.detail.title') }}
        </h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('admin.paymentExceptions.detail.close')"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>
      <div class="modal-body">
        <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
        <p v-else-if="error" role="alert" class="form-error">{{ error }}</p>
        <template v-else-if="detail">
          <dl class="facts">
            <div v-for="[key, value] in transfer" :key="key">
              <dt>{{ t(`admin.paymentExceptions.detail.${key}`) }}</dt>
              <dd>{{ value }}</dd>
            </div>
          </dl>
          <h4 class="section-title">{{ t('admin.paymentExceptions.detail.bookingTitle') }}</h4>
          <dl v-if="booking.length" class="facts" data-testid="owner-payment-exception-booking">
            <div v-for="[key, value] in booking" :key="key">
              <dt>{{ t(`admin.paymentExceptions.detail.${key}`) }}</dt>
              <dd>{{ value }}</dd>
            </div>
          </dl>
          <p v-else class="muted">{{ t('admin.paymentExceptions.detail.noBooking') }}</p>
          <p class="muted">{{ t('admin.paymentExceptions.readOnly') }}</p>
        </template>
      </div>
    </section>
  </div>
</template>

<style scoped>
.facts {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0 0 12px;
  font-size: 13px;
}

.facts div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.facts dt {
  color: var(--muted);
}

.facts dd {
  margin: 0;
  text-align: right;
  font-weight: 600;
}

.section-title {
  margin: 4px 0 8px;
  font-size: 14px;
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 12.5px;
}
</style>
