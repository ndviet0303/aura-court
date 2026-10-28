<script setup lang="ts">
import { formatSlotRange } from '@/i18n'
import type { TransferCourt, TransferVenue } from '../types/transfer'

// BR-180: venue, court, address, date, time and court image all come from the booking, read only.
defineProps<{ venue: TransferVenue; court: TransferCourt; startsAt: string; endsAt: string }>()
</script>

<template>
  <div class="booking-line">
    <img v-if="court.image_url" :src="court.image_url" alt="" class="court-image" loading="lazy" />
    <span v-else class="court-image placeholder material-symbols-outlined" aria-hidden="true">
      sports_tennis
    </span>
    <div class="booking-info">
      <strong>{{ court.name }} · {{ venue.name }}</strong>
      <span v-if="venue.address" class="muted">{{ venue.address }}</span>
      <span>{{ formatSlotRange(startsAt, endsAt) }}</span>
    </div>
    <slot />
  </div>
</template>

<style scoped>
.booking-line {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: center;
  min-width: 0;
  color: var(--ink);
  font-size: 13px;
}
.court-image {
  flex: none;
  width: 56px;
  height: 56px;
  border-radius: 10px;
  object-fit: cover;
}
.placeholder {
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--muted) 12%, transparent);
  color: var(--muted);
}
.booking-info {
  display: grid;
  flex: 1 1 180px;
  gap: 2px;
  min-width: 0;
  overflow-wrap: anywhere;
}
.muted {
  color: var(--muted);
  font-size: 12px;
}
</style>
