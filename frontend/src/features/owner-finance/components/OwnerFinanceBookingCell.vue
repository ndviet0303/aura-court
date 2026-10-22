<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { venueClockRange } from '@/core/utils/venueTime'
import type { OwnerFinanceBooking } from '../types/ownerFinance'

defineProps<{ booking: OwnerFinanceBooking }>()

const { t, te, d } = useI18n()
const status = (key: string) =>
  te(`admin.ownerFinance.bookingStatus.${key}`) ? t(`admin.ownerFinance.bookingStatus.${key}`) : key
</script>

<template>
  <span class="booking">
    <span>
      <span class="font-mono code">{{ booking.code }}</span>
      <span class="status">{{ status(booking.status) }}</span>
    </span>
    <span class="sub">
      {{ [booking.venue.name, booking.court.code].filter(Boolean).join(' · ') || '—' }}
    </span>
    <span v-if="booking.starts_at" class="sub">
      {{ d(new Date(booking.starts_at), 'date') }}
      {{ venueClockRange(booking.starts_at, booking.ends_at) }}
    </span>
  </span>
</template>

<style scoped>
.booking {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.code {
  font-weight: 700;
}

.status {
  margin-left: 6px;
  font-size: 12px;
  color: var(--muted);
}

.sub {
  font-size: 12px;
  color: var(--muted);
}
</style>
