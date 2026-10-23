<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency, formatDateTime } from '@/i18n'
import type { VenueCustomer } from '../types/customer'
import CustomerTierBadge from './CustomerTierBadge.vue'

/** FR-110: completed and no-show counts, time and amount of the quarter, last visit, tier and usage rate. */
defineProps<{ customers: VenueCustomer[] }>()
const emit = defineEmits<{ select: [userId: number] }>()
const { t } = useI18n()

const hours = (minutes: number) =>
  (minutes / 60).toLocaleString('vi-VN', { maximumFractionDigits: 1 })
</script>

<template>
  <p v-if="!customers.length" class="muted">{{ t('customers.empty') }}</p>
  <div v-else class="table-wrap">
    <table class="customers">
      <thead>
        <tr>
          <th>{{ t('customers.name') }}</th>
          <th>{{ t('customers.tier') }}</th>
          <th>{{ t('customers.completed') }}</th>
          <th>{{ t('customers.noShow') }}</th>
          <th>{{ t('customers.usageRate') }}</th>
          <th>{{ t('customers.hours') }}</th>
          <th>{{ t('customers.amount') }}</th>
          <th>{{ t('customers.lastUsed') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in customers" :key="row.id">
          <td>
            <button type="button" class="link" @click="emit('select', row.id)">
              {{ row.name }}
            </button>
            <div class="muted">{{ row.phone ?? row.email ?? '' }}</div>
          </td>
          <td><CustomerTierBadge :tier="row.tier" /></td>
          <td>{{ row.completed }}</td>
          <td>{{ row.no_show }}</td>
          <td>{{ row.usage_rate === null ? t('customers.noData') : `${row.usage_rate}%` }}</td>
          <td>{{ hours(row.used_minutes) }}</td>
          <td>{{ formatCurrency(row.completed_amount) }}</td>
          <td>{{ row.last_used_at ? formatDateTime(row.last_used_at) : '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.table-wrap {
  overflow-x: auto;
}

.customers {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.customers th,
.customers td {
  padding: 8px 10px;
  border-bottom: 1px solid var(--outline);
  text-align: left;
  white-space: nowrap;
}

.customers th {
  color: var(--muted);
  font-weight: 600;
}

.link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--primary);
  font-weight: 700;
  cursor: pointer;
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 12px;
}
</style>
