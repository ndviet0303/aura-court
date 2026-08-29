<script setup lang="ts">
import { ref } from 'vue'
import type { PosShift } from '../types/pos'

const props = defineProps<{
  show: boolean
  activeShift: PosShift | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'openShift', openingCash: number): void
  (e: 'closeShift', shiftId: number, declaredCash: number, note: string): void
}>()

const openingCash = ref(1000000)
const declaredCash = ref(0)
const shiftNote = ref('')

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

function handleOpen() {
  emit('openShift', openingCash.value)
}

function handleClose() {
  if (props.activeShift) {
    emit('closeShift', props.activeShift.id, declaredCash.value, shiftNote.value)
  }
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">
          💼 Quản Lý Ca Thu Ngân
        </h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- If Shift is Currently Open -->
        <div v-if="activeShift" class="shift-active-box">
          <div class="shift-status-badge">
            <span class="status-dot"></span> Ca đang hoạt động (#{{ activeShift.id }})
          </div>

          <div class="shift-stats-grid">
            <div class="stat-card">
              <span class="stat-label">Thời gian mở ca</span>
              <strong class="stat-val">{{ activeShift.opened_at }}</strong>
            </div>
            <div class="stat-card">
              <span class="stat-label">Tiền đầu ca</span>
              <strong class="stat-val">{{ formatMoney(activeShift.initial_cash || activeShift.opening_cash || 0) }}</strong>
            </div>
            <div class="stat-card">
              <span class="stat-label">Doanh thu tiền mặt</span>
              <strong class="stat-val text-green">{{ formatMoney(activeShift.cash_sales || 0) }}</strong>
            </div>
            <div class="stat-card">
              <span class="stat-label">Doanh thu QR / Ví</span>
              <strong class="stat-val text-blue">{{ formatMoney((activeShift.qr_sales || 0) + (activeShift.wallet_sales || 0)) }}</strong>
            </div>
          </div>

          <div class="close-shift-form">
            <h4 class="form-section-title">🔒 Kiểm đếm & Kết Ca</h4>
            
            <div class="form-group">
              <label class="form-label">
                Tiền mặt thực tế trong két (VND):
              </label>
              <input
                v-model.number="declaredCash"
                type="number"
                step="1000"
                class="form-input"
                placeholder="Nhập tổng tiền mặt đếm được..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Ghi chú kết ca:</label>
              <textarea
                v-model="shiftNote"
                rows="2"
                class="form-textarea"
                placeholder="Lý do chênh lệch (nếu có)..."
              ></textarea>
            </div>
          </div>
        </div>

        <!-- If No Shift is Open -->
        <div v-else class="shift-open-box">
          <div class="no-shift-notice">
            <span class="notice-icon">⚠️</span>
            <div>
              <strong>Chưa có ca làm việc nào được mở</strong>
              <p>Vui lòng nhập số tiền mặt đầu ca để bắt đầu ghi nhận doanh thu.</p>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Tiền mặt ban đầu trong két:</label>
            <input
              v-model.number="openingCash"
              type="number"
              step="50000"
              class="form-input"
            />
            <span class="form-hint">Mặc định: 1.000.000 ₫ để thối tiền lẻ</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
        <button
          v-if="activeShift"
          class="btn-danger"
          :disabled="loading"
          @click="handleClose"
        >
          <span v-if="loading">Đang kết ca...</span>
          <span v-else>Kết Ca Làm Việc</span>
        </button>
        <button
          v-else
          class="btn-primary"
          :disabled="loading"
          @click="handleOpen"
        >
          <span v-if="loading">Đang mở ca...</span>
          <span v-else>Mở Ca Thu Ngân</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shift-active-box,
.shift-open-box {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.shift-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 8px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  font-weight: 700;
  font-size: 13px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
}

.shift-stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.stat-label {
  font-size: 11px;
  color: var(--muted);
}

.stat-val {
  font-size: 14px;
  color: var(--ink);
}

.text-green {
  color: #10b981;
}

.text-blue {
  color: #3b82f6;
}

.form-section-title {
  margin: 16px 0 10px;
  font-size: 14px;
  font-weight: 700;
}

.no-shift-notice {
  display: flex;
  gap: 12px;
  padding: 14px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.3);
  color: var(--ink);
  font-size: 13px;
}

.notice-icon {
  font-size: 24px;
}

.btn-cancel {
  padding: 10px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 10px 20px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.btn-danger {
  padding: 10px 20px;
  border-radius: 10px;
  border: 0;
  background: #ef4444;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
