<script setup lang="ts">
import type { VenueWallet } from '../composables/useWallet'

defineProps<{
  show: boolean
  wallet: VenueWallet | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && wallet" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <h3 class="modal-title">📱 Mã Thành Viên: {{ wallet.venue_name }}</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body qr-modal-body">
        <p class="qr-sub">Đưa mã QR này cho nhân viên quầy POS để thanh toán tiền nước hoặc xác nhận check-in sân:</p>

        <div class="qr-card-box">
          <img
            :src="`https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=AURA-MEMBER-${wallet.id}`"
            alt="Member QR"
          />
        </div>

        <div class="wallet-balance-pill">
          <span>Số dư khả dụng:</span>
          <strong class="text-pink font-mono">{{ formatMoney(wallet.balance) }}</strong>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.qr-modal-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  text-align: center;
}

.qr-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.qr-card-box {
  width: 200px;
  height: 200px;
  padding: 10px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: #fff;
}

.qr-card-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.wallet-balance-pill {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 13px;
}

.text-pink { color: var(--pink-dark, #db2777); font-size: 15px; }

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
