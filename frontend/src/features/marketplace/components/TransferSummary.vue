<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, formatDateTime } from '@/i18n'
import type { BookingTransfer } from '../types/transfer'
import TransferBookingLine from './TransferBookingLine.vue'

// FR-144: seller, buyer, each booking with its agreed price, the total and the status.
const props = defineProps<{ transfer: BookingTransfer; refusals: Record<number, string> }>()
const { t } = useI18n()

const times = computed(() =>
  (
    [
      ['createdAt', props.transfer.created_at],
      ['confirmedAt', props.transfer.confirmed_at],
      ['cancelledAt', props.transfer.cancelled_at],
    ] as const
  ).flatMap(([key, value]) => (value ? [{ key, value: formatDateTime(value) }] : [])),
)
// A stale re-check is noise once the transaction is over.
const reason = (id: number, refusal: string | undefined) =>
  props.refusals[id] ?? (props.transfer.status === 'pending' ? refusal : undefined)
</script>

<template>
  <section class="summary">
    <div class="parties">
      <div>
        <span class="label">{{ t('resale.transfer.seller') }}</span>
        <strong>{{ transfer.seller.name }}</strong>
      </div>
      <div>
        <span class="label">{{ t('resale.transfer.buyer') }}</span>
        <strong>{{ transfer.buyer.name }}</strong>
      </div>
      <div>
        <span class="label">{{ t('resale.transfer.statusLabel') }}</span>
        <span class="status" :class="transfer.status">
          {{ t(`resale.transfers.status.${transfer.status}`) }}
        </span>
      </div>
    </div>

    <ul class="items">
      <li v-for="item in transfer.items" :key="item.reservation_id" class="item">
        <TransferBookingLine
          :venue="item.venue"
          :court="item.court"
          :starts-at="item.starts_at"
          :ends-at="item.ends_at"
        >
          <strong>{{ formatCurrency(item.price) }}</strong>
        </TransferBookingLine>
        <p v-if="reason(item.reservation_id, item.refusal?.message)" class="refusal" role="alert">
          {{ reason(item.reservation_id, item.refusal?.message) }}
        </p>
      </li>
    </ul>

    <p class="total">
      {{ t('resale.transfer.total') }}: <strong>{{ formatCurrency(transfer.total) }}</strong>
    </p>
    <dl class="times">
      <div v-for="row in times" :key="row.key">
        <dt>{{ t(`resale.transfer.${row.key}`) }}</dt>
        <dd>{{ row.value }}</dd>
      </div>
    </dl>
  </section>
</template>

<style scoped>
.summary {
  display: grid;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 14px;
  background: var(--surface);
  color: var(--ink);
}
.parties {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
}
.parties > div {
  display: grid;
  gap: 2px;
}
.label,
dt {
  color: var(--muted);
  font-size: 12px;
}
.status {
  justify-self: start;
  padding: 2px 8px;
  border-radius: 999px;
  background: color-mix(in srgb, #d97706 14%, transparent);
  color: #d97706;
  font-size: 12px;
}
.status.completed {
  background: color-mix(in srgb, #16a34a 14%, transparent);
  color: #16a34a;
}
.status.cancelled {
  background: color-mix(in srgb, var(--muted) 14%, transparent);
  color: var(--muted);
}
.items {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.item {
  display: grid;
  gap: 6px;
  padding: 10px 12px;
  border: 1px solid var(--outline);
  border-radius: 12px;
}
.refusal {
  margin: 0;
  color: #dc2626;
  font-size: 12px;
}
.total {
  margin: 0;
  text-align: right;
}
.times {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 16px;
  margin: 0;
  font-size: 13px;
}
dd {
  margin: 0;
}
</style>
