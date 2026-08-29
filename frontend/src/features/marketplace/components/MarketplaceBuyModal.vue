<script setup lang="ts">
import type { ResaleListing } from '../types/marketplace'

defineProps<{
  show: boolean
  listing: ResaleListing | null
  paymentMethod: 'wallet' | 'transfer'
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:paymentMethod', method: 'wallet' | 'transfer'): void
  (e: 'confirm'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && listing" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">⚡ Xác Nhận Mua Lại Lịch Sân (Escrow)</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="summary-target">
          <strong class="target-court">{{ listing.courtName }}</strong>
          <span class="target-venue">📍 {{ listing.venueName }}</span>
          <span class="target-time">⏰ {{ listing.dateStr }} ({{ listing.timeSlot }})</span>
        </div>

        <div class="price-breakdown">
          <div class="p-row">
            <span>Giá pass thỏa thuận:</span>
            <strong class="text-pink">{{ formatMoney(listing.resalePrice) }}</strong>
          </div>
          <div class="p-row">
            <span>Phí bảo đảm Escrow P2P:</span>
            <strong class="text-green">MIỄN PHÍ (0 ₫)</strong>
          </div>
        </div>

        <div class="escrow-notice-box">
          <span class="shield-ico">🛡️</span>
          <p class="notice-txt">
            <strong>Bảo vệ người mua 100%:</strong> Tiền sẽ được giữ an toàn trên hệ thống Aura Escrow và chỉ chuyển cho người bán sau khi bạn check-in sân thành công.
          </p>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" :disabled="processing" @click="emit('confirm')">
          <span v-if="processing">Đang xử lý giao dịch...</span>
          <span v-else>Thanh Toán {{ formatMoney(listing.resalePrice) }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.summary-target {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 16px;
}

.target-court {
  font-size: 15px;
}

.target-venue, .target-time {
  font-size: 12px;
  color: var(--muted);
}

.price-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
  margin-bottom: 14px;
}

.p-row {
  display: flex;
  justify-content: space-between;
}

.text-pink { color: var(--pink-dark, #db2777); font-size: 15px; }
.text-green { color: #10b981; }

.escrow-notice-box {
  display: flex;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.05);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.shield-ico {
  font-size: 24px;
}

.notice-txt {
  margin: 0;
  font-size: 11px;
  color: var(--muted);
  line-height: 1.5;
}

.notice-txt strong {
  color: #10b981;
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
