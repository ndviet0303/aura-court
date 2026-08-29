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
      <h3 class="title">📋 Tóm Tắt Đặt Sân</h3>
      <span class="court-sub">{{ court.name }}</span>
      <span class="venue-sub">📍 {{ court.venue?.name }}</span>
      <span class="date-sub font-mono">📅 Ngày chơi: {{ date }}</span>
    </div>

    <!-- Slots selected list -->
    <div class="selected-slots-list">
      <div v-if="selectedSlots.length === 0" class="no-slots-hint">
        Vui lòng bấm chọn một hoặc nhiều khung giờ trên bảng lịch.
      </div>
      <div v-else class="slots-chips">
        <div v-for="s in selectedSlots" :key="s.startsAt" class="slot-chip-item">
          <span class="font-mono">⏰ {{ s.startsAt.slice(11, 16) }} - {{ s.endsAt.slice(11, 16) }}</span>
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
        ⚡ Xác Nhận Giữ Chỗ & Thanh Toán
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
  gap: 4px;
}

.title { margin: 0; font-size: 16px; font-weight: 700; }
.court-sub { font-size: 14px; font-weight: 600; color: var(--pink-dark, #db2777); }
.venue-sub, .date-sub { font-size: 12px; color: var(--muted); }

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
  font-size: 12px;
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

.total-num { font-size: 18px; }
.text-pink { color: var(--pink-dark, #db2777); }

.btn-checkout {
  padding: 14px;
  border-radius: 12px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 800;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.25);
}

.btn-checkout:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}
</style>
