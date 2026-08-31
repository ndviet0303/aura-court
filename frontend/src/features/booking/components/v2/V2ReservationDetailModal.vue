<script setup lang="ts">
import type { V2Reservation } from '../../types/v2Reservation'

defineProps<{
  show: boolean
  reservation: V2Reservation | null
  money: (value: number) => string
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
          <span class="material-symbols-outlined header-icon">receipt_long</span>
          <div>
            <h3 class="modal-title">Chi Tiết Báo Giá & Lịch Đặt V2</h3>
            <span class="modal-subtitle font-mono">MÃ ĐƠN: #{{ reservation.code }}</span>
          </div>
        </div>
        <button class="modal-close-btn" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- Status & Court Summary -->
        <div class="summary-section">
          <div class="info-group">
            <span class="info-label">Cụm sân thi đấu</span>
            <strong class="info-value">{{ reservation.court?.name || 'Sân Aura Court' }}</strong>
            <span class="info-sub">{{ reservation.court?.venue?.name || 'Aura Court' }} · {{ reservation.court?.venue?.address || '' }}</span>
          </div>
          <div class="info-group">
            <span class="info-label">Trạng thái đơn</span>
            <span class="status-badge" :class="reservation.status">
              {{ reservation.status }}
            </span>
          </div>
        </div>

        <!-- Quote Snapshot Details -->
        <div v-if="reservation.quote" class="quote-snapshot-box">
          <div class="snapshot-header">
            <span class="material-symbols-outlined snap-icon">lock</span>
            <span>Bảo lưu giá bất biến (Snapshot)</span>
          </div>
          <div class="snapshot-details">
            <div class="detail-line">
              <span class="detail-k">Token báo giá:</span>
              <span class="detail-v font-mono">#{{ reservation.quote.quote_token }}</span>
            </div>
            <div class="detail-line">
              <span class="detail-k">Thời gian tạo:</span>
              <span class="detail-v font-mono">{{ formatDate(reservation.created_at || '') }}</span>
            </div>
            <div class="detail-line">
              <span class="detail-k">Hết hạn giữ giá:</span>
              <span class="detail-v font-mono">{{ formatDate(reservation.quote.expires_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Slots Table -->
        <div class="slots-table-section">
          <h4 class="section-heading">Danh Sách Khung Giờ</h4>
          <div class="slots-list">
            <div
              v-for="(slot, idx) in reservation.slots"
              :key="idx"
              class="slot-table-row"
            >
              <div class="slot-info-col">
                <span class="material-symbols-outlined">schedule</span>
                <span class="font-mono">{{ formatTimeSlot(slot.starts_at, slot.ends_at) }}</span>
                <span class="slot-date-text">({{ formatDate(slot.starts_at) }})</span>
              </div>
              <div class="slot-price-col font-mono">
                {{ money(slot.unit_price) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Total Breakdown -->
        <div class="total-breakdown-card">
          <div class="total-row">
            <span>Tổng chi phí (Đã chốt cố định):</span>
            <strong class="total-price font-mono">{{ money(reservation.total_amount) }}</strong>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" type="button" @click="emit('close')">
          Đóng
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
  max-width: 520px;
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
  padding: 18px 24px;
  border-bottom: 1px solid var(--border);
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 24px;
  color: var(--primary, #6B2D3F);
}

.modal-title {
  margin: 0;
  font-size: 16px;
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
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 70vh;
  overflow-y: auto;
}

.summary-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: var(--surface-subtle);
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--border);
}

.info-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 11px;
  color: var(--muted);
  text-transform: uppercase;
}

.info-value {
  font-size: 14px;
}

.info-sub {
  font-size: 11.5px;
  color: var(--muted);
}

.status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  background: var(--border);
  color: var(--ink);
}

.status-badge.held {
  background: var(--warning-bg, rgba(239, 108, 0, 0.15));
  color: var(--warning-text, #ef6c00);
}

.status-badge.confirmed {
  background: var(--success-bg, rgba(46, 125, 50, 0.15));
  color: var(--success-text, #2e7d32);
}

.quote-snapshot-box {
  background: linear-gradient(135deg, rgba(201, 161, 90, 0.08) 0%, rgba(107, 45, 63, 0.05) 100%);
  border: 1px solid var(--accent, #C9A15A);
  border-radius: 12px;
  padding: 12px 16px;
}

.snapshot-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 800;
  color: var(--on-accent-container, #684F17);
  margin-bottom: 8px;
}

.snap-icon {
  font-size: 15px;
}

.snapshot-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 11.5px;
}

.detail-line {
  display: flex;
  justify-content: space-between;
}

.detail-k {
  color: var(--muted);
}

.detail-v {
  font-weight: 700;
  color: var(--ink);
}

.section-heading {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 800;
}

.slots-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.slot-table-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  font-size: 12.5px;
}

.slot-info-col {
  display: flex;
  align-items: center;
  gap: 6px;
}

.slot-date-text {
  font-size: 11px;
  color: var(--muted);
}

.total-breakdown-card {
  padding: 14px 18px;
  background: var(--surface-subtle);
  border-radius: 12px;
  border: 1px solid var(--border);
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.total-price {
  font-size: 18px;
  color: var(--primary, #6B2D3F);
}

.modal-footer {
  padding: 14px 24px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
}
</style>
