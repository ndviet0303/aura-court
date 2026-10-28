<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { Reservation } from '../../types/reservation'
import { canOfferTransfer, displayCode, quoteDiscounts, statusLabel } from '../../utils/reservation'
import CancelPaymentButton from './CancelPaymentButton.vue'
import ReservationDetailFacts from './ReservationDetailFacts.vue'

const { t } = useI18n()
defineProps<{
  show: boolean
  reservation: Reservation | null
  money: (value: number) => string
  formatDate: (value: string) => string
  formatTimeSlot: (startsAt: string, endsAt?: string) => string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'pay', reservation: Reservation): void
  (e: 'cancelled', reservation: Reservation): void
}>()
</script>

<template>
  <div v-if="show && reservation" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-row">
          <span class="material-symbols-outlined header-icon">receipt_long</span>
          <div>
            <h3 class="modal-title">{{ t('reservations.modal.detailsTitle') }}</h3>
            <span class="modal-subtitle font-mono">{{
              t('reservations.modal.reservationCode', { code: displayCode(reservation) })
            }}</span>
          </div>
        </div>
        <button class="modal-close-btn" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- Status & Court Summary -->
        <div class="summary-section">
          <div class="info-group">
            <span class="info-label">{{ t('reservations.modal.courtInfo') }}</span>
            <strong class="info-value">{{ reservation.court?.code || 'Aura Court' }}</strong>
            <span class="info-sub"
              >{{ reservation.court?.venue?.name || 'Aura Court' }} ·
              {{ reservation.court?.venue?.address || '' }}</span
            >
          </div>
          <div class="info-group">
            <span class="info-label">{{ t('reservations.modal.statusLabel') }}</span>
            <span class="status-badge" :class="reservation.status">
              {{ statusLabel(reservation, t) }}
            </span>
          </div>
        </div>

        <ReservationDetailFacts :reservation="reservation" />

        <!-- Slots Table -->
        <div class="slots-table-section">
          <h4 class="section-heading">{{ t('reservations.modal.slotsHeading') }}</h4>
          <div class="slots-list">
            <div v-for="(slot, idx) in reservation.slots" :key="idx" class="slot-table-row">
              <div class="slot-info-col">
                <span class="material-symbols-outlined">schedule</span>
                <span class="font-mono">{{ formatTimeSlot(slot.starts_at, slot.ends_at) }}</span>
                <span class="slot-date-text">({{ formatDate(slot.starts_at) }})</span>
              </div>
              <div class="slot-price-col font-mono">
                {{ money(slot.unit_price) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Total Breakdown -->
        <div class="total-breakdown-card">
          <div
            v-for="line in quoteDiscounts(reservation.quote?.benefit_snapshot, reservation.voucher)"
            :key="line.label"
            class="breakdown-subline discount"
          >
            <span>{{ t(line.label) }}</span>
            <strong class="font-mono text-pink">-{{ money(line.amount) }}</strong>
          </div>
          <div
            v-if="reservation.loyalty_discount_amount && reservation.loyalty_discount_amount > 0"
            class="loyalty-sub-breakdown"
          >
            <div class="breakdown-subline">
              <span>{{ t('loyalty.pointsUsed') || 'Điểm đã dùng' }}:</span>
              <strong class="font-mono">{{ reservation.loyalty_points_redeemed }}</strong>
            </div>
            <div class="breakdown-subline discount">
              <span>{{ t('loyalty.discountAmount') || 'Giảm giá điểm' }}:</span>
              <strong class="font-mono text-pink"
                >-{{ money(reservation.loyalty_discount_amount) }}</strong
              >
            </div>
            <div class="breakdown-subline">
              <span>{{ t('loyalty.netPaid') || 'Thực trả từ ví' }}:</span>
              <strong class="font-mono">{{
                money(
                  reservation.net_payable ??
                    reservation.total_amount - reservation.loyalty_discount_amount,
                )
              }}</strong>
            </div>
          </div>
          <div class="total-row">
            <span>{{ t('reservations.modal.totalPriceFixed') }}</span>
            <strong class="total-price font-mono">{{ money(reservation.total_amount) }}</strong>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <template v-if="reservation.status === 'held'">
          <CancelPaymentButton
            :code="reservation.code"
            @cancelled="emit('cancelled', reservation)"
          />
          <button
            v-if="reservation.payment_model === 'direct_owner_v1'"
            class="btn btn-ghost"
            type="button"
            data-testid="reservation-detail-pay"
            @click="emit('pay', reservation)"
          >
            {{ t('reservations.detail.reopenPayment') }}
          </button>
        </template>
        <RouterLink
          v-if="canOfferTransfer(reservation)"
          :to="{ name: 'resale-new', query: { reservation: reservation.id } }"
          class="btn btn-ghost"
          data-testid="reservation-detail-resale"
        >
          {{ t('booking.resaleBtn') }}
        </RouterLink>
        <button class="btn btn-primary" type="button" @click="emit('close')">
          {{ t('reservations.modal.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  color: var(--ink);
  border-radius: 20px;
  width: 100%;
  max-width: 520px;
  border: 1px solid var(--outline);
  box-shadow: 0 20px 48px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  border-bottom: 1px solid var(--border);
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 24px;
  color: var(--primary, #6b2d3f);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.modal-subtitle {
  font-size: 11px;
  color: var(--muted);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
}

.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 70vh;
  overflow-y: auto;
}

.summary-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: var(--surface-subtle);
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--border);
}

.info-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 11px;
  color: var(--muted);
  text-transform: uppercase;
}

.info-value {
  font-size: 14px;
}

.info-sub {
  font-size: 11.5px;
  color: var(--muted);
}

.status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  background: var(--border);
  color: var(--ink);
}

.status-badge.held {
  background: var(--warning-bg, rgba(239, 108, 0, 0.15));
  color: var(--warning-text, #ef6c00);
}

.status-badge.confirmed {
  background: var(--success-bg, rgba(46, 125, 50, 0.15));
  color: var(--success-text, #2e7d32);
}

.section-heading {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 800;
}

.slots-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.slot-table-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  font-size: 12.5px;
}

.slot-info-col {
  display: flex;
  align-items: center;
  gap: 6px;
}

.slot-date-text {
  font-size: 11px;
  color: var(--muted);
}

.total-breakdown-card {
  padding: 14px 18px;
  background: var(--surface-subtle);
  border-radius: 12px;
  border: 1px solid var(--border);
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.total-price {
  font-size: 18px;
  color: var(--primary, #6b2d3f);
}

.modal-footer {
  padding: 14px 24px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
