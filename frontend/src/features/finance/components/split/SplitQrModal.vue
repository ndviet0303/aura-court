<script setup lang="ts">
import type { SplitMember } from '../../composables/useGroupSplitBill'

const props = defineProps<{
  show: boolean
  member: SplitMember | null
  bankName: string
  accountNumber: string
  accountName: string
  bookingCode: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirmPaid', memberId: number): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && member" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <h3 class="modal-title">📱 Quét VietQR Thanh Toán Phần Tiền</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body qr-modal-body">
        <div class="member-target-info">
          <span>Thành viên: <strong>{{ member.name }}</strong></span>
          <strong class="target-amount text-pink">{{ formatMoney(member.amount) }}</strong>
        </div>

        <div class="qr-code-box">
          <img
            :src="`https://api.vietqr.io/image/970423-${accountNumber}-compact2.png?amount=${member.amount}&addInfo=AURA ${bookingCode} ${member.name}&accountName=${encodeURIComponent(accountName)}`"
            alt="VietQR Split Bill"
          />
        </div>

        <div class="bank-details-box">
          <div class="b-row">
            <span>Ngân hàng:</span>
            <strong>{{ bankName }}</strong>
          </div>
          <div class="b-row">
            <span>Số tài khoản:</span>
            <strong class="font-mono">{{ accountNumber }}</strong>
          </div>
          <div class="b-row">
            <span>Nội dung chuyển khoản:</span>
            <strong class="font-mono text-pink">AURA {{ bookingCode }} {{ member.name }}</strong>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
        <button class="btn-primary" @click="emit('confirmPaid', member.id); emit('close')">
          ✓ Đã Nhận Tiền
        </button>
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
}

.member-target-info {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.target-amount {
  font-size: 16px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.qr-code-box {
  width: 220px;
  height: 220px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
  background: #fff;
  padding: 10px;
}

.qr-code-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.bank-details-box {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
}

.b-row {
  display: flex;
  justify-content: space-between;
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
