<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { PaymentInfo } from '../types/booking'

const { t } = useI18n()

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

const copiedKey = ref<string | null>(null)

function handleCopy(key: string, text: string, label: string) {
  copiedKey.value = key
  emit('copy', text, label)
  setTimeout(() => {
    if (copiedKey.value === key) copiedKey.value = null
  }, 2000)
}
</script>

<template>
  <div class="payment-methods-panel">
    <div class="panel-header">
      <h3 class="panel-title">
        <span class="material-symbols-outlined panel-icon">credit_card</span>
        <span>{{ $t('payment.paymentMethodTitle') }}</span>
      </h3>
      <span v-if="copyNotice" class="copy-feedback-badge">{{ copyNotice }}</span>
    </div>

    <!-- Method Tabs -->
    <div class="method-tabs-bar">
      <button
        type="button"
        class="method-tab-btn"
        :class="{ active: selectedMethod === 'qr' }"
        @click="emit('update:selectedMethod', 'qr')"
      >
        <span class="material-symbols-outlined tab-icon">qr_code_scanner</span>
        <div class="tab-label-wrap">
          <strong>{{ $t('payment.methodQr') }}</strong>
          <span>{{ $t('payment.methodQrSub') }}</span>
        </div>
      </button>

      <button
        type="button"
        class="method-tab-btn"
        :class="{ active: selectedMethod === 'card' }"
        @click="emit('update:selectedMethod', 'card')"
      >
        <span class="material-symbols-outlined tab-icon">credit_card</span>
        <div class="tab-label-wrap">
          <strong>{{ $t('payment.methodCard') }}</strong>
          <span>{{ $t('payment.methodCardSub') }}</span>
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
          <p>{{ $t('payment.scanQrHelp') }}</p>
        </div>
      </div>

      <div class="bank-info-rows">
        <div class="bank-row">
          <span class="row-label">{{ $t('payment.bank') }}:</span>
          <strong class="row-val">{{ payment.bank }}</strong>
        </div>

        <div class="bank-row">
          <span class="row-label">{{ $t('payment.accountNumber') }}:</span>
          <div class="copyable-val">
            <strong class="row-val font-mono">{{ payment.accountNumber }}</strong>
            <button
              type="button"
              class="btn-copy"
              :class="{ copied: copiedKey === 'accountNumber' }"
              :title="copiedKey === 'accountNumber' ? $t('payment.copied') : $t('payment.copy')"
              @click="handleCopy('accountNumber', payment.accountNumber, $t('payment.accountNumber'))"
            >
              <span class="material-symbols-outlined copy-icon">
                {{ copiedKey === 'accountNumber' ? 'check' : 'content_copy' }}
              </span>
              <span class="btn-copy-text">{{ copiedKey === 'accountNumber' ? $t('payment.copied') : $t('payment.copy') }}</span>
            </button>
          </div>
        </div>

        <div class="bank-row">
          <span class="row-label">{{ $t('payment.amount') }}:</span>
          <div class="copyable-val">
            <strong class="row-val text-pink font-mono">{{ formatCurrency(payment.amount) }}</strong>
            <button
              type="button"
              class="btn-copy"
              :class="{ copied: copiedKey === 'amount' }"
              :title="copiedKey === 'amount' ? $t('payment.copied') : $t('payment.copy')"
              @click="handleCopy('amount', String(payment.amount), $t('payment.amount'))"
            >
              <span class="material-symbols-outlined copy-icon">
                {{ copiedKey === 'amount' ? 'check' : 'content_copy' }}
              </span>
              <span class="btn-copy-text">{{ copiedKey === 'amount' ? $t('payment.copied') : $t('payment.copy') }}</span>
            </button>
          </div>
        </div>

        <div class="bank-row highlight-code-row">
          <span class="row-label">{{ $t('payment.transferContent') }}:</span>
          <div class="copyable-val">
            <strong class="row-val font-mono memo-code">{{ payment.code }}</strong>
            <button
              type="button"
              class="btn-copy btn-copy-primary"
              :class="{ copied: copiedKey === 'code' }"
              :title="copiedKey === 'code' ? $t('payment.copied') : $t('payment.copy')"
              @click="handleCopy('code', payment.code, $t('payment.transferContent'))"
            >
              <span class="material-symbols-outlined copy-icon">
                {{ copiedKey === 'code' ? 'check' : 'content_copy' }}
              </span>
              <span class="btn-copy-text">{{ copiedKey === 'code' ? $t('payment.copied') : $t('payment.copy') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: Stripe Card -->
    <div v-else class="card-method-box">
      <div class="stripe-badge-banner">
        <span class="material-symbols-outlined lock-icon">lock</span>
        <span>{{ $t('payment.stripeSecureNotice') }}</span>
      </div>

      <div class="form-group">
        <label class="form-label">{{ $t('payment.cardNumber') }}</label>
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
          <label class="form-label">{{ $t('payment.cardExpiry') }}</label>
          <input
            :value="cardExpiry"
            type="text"
            class="form-input"
            placeholder="12/28"
            @input="emit('update:cardExpiry', ($event.target as HTMLInputElement).value)"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ $t('payment.cardCvc') }}</label>
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
        <label class="form-label">{{ $t('payment.cardName') }}</label>
        <input
          :value="cardName"
          type="text"
          class="form-input"
          placeholder="NGUYEN VAN A"
          @input="emit('update:cardName', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <button
        type="button"
        class="btn-stripe-pay"
        :disabled="isCardProcessing"
        @click="emit('payStripe')"
      >
        <span v-if="isCardProcessing">{{ $t('payment.verifyingCard') }}</span>
        <span v-else>{{ $t('payment.payAmount', { amount: formatCurrency(payment.amount) }) }}</span>
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
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.panel-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
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
  transition: all 0.15s ease;
}

.method-tab-btn:hover {
  border-color: var(--pink-dark, #db2777);
}

.method-tab-btn.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.08);
}

.tab-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
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
  background: #fff;
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
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
}

.highlight-code-row {
  border-color: rgba(236, 72, 153, 0.4);
  background: linear-gradient(135deg, rgba(236, 72, 153, 0.06) 0%, var(--surface-container, rgba(0, 0, 0, 0.02)) 100%);
}

.row-label {
  font-size: 12.5px;
  color: var(--muted);
  font-weight: 500;
}

.copyable-val {
  display: flex;
  align-items: center;
  gap: 10px;
}

.row-val {
  font-size: 13.5px;
  color: var(--ink);
}

.memo-code {
  font-weight: 800;
  letter-spacing: 0.5px;
}

.btn-copy {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  font-size: 12px;
  font-weight: 650;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.btn-copy:hover {
  background: rgba(236, 72, 153, 0.12);
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
  transform: translateY(-1px);
}

.btn-copy-primary {
  border-color: rgba(236, 72, 153, 0.5);
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
}

.btn-copy.copied {
  background: rgba(16, 185, 129, 0.15);
  border-color: #10b981;
  color: #10b981;
}

.copy-icon {
  font-size: 16px;
}

.btn-copy-text {
  font-size: 11.5px;
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
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(59, 130, 246, 0.08);
  border: 1px solid rgba(59, 130, 246, 0.2);
  color: #3b82f6;
  font-size: 12px;
}

.lock-icon {
  font-size: 18px;
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
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-family: inherit;
}

.form-input:focus {
  outline: none;
  border-color: var(--pink-dark, #db2777);
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
  margin-top: 6px;
  transition: opacity 0.2s ease;
}

.btn-stripe-pay:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
