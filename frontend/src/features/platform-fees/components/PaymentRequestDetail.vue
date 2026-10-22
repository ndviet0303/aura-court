<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { PlatformFeeRequest } from '../types/platformFee'

const props = defineProps<{
  code: string
  request: PlatformFeeRequest | null
  loading: boolean
  error: string | null
}>()
const emit = defineEmits<{ close: [] }>()

const { t, d } = useI18n()
const time = (iso: string | null) => (iso ? d(new Date(iso), 'datetime') : '—')

const fields = computed(() => {
  const r = props.request
  if (!r) return []
  return [
    ['paymentCode', r.payment_code],
    ['venue', r.venue.name || '—'],
    ['organization', r.organization.name || '—'],
    ['owner', r.owner ? [r.owner.name, r.owner.email].filter(Boolean).join(' · ') : '—'],
    ['kind', t(`admin.packages.kind.${r.kind}`)],
    ['term', t('admin.packages.termLabel', { days: r.term_days })],
    ['amount', formatCurrency(r.amount)],
    ['status', t(`admin.packages.requestStatus.${r.status}`)],
    ['createdAt', time(r.created_at)],
    ['expiresAt', time(r.expires_at)],
    ['paidAt', time(r.paid_at)],
    [
      'period',
      r.period_starts_at
        ? t('admin.platformFees.requestDetail.period', {
            start: time(r.period_starts_at),
            end: time(r.period_ends_at),
          })
        : '—',
    ],
  ] as const
})
</script>

<template>
  <section class="request-detail" :aria-busy="loading" data-testid="platform-fee-request-detail">
    <header class="detail-head">
      <h2 class="detail-title">{{ t('admin.platformFees.requestDetail.title', { code }) }}</h2>
      <button
        type="button"
        class="close-btn"
        :aria-label="t('admin.platformFees.requestDetail.close')"
        @click="emit('close')"
      >
        <span class="material-symbols-outlined" aria-hidden="true">close</span>
      </button>
    </header>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="loading || !request" class="muted">{{ t('common.loading') }}</p>
    <template v-else>
      <dl class="fields">
        <div v-for="[key, value] in fields" :key="key" class="field">
          <dt>{{ t(`admin.platformFees.requestDetail.fields.${key}`) }}</dt>
          <dd>{{ value }}</dd>
        </div>
      </dl>

      <h3 class="section-title">{{ t('admin.platformFees.requestDetail.receipts') }}</h3>
      <p v-if="!request.receipts?.length" class="muted">
        {{ t('admin.platformFees.requestDetail.receiptsEmpty') }}
      </p>
      <div v-else class="table-wrap">
        <table class="fee-table" data-testid="platform-fee-request-receipts">
          <thead>
            <tr>
              <th scope="col">
                {{ t('admin.platformFees.requestDetail.receiptColumns.transaction') }}
              </th>
              <th scope="col" class="num">
                {{ t('admin.platformFees.requestDetail.receiptColumns.amount') }}
              </th>
              <th scope="col">
                {{ t('admin.platformFees.requestDetail.receiptColumns.transferredAt') }}
              </th>
              <th scope="col">
                {{ t('admin.platformFees.requestDetail.receiptColumns.receivedAt') }}
              </th>
              <th scope="col">
                {{ t('admin.platformFees.requestDetail.receiptColumns.outcome') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="receipt in request.receipts"
              :key="receipt.id"
              :data-testid="`platform-fee-receipt-${receipt.id}`"
            >
              <th scope="row" class="code">{{ receipt.provider_transaction_id }}</th>
              <td class="num">{{ formatCurrency(receipt.amount) }}</td>
              <td>{{ time(receipt.transferred_at) }}</td>
              <td>{{ time(receipt.received_at) }}</td>
              <td :class="`outcome-${receipt.outcome}`">
                {{ t(`admin.platformFees.requestDetail.outcomes.${receipt.outcome}`) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>

<style scoped>
.request-detail {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--primary);
  border-radius: 16px;
}

.detail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.detail-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.close-btn {
  display: inline-flex;
  padding: 4px;
  border: none;
  border-radius: 8px;
  background: none;
  color: var(--ink);
  cursor: pointer;
}

.error,
.muted {
  margin: 0;
  font-size: 13px;
}

.muted {
  color: var(--muted);
}

.error {
  color: var(--error-text);
}

.fields {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px 16px;
  margin: 0;
}

.field dt {
  font-size: 12px;
  color: var(--muted);
}

.field dd {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 600;
}

.section-title {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.fee-table {
  width: 100%;
  border-collapse: collapse;
}

.fee-table th,
.fee-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--outline);
  white-space: nowrap;
  font-size: 13px;
}

.fee-table thead th {
  font-size: 12px;
  color: var(--muted);
}

.fee-table .num {
  text-align: right;
  font-weight: 700;
}

.code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-weight: 400;
}

.outcome-applied {
  color: var(--success-text);
}

.outcome-late,
.outcome-amount_mismatch,
.outcome-already_paid,
.outcome-term_conflict {
  color: var(--warning-text);
}
</style>
