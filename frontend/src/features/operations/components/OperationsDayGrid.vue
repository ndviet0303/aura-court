<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { venueClockRange } from '@/core/utils/venueTime'
import type { OperationalReservation, OperationsDay } from '../types/operations'

/**
 * FR-101, BR-121: the venue day as one row per court and one bar per booking. A bar always spans the booked time;
 * the operational status is a separate label, so a late check-out never stretches the bar.
 */
const props = defineProps<{ day: OperationsDay; selectedCode?: string | null }>()
const emit = defineEmits<{ select: [code: string] }>()
const { t } = useI18n()

const DAY_MS = 24 * 60 * 60 * 1000
const HOURS = Array.from({ length: 24 }, (_, hour) => hour)

// The day is [00:00, 24:00) on the venue clock (+07:00, no DST).
const dayStart = computed(() => Date.parse(`${props.day.date}T00:00:00+07:00`))
const percent = (instant: string) =>
  Math.min(100, Math.max(0, ((Date.parse(instant) - dayStart.value) / DAY_MS) * 100))

const rows = computed(() =>
  props.day.courts.map((court) => ({
    court,
    bookings: props.day.reservations.filter((r) => r.court.id === court.id),
  })),
)

const barStyle = (booking: OperationalReservation) => {
  const left = percent(booking.starts_at)
  return { left: `${left}%`, width: `${Math.max(percent(booking.ends_at) - left, 1)}%` }
}
</script>

<template>
  <div class="day-grid" role="table" :aria-label="t('operations.grid.label')">
    <div class="grid-row grid-head" role="row">
      <span class="court-cell" role="columnheader">{{ t('operations.grid.court') }}</span>
      <div class="track" role="columnheader">
        <span
          v-for="hour in HOURS"
          :key="hour"
          class="hour"
          :style="{ left: `${(hour / 24) * 100}%` }"
        >
          {{ String(hour).padStart(2, '0') }}
        </span>
      </div>
    </div>
    <div v-for="row in rows" :key="row.court.id" class="grid-row" role="row">
      <span class="court-cell" role="rowheader">
        <strong>{{ row.court.code }}</strong>
        <small v-if="row.court.status !== 'active'" class="court-status">
          {{ t(`operations.courtStatus.${row.court.status}`) }}
        </small>
      </span>
      <div class="track" role="cell">
        <button
          v-for="booking in row.bookings"
          :key="booking.id"
          type="button"
          class="bar"
          :class="[
            `is-${booking.operational_status}`,
            { 'is-selected': booking.check_in_code === selectedCode },
          ]"
          :style="barStyle(booking)"
          :title="`${booking.code} · ${venueClockRange(booking.starts_at, booking.ends_at)}`"
          @click="emit('select', booking.check_in_code)"
        >
          <span class="bar-code">{{ booking.code }}</span>
          <span class="bar-status">{{ t(`operations.status.${booking.operational_status}`) }}</span>
        </button>
      </div>
    </div>
    <p v-if="!rows.length" class="muted">{{ t('operations.grid.noCourts') }}</p>
  </div>
</template>

<style scoped>
.day-grid {
  overflow-x: auto;
}

.grid-row {
  display: grid;
  grid-template-columns: 96px minmax(960px, 1fr);
  min-height: 44px;
  border-bottom: 1px solid var(--outline);
}

.grid-head {
  min-height: 28px;
  color: var(--muted);
  font-size: 11px;
}

.court-cell {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 4px 8px;
  font-size: 13px;
}

.court-status {
  color: #b45309;
  font-size: 11px;
}

.track {
  position: relative;
  background-image: linear-gradient(to right, var(--outline) 1px, transparent 1px);
  background-size: calc(100% / 24) 100%;
}

.hour {
  position: absolute;
  top: 6px;
  padding-left: 3px;
}

.bar {
  position: absolute;
  top: 4px;
  bottom: 4px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  overflow: hidden;
  padding: 0 6px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-size: 11px;
  text-align: left;
  white-space: nowrap;
  cursor: pointer;
}

.bar-code {
  font-weight: 700;
}

.bar.is-confirmed {
  background: rgba(59, 130, 246, 0.14);
  color: #1d4ed8;
}

.bar.is-in_use {
  background: rgba(16, 185, 129, 0.16);
  color: #047857;
}

.bar.is-completed {
  background: rgba(107, 114, 128, 0.16);
  color: var(--muted);
}

.bar.is-no_show {
  background: rgba(239, 68, 68, 0.14);
  color: #b91c1c;
}

.bar.is-selected {
  border-color: currentColor;
}

.muted {
  color: var(--muted);
  font-size: 13px;
}
</style>
