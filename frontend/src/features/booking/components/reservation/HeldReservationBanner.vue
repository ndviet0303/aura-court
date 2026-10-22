<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Reservation } from '../../types/reservation'
import { displayCode } from '../../utils/reservation'
import CancelPaymentButton from './CancelPaymentButton.vue'

const { t } = useI18n()
defineProps<{
  reservation: Reservation
  confirmingCode: string | null
  money: (value: number) => string
  formatDate: (value: string) => string
  formatTimeSlot: (startsAt: string, endsAt?: string) => string
}>()

const emit = defineEmits<{
  (e: 'confirm', reservation: Reservation): void
  (e: 'pay', reservation: Reservation): void
  (e: 'details', reservation: Reservation): void
  (e: 'cancelled', reservation: Reservation): void
}>()
</script>

<template>
  <div class="held-reservation-banner">
    <div class="banner-badge-col">
      <div class="pulse-indicator">
        <span class="pulse-ring"></span>
        <span class="material-symbols-outlined held-icon">hourglass_top</span>
      </div>
    </div>

    <div class="banner-info-col">
      <div class="held-eyebrow">
        <span class="badge-held">{{ t('reservations.heldBanner.badge') }}</span>
        <span class="code font-mono">{{
          t('reservations.heldBanner.orderCode', { code: displayCode(reservation) })
        }}</span>
      </div>
      <h3 class="held-title">
        {{ reservation.court?.code || 'Aura Court' }} ·
        {{ reservation.court?.venue?.name || 'Aura Sports Club' }}
      </h3>
      <div class="held-meta">
        <span
          v-if="reservation.slots && reservation.slots.length && reservation.slots[0]"
          class="meta-item"
        >
          <span class="material-symbols-outlined meta-icon">schedule</span>
          <span class="font-mono">
            {{ formatTimeSlot(reservation.slots[0].starts_at, reservation.slots[0].ends_at) }}
          </span>
          <span class="meta-date"> ({{ formatDate(reservation.slots[0].starts_at) }}) </span>
        </span>
        <span class="meta-item price">
          <span class="material-symbols-outlined meta-icon">payments</span>
          <strong class="font-mono">{{ money(reservation.total_amount) }}</strong>
        </span>
      </div>
    </div>

    <div class="banner-actions-col">
      <button
        v-if="reservation.payment_model === 'direct_owner_v1'"
        class="btn btn-primary btn-confirm-held"
        @click="emit('pay', reservation)"
      >
        <span class="material-symbols-outlined">qr_code_2</span>
        <span>{{ t('reservations.payment.payByBank') }}</span>
      </button>
      <button
        v-else
        class="btn btn-primary btn-confirm-held"
        :disabled="confirmingCode === reservation.code"
        @click="emit('confirm', reservation)"
      >
        <span
          class="material-symbols-outlined"
          :class="{ spinning: confirmingCode === reservation.code }"
        >
          {{ confirmingCode === reservation.code ? 'refresh' : 'check_circle' }}
        </span>
        <span>{{
          confirmingCode === reservation.code
            ? t('reservations.heldBanner.confirming')
            : t('reservations.heldBanner.confirmBtn')
        }}</span>
      </button>
      <CancelPaymentButton :code="reservation.code" @cancelled="emit('cancelled', reservation)" />
    </div>
  </div>
</template>

<style scoped>
.held-reservation-banner {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 20px 24px;
  background: linear-gradient(90deg, rgba(239, 108, 0, 0.08) 0%, rgba(201, 161, 90, 0.08) 100%);
  border: 1.5px solid rgba(239, 108, 0, 0.35);
  border-radius: 18px;
  box-shadow: 0 4px 18px rgba(239, 108, 0, 0.06);
}

.banner-badge-col {
  flex-shrink: 0;
}

.pulse-indicator {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(239, 108, 0, 0.15);
  color: #ef6c00;
  display: flex;
  align-items: center;
  justify-content: center;
}

.held-icon {
  font-size: 22px;
}

.banner-info-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.held-eyebrow {
  display: flex;
  align-items: center;
  gap: 8px;
}

.badge-held {
  font-size: 11px;
  font-weight: 800;
  color: #ef6c00;
  background: rgba(239, 108, 0, 0.15);
  padding: 2px 8px;
  border-radius: 6px;
}

.code {
  font-size: 11.5px;
  color: var(--muted);
}

.held-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.held-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px;
  font-size: 13px;
  color: var(--muted);
  margin-top: 2px;
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.meta-icon {
  font-size: 16px;
  color: var(--muted);
}

.meta-item.price strong {
  color: var(--primary, #6b2d3f);
  font-size: 14.5px;
}

.banner-actions-col {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.btn-confirm-held {
  padding: 9px 20px;
  font-size: 13.5px;
}

@media (max-width: 768px) {
  .held-reservation-banner {
    flex-direction: column;
    align-items: flex-start;
  }
  .banner-actions-col {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
