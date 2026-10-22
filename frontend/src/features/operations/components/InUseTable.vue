<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { venueClock, venueClockRange } from '@/core/utils/venueTime'
import type { OperationalReservation } from '../types/operations'

/** FR-104: bookings checked in and not yet checked out, flagged 10 minutes before their booked end and once past it. */
defineProps<{ reservations: OperationalReservation[] }>()
const emit = defineEmits<{ select: [code: string] }>()
const { t } = useI18n()
</script>

<template>
  <p v-if="!reservations.length" class="muted">{{ t('operations.inUse.empty') }}</p>
  <div v-else class="table-wrap">
    <table class="in-use">
      <thead>
        <tr>
          <th>{{ t('operations.grid.court') }}</th>
          <th>{{ t('operations.detail.code') }}</th>
          <th>{{ t('operations.detail.customer') }}</th>
          <th>{{ t('operations.detail.booked') }}</th>
          <th>{{ t('operations.detail.checkedIn') }}</th>
          <th>{{ t('operations.inUse.flag') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in reservations" :key="row.id">
          <td>{{ row.court.code }}</td>
          <td>
            <button type="button" class="link" @click="emit('select', row.code)">
              {{ row.code }}
            </button>
          </td>
          <td>{{ row.customer_name ?? '—' }}</td>
          <td>{{ venueClockRange(row.starts_at, row.ends_at) }}</td>
          <td>{{ venueClock(row.attendance?.checked_in_at) }}</td>
          <td>
            <span v-if="row.usage_flag" class="flag" :class="`is-${row.usage_flag}`">
              {{ t(`operations.inUse.${row.usage_flag}`) }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.table-wrap {
  overflow-x: auto;
}

.in-use {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.in-use th,
.in-use td {
  padding: 8px 10px;
  border-bottom: 1px solid var(--outline);
  text-align: left;
  white-space: nowrap;
}

.in-use th {
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

.flag {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.flag.is-ending_soon {
  background: rgba(245, 158, 11, 0.16);
  color: #92400e;
}

.flag.is-overdue {
  background: rgba(239, 68, 68, 0.14);
  color: #b91c1c;
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>
