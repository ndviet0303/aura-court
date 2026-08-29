<script setup lang="ts">
import type { MonthlyInvoiceData } from '../../composables/usePaymentCycleDetail'

defineProps<{
  show: boolean
  invoice: MonthlyInvoiceData | null
  paying?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirmPay'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && invoice" class="modal-backdrop modal-payment" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">📱 Thanh Toán Hóa Đơn Định Kỳ</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body payment-modal-body">
        <div class="qr-box">
          <img
            :src="`https://api.vietqr.io/image/970423-83403032005-compact2.png?amount=${invoice.amount}&addInfo=AURA INV ${invoice.id}&accountName=AURA COURT`"
            alt="VietQR Monthly Invoice"
          />
        </div>

        <div class="invoice-summary-box">
          <div class="row">
            <span>Mã hóa đơn:</span>
            <strong class="font-mono">INV-{{ invoice.id }}</strong>
          </div>
          <div class="row">
            <span>Kỳ thanh toán:</span>
            <strong>{{ invoice.period_start.slice(0, 10) }} ➔ {{ invoice.period_end.slice(0, 10) }}</strong>
          </div>
          <div class="row">
            <span>Tổng tiền:</span>
            <strong class="text-pink">{{ formatMoney(invoice.amount) }}</strong>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button
          class="btn-primary btn-confirm-pay"
          :disabled="paying"
          @click="emit('confirmPay')"
        >
          <span v-if="paying">Đang xử lý...</span>
          <span v-else>✓ Xác Nhận Đã Chuyển Khoản</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.payment-modal-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.qr-box {
  width: 220px;
  height: 220px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: #fff;
  padding: 10px;
}

.qr-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.invoice-summary-box {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  font-size: 13px;
}

.row {
  display: flex;
  justify-content: space-between;
}

.text-pink { color: var(--pink-dark, #db2777); }

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
