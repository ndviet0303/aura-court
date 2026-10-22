<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Reservation } from '../../types/reservation'
import { displayCode } from '../../utils/reservation'
import { QrImage } from '@/shared/components'

const { t } = useI18n()

defineProps<{
  show: boolean
  reservation: Reservation | null
  formatDate: (value: string) => string
  formatTimeSlot: (startsAt: string, endsAt?: string) => string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <div v-if="show && reservation" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-row">
          <span class="material-symbols-outlined header-icon">qr_code_2</span>
          <div>
            <h3 class="modal-title">{{ t('reservations.modal.qrTitle') }}</h3>
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
        <div class="court-summary-badge">
          <span class="court-name">{{ reservation.court?.code || 'Aura Court' }}</span>
          <span class="venue-address">
            <span class="material-symbols-outlined loc-icon">location_on</span>
            {{ reservation.court?.venue?.name || 'Aura Court' }}
          </span>
        </div>

        <div class="qr-box">
          <QrImage
            :value="`AURA_BOOKING_${reservation.code}`"
            :alt="`Aura QR #${displayCode(reservation)}`"
            class="qr-image"
          />
          <span class="qr-instruction">
            {{ t('reservations.modal.qrInstruction') }}
          </span>
        </div>

        <div class="schedule-summary-box">
          <div v-for="(slot, idx) in reservation.slots" :key="idx" class="slot-line">
            <span class="material-symbols-outlined icon">schedule</span>
            <span class="font-mono font-bold">{{
              formatTimeSlot(slot.starts_at, slot.ends_at)
            }}</span>
            <span class="date-text">({{ formatDate(slot.starts_at) }})</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" type="button" @click="emit('close')">
          {{ t('reservations.modal.done') }}
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
  max-width: 420px;
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
  padding: 18px 22px;
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
  font-size: 15.5px;
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
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.court-summary-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  text-align: center;
}

.court-name {
  font-size: 16px;
  font-weight: 800;
}

.venue-address {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.loc-icon {
  font-size: 14px;
}

.qr-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 16px;
  background: #ffffff;
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.qr-image {
  width: 180px;
  height: 180px;
  display: block;
}

.qr-instruction {
  font-size: 11.5px;
  color: #666;
  text-align: center;
  max-width: 220px;
}

.schedule-summary-box {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  padding: 10px 14px;
  border-radius: 10px;
  font-size: 12.5px;
}

.slot-line {
  display: flex;
  align-items: center;
  gap: 6px;
}

.slot-line .icon {
  font-size: 15px;
  color: var(--muted);
}

.date-text {
  font-size: 11.5px;
  color: var(--muted);
}

.modal-footer {
  padding: 14px 22px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
}
</style>
