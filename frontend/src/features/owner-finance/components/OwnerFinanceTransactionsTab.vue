<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { useOwnerFinanceTransactions } from '../composables/useOwnerFinanceTransactions'
import type { OwnerFinanceFilters, OwnerFinanceTransaction } from '../types/ownerFinance'
import OwnerFinanceBookingCell from './OwnerFinanceBookingCell.vue'

const props = defineProps<{ organizationId: number; filters: OwnerFinanceFilters }>()

const { t, te, d } = useI18n()
const { transactions, page, lastPage, total, loading, error, load } = useOwnerFinanceTransactions(
  props.organizationId,
  () => props.filters,
)

// Unknown codes show raw rather than blank; the exception codes are module 4's receipt codes.
const label = (path: string, key: string) => (te(`${path}.${key}`) ? t(`${path}.${key}`) : key)

const amount = (row: OwnerFinanceTransaction) =>
  row.currency === 'VND' ? formatCurrency(row.amount) : `${row.amount} ${row.currency}`

// Δ = Aⱼ − Pᵢ: 0 matches, below 0 is short, above 0 is excess.
const deltaKind = (delta: number) => (delta === 0 ? 'match' : delta < 0 ? 'short' : 'excess')
const signed = (delta: number) => `${delta > 0 ? '+' : ''}${formatCurrency(delta)}`
</script>

<template>
  <section class="transactions-tab" :aria-busy="loading">
    <p class="note">{{ t('admin.ownerFinance.transactions.note') }}</p>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="loading && !transactions.length" class="muted">{{ t('common.loading') }}</p>
    <p
      v-else-if="!transactions.length"
      class="muted"
      data-testid="owner-finance-transactions-empty"
    >
      {{ t('admin.ownerFinance.transactions.empty') }}
    </p>
    <template v-else>
      <p class="muted">{{ t('admin.ownerFinance.transactions.total', { total }) }}</p>
      <div class="table-wrap">
        <table class="finance-table" data-testid="owner-finance-transactions">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.ownerFinance.transactions.columns.time') }}</th>
              <th scope="col">{{ t('admin.ownerFinance.transactions.columns.venue') }}</th>
              <th scope="col" class="num">
                {{ t('admin.ownerFinance.transactions.columns.amount') }}
              </th>
              <th scope="col">{{ t('admin.ownerFinance.transactions.columns.paymentCode') }}</th>
              <th scope="col">{{ t('admin.ownerFinance.transactions.columns.booking') }}</th>
              <th scope="col" class="num">
                {{ t('admin.ownerFinance.transactions.columns.payable') }}
              </th>
              <th scope="col" class="num">
                {{ t('admin.ownerFinance.transactions.columns.delta') }}
              </th>
              <th scope="col">{{ t('admin.ownerFinance.transactions.columns.result') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in transactions"
              :key="row.id"
              :data-testid="`owner-finance-transaction-${row.id}`"
            >
              <td>{{ d(new Date(row.transaction_at), 'datetime') }}</td>
              <td>{{ row.venue.name || '—' }}</td>
              <td class="num">{{ amount(row) }}</td>
              <td>
                <span class="font-mono">{{ row.payment_code || '—' }}</span>
              </td>
              <td>
                <OwnerFinanceBookingCell v-if="row.booking" :booking="row.booking" />
                <span v-else class="sub">
                  {{ t('admin.ownerFinance.transactions.noBooking') }}
                  <template v-if="row.purpose">
                    · {{ label('admin.paymentExceptions.purposes', row.purpose) }}
                  </template>
                </span>
              </td>
              <td class="num">{{ row.booking ? formatCurrency(row.booking.payable) : '—' }}</td>
              <td class="num" :data-testid="`owner-finance-delta-${row.id}`">
                <template v-if="row.delta !== null">
                  <span :class="`delta-${deltaKind(row.delta)}`">{{ signed(row.delta) }}</span>
                  <span class="sub">
                    {{ t(`admin.ownerFinance.transactions.delta.${deltaKind(row.delta)}`) }}
                  </span>
                </template>
                <template v-else>—</template>
              </td>
              <td>
                <span :class="`status-${row.status}`">
                  {{ label('admin.ownerFinance.transactions.statuses', row.status) }}
                </span>
                <span v-if="row.exception_code" class="sub">
                  {{ label('admin.paymentExceptions.codes', row.exception_code) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav
        v-if="lastPage > 1"
        class="pager"
        :aria-label="t('admin.ownerFinance.transactions.pager')"
      >
        <button type="button" :disabled="loading || page <= 1" @click="load(page - 1)">
          {{ t('admin.ownerFinance.transactions.prevPage') }}
        </button>
        <span>{{ t('admin.ownerFinance.transactions.pageOf', { page, last: lastPage }) }}</span>
        <button type="button" :disabled="loading || page >= lastPage" @click="load(page + 1)">
          {{ t('admin.ownerFinance.transactions.nextPage') }}
        </button>
      </nav>
    </template>
  </section>
</template>

<style scoped>
.transactions-tab {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.note,
.error,
.muted {
  margin: 0;
  font-size: 13px;
}

.note,
.muted,
.sub {
  color: var(--muted);
}

.error {
  color: var(--error-text);
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.finance-table {
  width: 100%;
  border-collapse: collapse;
}

.finance-table th,
.finance-table td {
  padding: 10px 12px;
  text-align: left;
  vertical-align: top;
  border-bottom: 1px solid var(--outline);
  white-space: nowrap;
  font-size: 13px;
}

.finance-table thead th {
  font-size: 12px;
  color: var(--muted);
}

.finance-table .num {
  text-align: right;
  font-weight: 700;
}

.sub {
  display: block;
  font-size: 12px;
  font-weight: 400;
}

.delta-match,
.status-allocated {
  color: var(--success-text);
}

.delta-short,
.delta-excess,
.status-unmatched,
.status-late {
  color: var(--warning-text);
}

.status-exception {
  color: var(--error-text);
}

.pager {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  font-size: 13px;
}
</style>
