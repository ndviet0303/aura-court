<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { PackagePaymentRequest } from '../types'

const props = defineProps<{ requests: PackagePaymentRequest[]; expiredCode?: string }>()

const { t, d } = useI18n()
// The card's countdown hit 00:00: show its pending request as expired before any reload (D7).
const shownStatus = (r: PackagePaymentRequest) =>
  r.status === 'pending' && r.request_code === props.expiredCode ? 'expired' : r.status
</script>

<template>
  <section class="history">
    <h3 class="history-title">{{ t('admin.packages.history.title') }}</h3>
    <div class="table-wrap">
      <table class="history-table">
        <thead>
          <tr>
            <th scope="col">{{ t('admin.packages.history.columns.code') }}</th>
            <th scope="col">{{ t('admin.packages.history.columns.kind') }}</th>
            <th scope="col">{{ t('admin.packages.history.columns.term') }}</th>
            <th scope="col" class="num">{{ t('admin.packages.history.columns.amount') }}</th>
            <th scope="col">{{ t('admin.packages.history.columns.status') }}</th>
            <th scope="col">{{ t('admin.packages.history.columns.paidAt') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="request in requests"
            :key="request.request_code"
            :data-testid="`package-request-row-${request.request_code}`"
          >
            <th scope="row" class="font-mono">{{ request.request_code }}</th>
            <td>{{ t(`admin.packages.kind.${request.kind}`) }}</td>
            <td>{{ t('admin.packages.termLabel', { days: request.days }) }}</td>
            <td class="num">{{ formatCurrency(request.amount) }}</td>
            <td>{{ t(`admin.packages.requestStatus.${shownStatus(request)}`) }}</td>
            <td>{{ request.paid_at ? d(new Date(request.paid_at), 'datetime') : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.history {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.history-title {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
}

.history-table th,
.history-table td {
  padding: 8px 12px;
  text-align: left;
  border-bottom: 1px solid var(--outline);
  white-space: nowrap;
  font-size: 13px;
}

.history-table thead th {
  font-size: 12px;
  color: var(--muted);
}

.history-table .num {
  text-align: right;
  font-weight: 700;
}
</style>
