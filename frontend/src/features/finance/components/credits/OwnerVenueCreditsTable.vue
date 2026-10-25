<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { VenueCreditEntry } from '@/core/types/venueCredit'

defineProps<{ entries: VenueCreditEntry[] }>()

const { t, d } = useI18n()

// A reserve or release only moves credit between balance and reserved, so it shows its plain amount.
function delta(entry: VenueCreditEntry): string {
  if (!entry.balance_delta) return formatCurrency(entry.amount)
  return `${entry.balance_delta > 0 ? '+' : '−'}${formatCurrency(Math.abs(entry.balance_delta))}`
}
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm" data-testid="owner-venue-credits">
      <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
        <tr>
          <th scope="col" class="px-4 py-3">{{ t('admin.venueCredits.columns.at') }}</th>
          <th scope="col" class="px-4 py-3">{{ t('admin.venueCredits.columns.customer') }}</th>
          <th scope="col" class="px-4 py-3">{{ t('admin.venueCredits.columns.type') }}</th>
          <th scope="col" class="px-4 py-3 text-right">
            {{ t('admin.venueCredits.columns.amount') }}
          </th>
          <th scope="col" class="px-4 py-3 text-right">
            {{ t('admin.venueCredits.columns.balanceAfter') }}
          </th>
          <th scope="col" class="px-4 py-3">{{ t('admin.venueCredits.columns.reference') }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <tr v-for="entry in entries" :key="entry.id" class="text-gray-800 dark:text-gray-200">
          <td class="px-4 py-3 whitespace-nowrap">
            {{ entry.created_at ? d(new Date(entry.created_at), 'datetime') : '—' }}
          </td>
          <td class="px-4 py-3">{{ entry.customer?.name || '—' }}</td>
          <td class="px-4 py-3">{{ t(`profile.credit.types.${entry.type}`) }}</td>
          <td class="px-4 py-3 text-right whitespace-nowrap font-medium">{{ delta(entry) }}</td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            {{ formatCurrency(entry.balance_after) }}
          </td>
          <td class="px-4 py-3 font-mono text-xs">{{ entry.reference || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
