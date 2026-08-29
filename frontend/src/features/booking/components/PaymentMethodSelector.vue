<script setup lang="ts">
import type { PaymentInfo } from '../types/booking'

const props = defineProps<{
  payment: PaymentInfo
  selectedMethod: 'qr' | 'card'
  cardNumber: string
  cardExpiry: string
  cardCvc: string
  cardName: string
  isCardProcessing?: boolean
  copyNotice?: string
}>()

const emit = defineEmits<{
  (e: 'update:selectedMethod', method: 'qr' | 'card'): void
  (e: 'update:cardNumber', val: string): void
  (e: 'update:cardExpiry', val: string): void
  (e: 'update:cardCvc', val: string): void
  (e: 'update:cardName', val: string): void
  (e: 'payStripe'): void
  (e: 'copy', text: string, label: string): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="payment-methods-panel">
    <div class="panel-header">
      <h3 class="panel-title">💳 Phương Thức Thanh Toán</h3>
      <span v-if="copyNotice" class="copy-feedback-badge">{{ copyNotice }}</span>
    </div>

    <!-- Method Tabs -->
    <div class="method-tabs-bar">
      <button
        class="method-tab-btn"
        :class="{ active: selectedMethod === 'qr' }"
        @click="emit('update:selectedMethod', 'qr')"
      >
        <span class="tab-icon">📱</span>
        <div class="tab-label-wrap">
          <strong>Chuyển Khoản QR</strong>
          <span>VietQR · SePay Auto</span>
        </div>
      </button>

      <button
        class="method-tab-btn"
        :class="{ active: selectedMethod === 'card' }"
        @click="emit('update:selectedMethod', 'card')"
      >
        <span class="tab-icon">💳</span>
        <div class="tab-label-wrap">
          <strong>Thẻ Quốc Tế</strong>
          <span>Visa · MasterCard · Stripe</span>
        </div>
      </button>
    </div>

    <!-- Tab 1: QR VietQR -->
    <div v-if="selectedMethod === 'qr'" class="qr-method-box">
      <div class="qr-code-display-wrap">
        <img
          v-if="payment.qrUrl"
          :src="payment.qrUrl"
          alt="VietQR Payment"
          class="vietqr-img"
        />
        <div class="qr-scan-guide">
          <p>Mở ứng dụng ngân hàng hoặc ví điện tử để quét mã thanh toán tự động.</p>
        </div>
      </div>

      <div class="bank-info-rows">
        <div class="bank-row">
          <span class="row-label">Ngân hàng:</span>
          <strong class="row-val">{{ payment.bank }}</strong>
        </div>

        <div class="bank-row">
          <span class="row-label">Số tài khoản:</span>
          <div class="copyable-val">
            <strong class="row-val">{{ payment.accountNumber }}</strong>
            <button class="btn-copy" @click="emit('copy', payment.accountNumber, 'Số tài khoản')">📋</button>
          </div>
        </div>

        <div class="bank-row">
          <span class="row-label">Số tiền:</span>
          <div class="copyable-val">
            <strong class="row-val text-pink">{{ formatMoney(payment.amount) }}</strong>
            <button class="btn-copy" @click="emit('copy', String(payment.amount), 'Số tiền')">📋</button>
          </div>
        </div>

        <div class="bank-row">
          <span class="row-label">Nội dung chuyển khoản:</span>
          <div class="copyable-val">
            <strong class="row-val font-mono">{{ payment.code }}</strong>
            <button class="btn-copy" @click="emit('copy', payment.code, 'Nội dung chuyển khoản')">📋</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: Stripe Card -->
    <div v-else class="card-method-box">
      <div class="stripe-badge-banner">
        🔒 Thanh toán bảo mật qua cổng quốc tế <strong>Stripe</strong> (256-bit SSL)
      </div>

      <div class="form-group">
        <label class="form-label">Số thẻ tín dụng / ghi nợ:</label>
        <input
          :value="cardNumber"
          type="text"
          class="form-input font-mono"
          placeholder="4242 •••• •••• 4242"
          @input="emit('update:cardNumber', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="card-multi-row">
        <div class="form-group">
          <label class="form-label">Hạn thẻ (MM/YY):</label>
          <input
            :value="cardExpiry"
            type="text"
            class="form-input"
            placeholder="12/28"
            @input="emit('update:cardExpiry', ($event.target as HTMLInputElement).value)"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Mã CVC / CVV:</label>
          <input
            :value="cardCvc"
            type="password"
            maxlength="4"
            class="form-input"
            placeholder="•••"
            @input="emit('update:cardCvc', ($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Tên in trên thẻ:</label>
        <input
          :value="cardName"
          type="text"
          class="form-input"
          placeholder="NGUYEN VAN A"
          @input="emit('update:cardName', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <button
        class="btn-stripe-pay"
        :disabled="isCardProcessing"
        @click="emit('payStripe')"
      >
        <span v-if="isCardProcessing">Đang xác thực thẻ qua Stripe...</span>
        <span v-else>Thanh Toán {{ formatMoney(payment.amount) }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.payment-methods-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.panel-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.copy-feedback-badge {
  padding: 4px 10px;
  border-radius: 6px;
  background: #10b981;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
}

.method-tabs-bar {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.method-tab-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  text-align: left;
  transition: all 0.15s;
}

.method-tab-btn:hover {
  border-color: var(--pink-dark, #db2777);
}

.method-tab-btn.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
}

.tab-icon {
  font-size: 24px;
}

.tab-label-wrap {
  display: flex;
  flex-direction: column;
}

.tab-label-wrap strong {
  font-size: 13px;
}

.tab-label-wrap span {
  font-size: 11px;
  color: var(--muted);
}

.qr-method-box {
  display: flex;
  flex-direction: column;
  gap: 16px;
  align-items: center;
  padding-top: 8px;
}

.qr-code-display-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.vietqr-img {
  width: 220px;
  height: 220px;
  border-radius: 12px;
  border: 1px solid var(--outline);
}

.qr-scan-guide {
  font-size: 12px;
  color: var(--muted);
  text-align: center;
}

.bank-info-rows {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.bank-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.row-label {
  font-size: 12px;
  color: var(--muted);
}

.copyable-val {
  display: flex;
  align-items: center;
  gap: 8px;
}

.row-val {
  font-size: 13px;
}

.btn-copy {
  border: 0;
  background: transparent;
  cursor: pointer;
  font-size: 14px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.card-method-box {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.stripe-badge-banner {
  padding: 10px;
  border-radius: 8px;
  background: rgba(59, 130, 246, 0.08);
  color: #3b82f6;
  font-size: 12px;
}

.card-multi-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.btn-stripe-pay {
  padding: 12px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
</style>
