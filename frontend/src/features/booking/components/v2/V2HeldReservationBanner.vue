<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { V2Reservation } from '../../types/v2Reservation'

const { t } = useI18n()
defineProps<{
  reservation: V2Reservation
  confirmingCode: string | null
  cancellingCode: string | null
  money: (value: number) => string
  formatDate: (value: string) => string
  formatTimeSlot: (startsAt: string, endsAt?: string) => string
}>()

const emit = defineEmits<{
  (e: 'confirm', reservation: V2Reservation): void
  (e: 'cancel', reservation: V2Reservation): void
  (e: 'details', reservation: V2Reservation): void
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
        <span class="badge-held">{{ t('v2Reservations.heldBanner.badge') }}</span>
        <span class="code font-mono">{{ t('v2Reservations.heldBanner.orderCode', { code: reservation.code }) }}</span>
      </div>
      <h3 class="held-title">
        {{ reservation.court?.name || 'Aura Court' }} · {{ reservation.court?.venue?.name || 'Aura Sports Club' }}
      </h3>
      <div class="held-meta">
        <span v-if="reservation.slots && reservation.slots.length && reservation.slots[0]" class="meta-item">
          <span class="material-symbols-outlined meta-icon">schedule</span>
          <span class="font-mono">
            {{ formatTimeSlot(reservation.slots[0].starts_at, reservation.slots[0].ends_at) }}
          </span>
          <span class="meta-date">
            ({{ formatDate(reservation.slots[0].starts_at) }})
          </span>
        </span>
        <span class="meta-item price">
          <span class="material-symbols-outlined meta-icon">payments</span>
          <strong class="font-mono">{{ money(reservation.total_amount) }}</strong>
        </span>
      </div>
    </div>

    <div class="banner-actions-col">
      <button
        class="btn btn-primary btn-confirm-held"
        :disabled="confirmingCode === reservation.code || cancellingCode === reservation.code"
        @click="emit('confirm', reservation)"
      >
        <span class="material-symbols-outlined" :class="{ spinning: confirmingCode === reservation.code }">
          {{ confirmingCode === reservation.code ? 'refresh' : 'check_circle' }}
        </span>
        <span>{{ confirmingCode === reservation.code ? t('v2Reservations.heldBanner.confirming') : t('v2Reservations.heldBanner.confirmBtn') }}</span>
      </button>

      <button
        class="btn btn-cancel-held"
        :disabled="confirmingCode === reservation.code || cancellingCode === reservation.code"
        @click="emit('cancel', reservation)"
      >
        <span class="material-symbols-outlined">close</span>
        <span>{{ t('v2Reservations.heldBanner.cancelBtn') }}</span>
      </button>
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
  color: var(--primary, #6B2D3F);
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

.btn-cancel-held {
  padding: 9px 14px;
  border-radius: 9999px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s ease;
}

.btn-cancel-held:hover:not(:disabled) {
  background: var(--error-bg, rgba(198, 40, 40, 0.1));
  color: var(--error-text, #c62828);
  border-color: rgba(198, 40, 40, 0.3);
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
