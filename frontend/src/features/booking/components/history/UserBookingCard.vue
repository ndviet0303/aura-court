<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { VENUE_TIME_ZONE, venueClockRange } from '@/core/utils/venueTime'
import type { Reservation } from '../../types/reservation'
import {
  canOfferTransfer,
  canReview,
  canEditReview,
  displayCode,
  displayStatus,
  showsQr,
  statusLabel,
} from '../../utils/reservation'

const { t, locale } = useI18n()

const props = defineProps<{ booking: Reservation }>()

const emit = defineEmits<{
  (e: 'review', booking: Reservation): void
  (e: 'qr', booking: Reservation): void
}>()

function formatDate(iso: string): string {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    return d.toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
      timeZone: VENUE_TIME_ZONE,
      weekday: 'short',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return iso.slice(0, 10)
  }
}

const status = computed(() => displayStatus(props.booking))
const isPendingPayment = computed(() => status.value === 'held')

const STATUS_STYLE: Record<string, [icon: string, chipClass: string]> = {
  held: ['schedule', 'status-pending'],
  confirmed: ['verified', 'status-confirmed'],
  in_use: ['sports_tennis', 'status-confirmed'],
  completed: ['task_alt', 'status-confirmed'],
  refunded: ['currency_exchange', 'status-refunded'],
}
const statusMeta = computed(() => {
  const [icon, cls] = STATUS_STYLE[status.value] ?? ['cancel', 'status-cancelled']
  return { label: statusLabel(props.booking, t), icon, class: cls }
})

const reviewLabel = computed(() => {
  const review = props.booking.review
  if (!review) return t('booking.reviewBtn')
  const key = canEditReview(props.booking) ? 'editReviewBtn' : 'viewReviewBtn'
  return `${t(`booking.${key}`)} (${review.rating}★)`
})

const sportLabel = computed(() => t(`sportTypes.${props.booking.court?.sport_type ?? 'badminton'}`))
</script>

<template>
  <div class="user-booking-card" :class="`border-${status}`">
    <div class="card-header">
      <div class="header-main">
        <div class="code-and-type">
          <span class="booking-code font-mono">#{{ displayCode(booking) }}</span>
          <span class="sport-badge font-mono">{{ sportLabel.toUpperCase() }}</span>
        </div>
        <h3 class="court-name">
          <RouterLink
            v-if="booking.court?.id"
            :to="`/courts/${booking.court.id}`"
            class="court-link"
          >
            {{ booking.court?.code || 'Sân Aura Court' }}
          </RouterLink>
          <span v-else>{{ booking.court?.code || 'Sân Aura Court' }}</span>
        </h3>
        <div class="venue-info">
          <span class="material-symbols-outlined venue-icon">location_on</span>
          <span class="venue-text">{{
            booking.court?.venue?.name || 'Aura Badminton Center'
          }}</span>
          <RouterLink
            v-if="booking.court?.id"
            :to="`/courts/${booking.court.id}/directions`"
            class="directions-link"
            :title="$t('courts.directionsBtn')"
          >
            ({{ $t('courts.directionsBtn') }})
          </RouterLink>
        </div>
      </div>

      <div class="status-chip" :class="statusMeta.class">
        <span class="material-symbols-outlined status-icon">{{ statusMeta.icon }}</span>
        <span>{{ statusMeta.label }}</span>
      </div>
    </div>

    <div class="slots-box">
      <div v-for="s in booking.slots" :key="s.starts_at" class="slot-item">
        <div class="slot-time-col">
          <span class="material-symbols-outlined slot-icon">schedule</span>
          <span class="slot-time font-mono">{{ venueClockRange(s.starts_at, s.ends_at) }}</span>
        </div>
        <div class="slot-date-col">
          <span class="material-symbols-outlined slot-icon">calendar_today</span>
          <span class="slot-date">{{ formatDate(s.starts_at) }}</span>
        </div>
        <div class="slot-price-col">
          <span class="slot-price font-mono">{{ formatCurrency(s.unit_price) }}</span>
        </div>
      </div>

      <div
        v-if="booking.loyalty_discount_amount && booking.loyalty_discount_amount > 0"
        class="discount-row"
      >
        <span class="discount-label">
          {{ $t('loyalty.loyaltyDiscount') }}
          <span v-if="booking.loyalty_points_redeemed"
            >({{ booking.loyalty_points_redeemed }} {{ $t('loyalty.points') }})</span
          >:
        </span>
        <span class="discount-value font-mono"
          >-{{ formatCurrency(booking.loyalty_discount_amount) }}</span
        >
      </div>
    </div>
    <div class="card-footer">
      <div class="total-section">
        <span class="total-label">{{ $t('booking.totalAmountLabel') }}</span>
        <strong class="total-amount font-mono">{{ formatCurrency(booking.total_amount) }}</strong>
      </div>

      <div class="actions-group">
        <RouterLink
          v-if="isPendingPayment"
          :to="{ name: 'reservations', params: { code: booking.code } }"
          class="btn-card-pay"
        >
          <span class="material-symbols-outlined">payments</span>
          <span>{{ $t('booking.payNowBtn') }}</span>
        </RouterLink>

        <!-- QR Check-in Pass -->
        <button
          v-if="showsQr(booking)"
          type="button"
          class="btn-card-secondary"
          @click="emit('qr', booking)"
        >
          <span class="material-symbols-outlined">qr_code_2</span>
          <span>{{ $t('booking.checkInQrBtn') }}</span>
        </button>

        <!-- Review: write (BR-147 window), edit within 7 days, or read it with the venue's reply -->
        <button
          v-if="canReview(booking) || (booking.review && !booking.review.deleted_at)"
          type="button"
          class="btn-card-secondary"
          @click="emit('review', booking)"
        >
          <span class="material-symbols-outlined">rate_review</span>
          <span>{{ reviewLabel }}</span>
        </button>

        <!-- Bookings are final: an upcoming confirmed booking can only be passed on by transfer (UC-9.1) -->
        <RouterLink
          v-if="canOfferTransfer(booking)"
          :to="{ name: 'resale-new', query: { reservation: booking.id } }"
          class="btn-card-secondary"
          data-testid="booking-resale-btn"
        >
          <span class="material-symbols-outlined">sell</span>
          <span>{{ $t('booking.resaleBtn') }}</span>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-booking-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 22px 24px;
  border-radius: 18px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 2px 12px rgba(107, 45, 63, 0.03);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.user-booking-card:hover {
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.08);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.header-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.code-and-type {
  display: flex;
  align-items: center;
  gap: 8px;
}

