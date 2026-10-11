<script setup lang="ts">
import type { Booking } from '../../types/booking'

defineProps<{
  show: boolean
  booking: Booking | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <div v-if="show && booking" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined header-icon">qr_code_2</span>
          <h3 class="modal-title">Thẻ Check-In Sân Đấu</h3>
        </div>
        <button class="modal-close-btn" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body qr-body">
        <div class="court-summary-box">
          <span class="court-title">{{ booking.court?.name || 'Sân Aura Court' }}</span>
          <span class="venue-subtitle">
            <span class="material-symbols-outlined loc-icon">location_on</span>
            {{ booking.court?.venue?.name || 'Aura Badminton Center' }}
          </span>
          <span class="code-pill font-mono">MÃ ĐƠN: #{{ booking.code }}</span>
        </div>

        <div class="qr-container">
          <img
            :src="`https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=AURA_BOOKING_${booking.code}&margin=10`"
            :alt="`QR Check-in ${booking.code}`"
            class="qr-image"
          />
          <span class="qr-caption">Xuất trình mã này tại quầy lễ tân hoặc máy quét cửa</span>
        </div>

        <div class="booking-schedule-brief">
          <div v-for="s in booking.slots" :key="s.starts_at" class="schedule-line">
            <span class="material-symbols-outlined sched-icon">schedule</span>
            <span class="font-mono"
              >{{ s.starts_at.slice(11, 16) }} - {{ s.ends_at.slice(11, 16) }}</span
            >
            <span class="sched-date">({{ s.starts_at.slice(0, 10) }})</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-done" type="button" @click="emit('close')">Đóng</button>
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
  background: rgba(0, 0, 0, 0.6);
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
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-icon {
  font-size: 22px;
  color: var(--primary, #6b2d3f);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
}

.modal-close-btn:hover {
  color: var(--ink);
}

.qr-body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.court-summary-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-align: center;
}

.court-title {
  font-size: 15px;
  font-weight: 800;
}

.venue-subtitle {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.loc-icon {
  font-size: 14px;
}

.code-pill {
  font-size: 11px;
  font-weight: 800;
  color: var(--primary, #6b2d3f);
  background: var(--primary-container, rgba(107, 45, 63, 0.08));
  padding: 3px 8px;
  border-radius: 6px;
  margin-top: 4px;
}

.qr-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 16px;
  background: #ffffff;
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.qr-image {
  width: 190px;
  height: 190px;
  display: block;
}

.qr-caption {
  font-size: 11.5px;
  color: #666;
  text-align: center;
  max-width: 220px;
}

.booking-schedule-brief {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--border);
  padding: 10px 14px;
  border-radius: 10px;
  font-size: 12px;
}

.schedule-line {
  display: flex;
  align-items: center;
  gap: 6px;
}

.sched-icon {
  font-size: 14px;
  color: var(--muted);
}

.sched-date {
  color: var(--muted);
  font-size: 11.5px;
}

.modal-footer {
  padding: 14px 22px;
  border-top: 1px solid var(--outline);
  display: flex;
  justify-content: flex-end;
}

.btn-done {
  padding: 9px 22px;
  border-radius: 10px;
  border: 0;
  background: var(--primary, #6b2d3f);
  color: var(--on-primary, #fff);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
</style>
