<script setup lang="ts">
import type { Booking } from '../../types/booking'

defineProps<{
  show: boolean
  booking: Booking | null
  reason: string
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:reason', val: string): void
  (e: 'confirm'): void
}>()
</script>

<template>
  <div v-if="show && booking" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined header-icon-danger">warning</span>
          <h3 class="modal-title">Xác Nhận Huỷ Lịch Đặt Sân</h3>
        </div>
        <button class="modal-close-btn" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <p class="confirm-text">
          Bạn có chắc chắn muốn huỷ đơn đặt sân <strong>#{{ booking.code }}</strong> tại
          <strong>{{ booking.court?.name }}</strong>?
        </p>

        <div class="warning-alert-box">
          <span class="material-symbols-outlined alert-icon">info</span>
          <span>Khung giờ đã đặt sẽ được mở lại cho người chơi khác. Theo chính sách CLB, hoàn tiền (nếu có) sẽ được xử lý trong 24h.</span>
        </div>

        <div class="form-group">
          <label class="form-label">Lý do huỷ đơn (không bắt buộc):</label>
          <input
            :value="reason"
            type="text"
            class="form-input"
            placeholder="Bận việc đột xuất, đổi giờ chơi..."
            @input="emit('update:reason', ($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-back" type="button" :disabled="loading" @click="emit('close')">
          Giữ Lại Đơn
        </button>
        <button class="btn-confirm-cancel" type="button" :disabled="loading" @click="emit('confirm')">
          <span v-if="loading" class="material-symbols-outlined spin-icon">progress_activity</span>
          <span v-else>Xác Nhận Huỷ</span>
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
  max-width: 440px;
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

.header-icon-danger {
  font-size: 22px;
  color: var(--error-text, #c62828);
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

.modal-body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.confirm-text {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.5;
  color: var(--ink);
}

.warning-alert-box {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--warning-bg, rgba(239, 108, 0, 0.1));
  color: var(--warning-text, #ef6c00);
  font-size: 12px;
  line-height: 1.4;
}

.alert-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.form-input {
  width: 100%;
  padding: 9px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 13px;
  outline: none;
}

.form-input:focus {
  border-color: var(--primary, #6B2D3F);
}

.modal-footer {
  padding: 14px 22px;
  border-top: 1px solid var(--outline);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-back {
  padding: 9px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-confirm-cancel {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 20px;
  border-radius: 10px;
  border: 0;
  background: var(--error-text, #c62828);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-confirm-cancel:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spin-icon {
  animation: spin 1s linear infinite;
  font-size: 16px;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