.booking-code {
  font-size: 12px;
  font-weight: 800;
  color: var(--primary, #6b2d3f);
}

.sport-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.04));
  color: var(--muted);
  border: 1px solid var(--border);
}

.court-name {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.court-link {
  color: var(--ink);
  text-decoration: none;
}

.court-link:hover {
  color: var(--primary, #6b2d3f);
  text-decoration: underline;
}

.venue-info {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12.5px;
  color: var(--muted);
}

.venue-icon {
  font-size: 15px;
  color: var(--primary, #6b2d3f);
}

.directions-link {
  color: var(--accent, #c9a15a);
  text-decoration: none;
  font-size: 12px;
  font-weight: 600;
  margin-left: 4px;
}

.directions-link:hover {
  text-decoration: underline;
}

.status-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 800;
  padding: 4px 10px;
  border-radius: 8px;
  white-space: nowrap;
  text-transform: uppercase;
}

.status-icon {
  font-size: 15px;
}

.status-confirmed {
  background: var(--success-bg, rgba(46, 125, 50, 0.12));
  color: var(--success-text, #2e7d32);
}

.status-pending {
  background: var(--warning-bg, rgba(239, 108, 0, 0.12));
  color: var(--warning-text, #ef6c00);
}

.status-cancelled {
  background: var(--error-bg, rgba(198, 40, 40, 0.12));
  color: var(--error-text, #c62828);
}

.status-refunded {
  background: var(--purple-bg, rgba(123, 31, 162, 0.12));
  color: var(--purple-text, #7b1fa2);
}

.slots-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 16px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--border);
}

.slot-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
  gap: 12px;
  flex-wrap: wrap;
}

.slot-time-col,
.slot-date-col {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--ink);
}

.slot-icon {
  font-size: 15px;
  color: var(--muted);
}

.slot-price-col {
  font-weight: 700;
  color: var(--primary, #6b2d3f);
}

.discount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: var(--success-text, #2e7d32);
  padding-top: 6px;
  border-top: 1px dashed var(--border);
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--outline);
}

.total-section {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.total-label {
  font-size: 12.5px;
  color: var(--muted);
}

.total-amount {
  font-size: 18px;
  font-weight: 800;
  color: var(--primary, #6b2d3f);
}

.actions-group {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-card-pay {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 10px;
  border: 0;
  background: var(--primary, #6b2d3f);
  color: var(--on-primary, #fff);
  font-size: 12.5px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.2);
}

.btn-card-secondary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-card-secondary:hover {
  border-color: var(--primary, #6b2d3f);
  color: var(--primary, #6b2d3f);
}

.btn-card-danger {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid rgba(198, 40, 40, 0.2);
  background: transparent;
  color: var(--error-text, #c62828);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-card-danger:hover {
  background: var(--error-bg, rgba(198, 40, 40, 0.08));
}

.btn-card-pay .material-symbols-outlined,
.btn-card-secondary .material-symbols-outlined,
.btn-card-danger .material-symbols-outlined {
  font-size: 16px;
}
</style>
