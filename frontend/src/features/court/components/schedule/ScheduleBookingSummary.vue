<script setup lang="ts">
import type { Court, AvailabilitySlot } from '../../types/court'

defineProps<{
  court: Court
  date: string
  selectedSlots: AvailabilitySlot[]
  totalPrice: number
}>()

const emit = defineEmits<{
  (e: 'checkout'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="schedule-summary-card">
    <div class="summary-hdr">
      <h3 class="title">
        <span class="material-symbols-outlined title-icon">receipt_long</span>
        <span>Tóm Tắt Đặt Sân</span>
      </h3>
      <span class="court-sub">{{ court.name }}</span>
      <span class="venue-sub">
        <span class="material-symbols-outlined icon-14">location_on</span>
        <span>{{ court.venue?.name }}</span>
      </span>
      <span class="date-sub font-mono">
        <span class="material-symbols-outlined icon-14">event</span>
        <span>Ngày chơi: {{ date }}</span>
      </span>
    </div>

    <!-- Slots selected list -->
    <div class="selected-slots-list">
      <div v-if="selectedSlots.length === 0" class="no-slots-hint">
        Vui lòng bấm chọn một hoặc nhiều khung giờ trên bảng lịch.
      </div>
      <div v-else class="slots-chips">
        <div v-for="s in selectedSlots" :key="s.startsAt" class="slot-chip-item">
          <span class="font-mono slot-time-badge">
            <span class="material-symbols-outlined icon-14">schedule</span>
            <span>{{ s.startsAt.slice(11, 16) }} - {{ s.endsAt.slice(11, 16) }}</span>
          </span>
          <strong class="font-mono">{{ formatMoney(s.price) }}</strong>
        </div>
      </div>
    </div>

    <div class="summary-footer">
      <div class="total-row">
        <span>Tổng cộng ({{ selectedSlots.length }} khung giờ):</span>
        <strong class="total-num text-pink font-mono">{{ formatMoney(totalPrice) }}</strong>
      </div>

      <button
        class="btn-checkout"
        :disabled="selectedSlots.length === 0"
        @click="emit('checkout')"
      >
        <span class="material-symbols-outlined btn-icon">bolt</span>
        <span>Xác Nhận Giữ Chỗ & Thanh Toán</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.schedule-summary-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.summary-hdr {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.title-icon {
  font-size: 18px;
  color: var(--primary);
}

.court-sub {
  font-size: 14px;
  font-weight: 600;
  color: var(--primary);
}

.venue-sub,
.date-sub {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.icon-14 {
  font-size: 14px;
  color: var(--muted);
}

.selected-slots-list {
  padding: 12px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  min-height: 80px;
}

.no-slots-hint {
  font-size: 12px;
  color: var(--muted);
  text-align: center;
  padding: 16px 8px;
}

.slots-chips {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.slot-chip-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}

.slot-time-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.summary-footer {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--outline);
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 13px;
}

.total-num { font-size: 18px; font-weight: 800; }
.text-pink { color: var(--primary); }

.btn-checkout {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 14px;
  border-radius: 12px;
  border: 0;
  background: var(--primary);
  color: var(--on-primary);
  font-weight: 800;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.22);
  transition: all 0.15s ease;
}

.btn-icon {
  font-size: 18px;
}

.btn-checkout:hover:not(:disabled) {
  background: var(--primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.32);
}

.btn-checkout:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

:global(html.dark) .btn-checkout {
  box-shadow: 0 4px 16px rgba(229, 142, 159, 0.25);
}

:global(html.dark) .btn-checkout:hover:not(:disabled) {
  box-shadow: 0 6px 20px rgba(229, 142, 159, 0.35);
}
</style>
