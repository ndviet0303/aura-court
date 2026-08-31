<script setup lang="ts">
import type { V2Reservation } from '../../types/v2Reservation'

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
  (e: 'qr', reservation: V2Reservation): void
  (e: 'details', reservation: V2Reservation): void
}>()
</script>

<template>
  <div class="v2-reservation-card" :class="`status-${reservation.status}`">
    <!-- Card Header -->
    <div class="card-header">
      <div class="code-and-venue">
        <span class="booking-code font-mono">#{{ reservation.code }}</span>
        <span class="court-venue-name">
          {{ reservation.court?.venue?.name || 'Aura Badminton & Sports Club' }}
        </span>
      </div>

      <!-- Status Badge -->
      <div class="status-pill-badge" :class="reservation.status">
        <span v-if="reservation.status === 'held'" class="pulse-indicator-small"></span>
        <span v-else-if="reservation.status === 'confirmed'" class="material-symbols-outlined status-icon">check_circle</span>
        <span v-else class="material-symbols-outlined status-icon">cancel</span>
        <span>
          {{
            reservation.status === 'held'
              ? 'Đang giữ chỗ'
              : reservation.status === 'confirmed'
              ? 'Đã xác nhận'
              : reservation.status === 'cancelled'
              ? 'Đã hủy'
              : reservation.status
          }}
        </span>
      </div>
    </div>

    <!-- Court Details Row -->
    <div class="card-body">
      <div class="court-main-info">
        <div class="court-icon-box">
          <span class="material-symbols-outlined court-icon">sports_tennis</span>
        </div>
        <div class="court-text-col">
          <h4 class="court-title">{{ reservation.court?.name || 'Sân Aura Court' }}</h4>
          <p class="court-address">{{ reservation.court?.venue?.address || 'Khu phức hợp thể thao Aura Court' }}</p>
        </div>
      </div>

      <!-- Schedule Slots -->
      <div class="slots-container">
        <div
          v-for="s in reservation.slots"
          :key="s.starts_at"
          class="slot-badge-item"
        >
          <span class="material-symbols-outlined slot-icon">schedule</span>
          <span class="slot-time font-mono">{{ formatTimeSlot(s.starts_at, s.ends_at) }}</span>
          <span class="slot-date">· {{ formatDate(s.starts_at) }}</span>
        </div>
      </div>
    </div>

    <!-- Card Footer -->
    <div class="card-footer">
      <div class="pricing-col">
        <span class="pricing-label">Tổng thanh toán</span>
        <strong class="pricing-value font-mono">{{ money(reservation.total_amount) }}</strong>
      </div>

      <div class="actions-col">
        <!-- Held Actions -->
        <template v-if="reservation.status === 'held'">
          <button
            class="btn btn-outline-cancel"
            :disabled="cancellingCode === reservation.code || confirmingCode === reservation.code"
            @click="emit('cancel', reservation)"
          >
            <span class="material-symbols-outlined">close</span>
            <span>Hủy</span>
          </button>
          <button
            class="btn btn-primary btn-confirm"
            :disabled="confirmingCode === reservation.code || cancellingCode === reservation.code"
            @click="emit('confirm', reservation)"
          >
            <span class="material-symbols-outlined" :class="{ spinning: confirmingCode === reservation.code }">
              {{ confirmingCode === reservation.code ? 'refresh' : 'check' }}
            </span>
            <span>{{ confirmingCode === reservation.code ? 'Đang xử lý...' : 'Xác Nhận Đặt Sân' }}</span>
          </button>
        </template>

        <!-- Confirmed Actions -->
        <template v-else-if="reservation.status === 'confirmed'">
          <button
            class="btn btn-outline-details"
            @click="emit('details', reservation)"
          >
            <span class="material-symbols-outlined">receipt</span>
            <span>Báo giá</span>
          </button>
          <button
            class="btn btn-qr-pass"
            @click="emit('qr', reservation)"
          >
            <span class="material-symbols-outlined">qr_code_2</span>
            <span>Mã Vào Sân</span>
          </button>
        </template>

        <!-- Other Actions -->
        <template v-else>
          <button
            class="btn btn-outline-details"
            @click="emit('details', reservation)"
          >
            <span class="material-symbols-outlined">info</span>
            <span>Chi Tiết</span>
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.v2-reservation-card {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 18px;
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.03);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.v2-reservation-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(107, 45, 63, 0.08);
}

.v2-reservation-card.status-held {
  border-left: 4px solid #ef6c00;
}

.v2-reservation-card.status-confirmed {
  border-left: 4px solid #2e7d32;
}

.v2-reservation-card.status-cancelled {
  border-left: 4px solid var(--muted);
  opacity: 0.85;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--surface-subtle);
}

.code-and-venue {
  display: flex;
  align-items: center;
  gap: 8px;
}

.booking-code {
  font-size: 12.5px;
  font-weight: 800;
  color: var(--primary, #6B2D3F);
}

.court-venue-name {
  font-size: 12px;
  color: var(--muted);
}

.status-pill-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11.5px;
  font-weight: 800;
}

.status-pill-badge.held {
  background: var(--warning-bg, rgba(239, 108, 0, 0.15));
  color: var(--warning-text, #ef6c00);
}

.pulse-indicator-small {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ef6c00;
  animation: pulse-ring 1.8s infinite;
}

.status-pill-badge.confirmed {
  background: var(--success-bg, rgba(46, 125, 50, 0.15));
  color: var(--success-text, #2e7d32);
}

.status-pill-badge.cancelled {
  background: var(--error-bg, rgba(198, 40, 40, 0.12));
  color: var(--error-text, #c62828);
}

.status-icon {
  font-size: 14px;
}

.card-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.court-main-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.court-icon-box {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary, #6B2D3F);
  flex-shrink: 0;
}

.court-icon {
  font-size: 20px;
}

.court-title {
  margin: 0;
  font-size: 15.5px;
  font-weight: 800;
  color: var(--ink);
}

.court-address {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.slots-container {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.slot-badge-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 8px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  font-size: 12px;
  color: var(--ink);
}

.slot-icon {
  font-size: 14px;
  color: var(--muted);
}

.slot-time {
  font-weight: 700;
}

.slot-date {
  color: var(--muted);
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  border-top: 1px solid var(--border);
  background: var(--surface-subtle);
}

.pricing-col {
  display: flex;
  flex-direction: column;
}

.pricing-label {
  font-size: 11px;
  color: var(--muted);
  text-transform: uppercase;
}

.pricing-value {
  font-size: 16px;
  font-weight: 800;
  color: var(--primary, #6B2D3F);
}

.actions-col {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-confirm {
  padding: 8px 16px;
  font-size: 13px;
}

.btn-qr-pass {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 9999px;
  background: var(--accent, #C9A15A);
  color: #1c1618;
  font-size: 13px;
  font-weight: 800;
  border: none;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(201, 161, 90, 0.25);
  transition: all 0.2s ease;
}

.btn-qr-pass:hover {
  filter: brightness(1.08);
}

.btn-outline-details, .btn-outline-cancel {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 7px 12px;
  border-radius: 9999px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-outline-details:hover {
  background: var(--surface);
  border-color: var(--ink);
}

.btn-outline-cancel:hover {
  background: var(--error-bg, rgba(198, 40, 40, 0.1));
  color: var(--error-text, #c62828);
  border-color: rgba(198, 40, 40, 0.3);
}

@media (max-width: 640px) {
  .card-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  .actions-col {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
