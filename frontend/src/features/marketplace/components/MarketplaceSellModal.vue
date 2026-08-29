<script setup lang="ts">
import type { UserBookingSlot } from '../types/marketplace'

defineProps<{
  show: boolean
  slot: UserBookingSlot | null
  askingPrice: number
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:askingPrice', price: number): void
  (e: 'confirm'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && slot" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">🏷️ Niêm Yết Pass Lại Sân (Resell Slot)</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="slot-to-sell-info">
          <strong class="slot-court">{{ slot.courtName }}</strong>
          <span class="slot-venue">📍 {{ slot.venueName }}</span>
          <span class="slot-time font-mono">📅 {{ slot.dateStr }} · {{ slot.timeSlot }}</span>
          <span class="slot-orig">Giá đã thanh toán: <strong>{{ formatMoney(slot.originalPrice) }}</strong></span>
        </div>

        <div class="form-group">
          <label class="form-label">Đặt giá muốn pass lại (₫):</label>
          <input
            :value="askingPrice"
            type="number"
            min="10000"
            :max="slot.originalPrice"
            step="10000"
            class="form-input"
            @input="emit('update:askingPrice', Number(($event.target as HTMLInputElement).value))"
          />
          <span class="hint">Gợi ý: Đặt thấp hơn 15-30% để thanh lý nhanh nhất.</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" :disabled="processing || askingPrice <= 0" @click="emit('confirm')">
          <span v-if="processing">Đang niêm yết...</span>
          <span v-else>Đăng Lên Chợ Ngay</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.slot-to-sell-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 16px;
  font-size: 13px;
}

.slot-court {
  font-size: 15px;
}

.slot-venue, .slot-time, .slot-orig {
  color: var(--muted);
}

.hint {
  font-size: 11px;
  color: var(--muted);
  margin-top: 4px;
  display: block;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
