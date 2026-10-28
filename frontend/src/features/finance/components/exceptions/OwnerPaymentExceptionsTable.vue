<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { OwnerPaymentException } from '../../types/finance'

defineProps<{ exceptions: OwnerPaymentException[] }>()
const emit = defineEmits<{ (e: 'select', id: number): void }>()

const { t, te, d } = useI18n()

function label(group: string, key: string | null): string {
  if (!key) return '—'
  const path = `admin.paymentExceptions.${group}.${key}`
  return te(path) ? t(path) : key
}

function amount(row: OwnerPaymentException): string {
  return row.currency === 'VND' ? formatCurrency(row.amount) : `${row.amount} ${row.currency}`
}

function connection(row: OwnerPaymentException): string {
  const { bank_code: bank, account_last_four: lastFour } = row.connection
  return bank ? `${bank} ••${lastFour ?? ''}` : '—'
}
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm" data-testid="owner-payment-exceptions">
      <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
        <tr>
          <th scope="col" class="px-4 py-3">{{ t('admin.paymentExceptions.columns.eventAt') }}</th>
          <th scope="col" class="px-4 py-3">{{ t('admin.paymentExceptions.columns.code') }}</th>
          <th scope="col" class="px-4 py-3 text-right">
            {{ t('admin.paymentExceptions.columns.amount') }}
          </th>
          <th scope="col" class="px-4 py-3">
            {{ t('admin.paymentExceptions.columns.paymentCode') }}
          </th>
          <th scope="col" class="px-4 py-3">
            {{ t('admin.paymentExceptions.columns.connection') }}
          </th>
          <th scope="col" class="px-4 py-3">{{ t('admin.paymentExceptions.columns.booking') }}</th>
          <th scope="col" class="px-4 py-3">{{ t('admin.paymentExceptions.columns.status') }}</th>
          <th scope="col" class="px-4 py-3">
            <span class="sr-only">{{ t('admin.paymentExceptions.detail.open') }}</span>
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <tr v-for="row in exceptions" :key="row.id" class="text-gray-800 dark:text-gray-200">
          <td class="px-4 py-3 whitespace-nowrap">
            {{ row.event_at ? d(new Date(row.event_at), 'datetime') : '—' }}
          </td>
          <td class="px-4 py-3">{{ label('codes', row.code) }}</td>
          <td class="px-4 py-3 text-right whitespace-nowrap font-medium">{{ amount(row) }}</td>
          <td class="px-4 py-3 font-mono text-xs">{{ row.payment_code || '—' }}</td>
          <td class="px-4 py-3 whitespace-nowrap">{{ connection(row) }}</td>
          <td class="px-4 py-3">
            <span v-if="row.booking_code" class="font-mono text-xs">{{ row.booking_code }}</span>
            <span v-else>{{ label('purposes', row.purpose) }}</span>
          </td>
          <td class="px-4 py-3">{{ label('statuses', row.status) }}</td>
          <td class="px-4 py-3">
            <button
              type="button"
              class="text-xs font-medium text-primary underline"
              :data-testid="`owner-payment-exception-open-${row.id}`"
              @click="emit('select', row.id)"
            >
              {{ t('admin.paymentExceptions.detail.open') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
