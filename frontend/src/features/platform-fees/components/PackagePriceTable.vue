<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { PackagePrice, PackageTermPrice } from '../types/platformFee'

defineProps<{ terms: PackageTermPrice[] }>()

const { t, d } = useI18n()

const amount = (price: PackagePrice | null) => (price ? formatCurrency(price.amount) : '—')
const effectiveAt = (price: PackagePrice | null) =>
  price ? d(new Date(price.effective_at), 'datetime') : '—'
</script>

<template>
  <div class="table-wrap">
    <table class="price-table" data-testid="package-price-table">
      <thead>
        <tr>
          <th scope="col">{{ t('admin.platformFees.prices.columns.term') }}</th>
          <th scope="col" class="num">{{ t('admin.platformFees.prices.columns.current') }}</th>
          <th scope="col">{{ t('admin.platformFees.prices.columns.currentSince') }}</th>
          <th scope="col" class="num">{{ t('admin.platformFees.prices.columns.next') }}</th>
          <th scope="col">{{ t('admin.platformFees.prices.columns.nextAt') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in terms"
          :key="row.term_days"
          :data-testid="`package-price-row-${row.term_days}`"
        >
          <th scope="row">{{ t('admin.platformFees.prices.termLabel', { days: row.days }) }}</th>
          <td class="num">{{ amount(row.current_price) }}</td>
          <td>{{ effectiveAt(row.current_price) }}</td>
          <td class="num">{{ amount(row.next_price) }}</td>
          <td>{{ effectiveAt(row.next_price) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--outline);
  border-radius: 16px;
}

.price-table {
  width: 100%;
  border-collapse: collapse;
}

.price-table th,
.price-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--outline);
  white-space: nowrap;
}

.price-table thead th {
  font-size: 12px;
  color: var(--muted);
}

.price-table .num {
  text-align: right;
  font-weight: 700;
}
</style>
