<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { VenueWallet, PaymentMethodType } from '../composables/useWallet'

const props = defineProps<{
  show: boolean
  wallets: VenueWallet[]
  targetWalletId: string
  amount: number
  method: PaymentMethodType
  quickAmounts: number[]
  step: 'select' | 'sepay_pay' | 'stripe_pay'
  cardNumber: string
  cardExpiry: string
  cardCvc: string
  cardName: string
  transferInfo: {
    bank: string
    accountNo: string
    accountName: string
    memo: string
    qrUrl: string
    amount: number
  }
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:targetWalletId', id: string): void
  (e: 'update:amount', amount: number): void
  (e: 'update:method', method: PaymentMethodType): void
  (e: 'update:cardNumber', val: string): void
  (e: 'update:cardExpiry', val: string): void
  (e: 'update:cardCvc', val: string): void
  (e: 'update:cardName', val: string): void
  (e: 'proceed'): void
  (e: 'confirm'): void
  (e: 'back'): void
  (e: 'copy', text: string, label: string): void
}>()
const { t } = useI18n()

const customAmountInput = ref<number>(props.amount)

function onCustomInput(e: Event) {
  const val = Number((e.target as HTMLInputElement).value)
  customAmountInput.value = val
  emit('update:amount', val)
}

function selectPreset(amt: number) {
  customAmountInput.value = amt
  emit('update:amount', amt)
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="modal-title-box">
          <span class="material-symbols-outlined header-icon">
            {{ step === 'stripe_pay' ? 'credit_card' : 'account_balance_wallet' }}
          </span>
          <h3 class="modal-title">
            <template v-if="step === 'sepay_pay'">{{
              t('wallet.depositModal.titleSepay')
            }}</template>
            <template v-else-if="step === 'stripe_pay'">{{
              t('wallet.depositModal.titleStripe')
            }}</template>
            <template v-else>{{ t('wallet.depositModal.titleDefault') }}</template>
          </h3>
        </div>
        <button
          class="modal-close-btn"
          @click="emit('close')"
          :aria-label="t('wallet.depositModal.cancelBtn')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Step 1: Select Amount & Method -->
      <div v-if="step === 'select'" class="modal-body">
        <!-- Target Wallet Selection -->
        <div class="form-group">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">credit_card</span>
            <span>{{ t('wallet.depositModal.selectWalletLabel') }}</span>
          </label>
          <select
            :value="targetWalletId"
            class="form-select-luxury"
            @change="emit('update:targetWalletId', ($event.target as HTMLSelectElement).value)"
          >
            <option v-for="w in wallets" :key="w.id" :value="w.id">
              {{ w.venue_name }} ·
              {{ t('wallet.depositModal.currentBalance', { amount: formatMoney(w.balance) }) }}
            </option>
          </select>
        </div>

        <!-- Quick Amount Presets -->
        <div class="form-group">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">payments</span>
            <span>{{ t('wallet.depositModal.quickAmountsLabel') }}</span>
          </label>
          <div class="presets-grid">
            <button
              v-for="amt in quickAmounts"
              :key="amt"
              type="button"
              class="preset-btn font-mono"
              :class="{ active: amount === amt }"
              @click="selectPreset(amt)"
            >
              <span class="preset-val">{{ formatMoney(amt) }}</span>
              <span v-if="amt >= 1000000" class="bonus-badge font-mono">+5% Pts</span>
            </button>
          </div>
        </div>

        <!-- Custom Amount Input -->
        <div class="form-group">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">edit</span>
            <span>{{ t('wallet.depositModal.customAmountLabel') }}</span>
          </label>
          <div class="input-with-suffix">
            <input
              type="number"
              :value="amount"
              min="50000"
              step="50000"
              class="form-input-luxury font-mono"
              :placeholder="t('wallet.depositModal.customAmountPlaceholder')"
              @input="onCustomInput"
            />
            <span class="input-suffix font-mono">VND</span>
          </div>
          <span class="input-help">{{ t('wallet.depositModal.minDepositNote') }}</span>
        </div>

        <!-- Payment Method Selection -->
        <div class="form-group">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">account_balance</span>
            <span>{{ t('wallet.depositModal.paymentMethodLabel') }}</span>
          </label>
          <div class="methods-list">
            <!-- 1. SePay VietQR -->
            <label class="method-option-card" :class="{ selected: method === 'sepay' }">
              <input
                type="radio"
                name="wallet-pay-method"
                value="sepay"
                :checked="method === 'sepay'"
                class="method-radio"
                @change="emit('update:method', 'sepay')"
              />
              <div class="method-meta">
                <div class="method-title-row">
                  <span class="material-symbols-outlined method-icon text-accent"
                    >qr_code_scanner</span
                  >
                  <strong>{{ t('wallet.depositModal.methodSepayTitle') }}</strong>
                  <span class="instant-pill">{{ t('wallet.depositModal.methodSepayTag') }}</span>
                </div>
                <p class="method-desc">
                  {{ t('wallet.depositModal.methodSepayDesc') }}
                </p>
              </div>
            </label>

            <!-- 2. Stripe Card -->
            <label class="method-option-card" :class="{ selected: method === 'stripe' }">
              <input
                type="radio"
                name="wallet-pay-method"
                value="stripe"
                :checked="method === 'stripe'"
                class="method-radio"
                @change="emit('update:method', 'stripe')"
              />
              <div class="method-meta">
                <div class="method-title-row">
                  <span class="material-symbols-outlined method-icon">credit_card</span>
                  <strong>{{ t('wallet.depositModal.methodStripeTitle') }}</strong>
                  <span class="stripe-pill">{{ t('wallet.depositModal.methodStripeTag') }}</span>
                </div>
                <p class="method-desc">
                  {{ t('wallet.depositModal.methodStripeDesc') }}
                </p>
              </div>
            </label>
          </div>
        </div>

        <!-- Breakdown summary -->
        <div class="summary-box">
          <div class="sum-row">
            <span>{{ t('wallet.depositModal.summaryDepositAmount') }}</span>
            <strong class="font-mono">{{ formatMoney(amount) }}</strong>
          </div>
          <div class="sum-row">
            <span>{{ t('wallet.depositModal.summaryFee') }}</span>
            <span class="text-green font-mono">{{ t('wallet.depositModal.summaryFeeFree') }}</span>
          </div>
          <div class="sum-row">
            <span>{{ t('wallet.depositModal.summaryPoints') }}</span>
            <span class="text-gold font-mono">+{{ Math.floor(amount / 10000) }} pts</span>
          </div>
          <div class="sum-divider"></div>
          <div class="sum-row total">
            <span>{{ t('wallet.depositModal.summaryTotal') }}</span>
            <strong class="total-val font-mono">{{ formatMoney(amount) }}</strong>
          </div>
        </div>
      </div>

      <!-- Step 2A: SePay VietQR Direct Payment -->
      <div v-else-if="step === 'sepay_pay'" class="modal-body vietqr-body">
        <div class="vqr-instructions">
          <p class="vqr-lead">
            {{ t('wallet.depositModal.vqrLead') }}
          </p>
        </div>

        <div class="vqr-card-container">
          <div class="vqr-qr-frame">
            <img :src="transferInfo.qrUrl" alt="VietQR SePay Payment Code" class="vqr-image" />
          </div>

          <div class="vqr-details-table">
            <div class="vqr-detail-row">
              <span class="vqr-d-label">{{ t('wallet.depositModal.beneficiaryBank') }}</span>
              <strong class="vqr-d-val font-mono">{{ transferInfo.bank }}</strong>
            </div>

            <div class="vqr-detail-row">
              <span class="vqr-d-label">{{ t('wallet.depositModal.accountNumber') }}</span>
              <div class="vqr-d-copyable">
                <strong class="vqr-d-val font-mono">{{ transferInfo.accountNo }}</strong>
                <button
                  type="button"
                  class="btn-copy-mini"
                  @click="
                    emit('copy', transferInfo.accountNo, t('wallet.depositModal.copiedAccount'))
                  "
                  :title="t('wallet.depositModal.copiedAccount')"
                >
                  <span class="material-symbols-outlined">content_copy</span>
                </button>
              </div>
            </div>

            <div class="vqr-detail-row">
              <span class="vqr-d-label">{{ t('wallet.depositModal.accountName') }}</span>
              <strong class="vqr-d-val">{{ transferInfo.accountName }}</strong>
            </div>

            <div class="vqr-detail-row">
              <span class="vqr-d-label">{{ t('wallet.depositModal.amountLabel') }}</span>
              <strong class="vqr-d-val text-primary font-mono">{{
                formatMoney(transferInfo.amount)
              }}</strong>
            </div>

            <div class="vqr-detail-row highlight-memo">
              <span class="vqr-d-label">{{ t('wallet.depositModal.transferMemo') }}</span>
              <div class="vqr-d-copyable">
                <strong class="vqr-d-val font-mono">{{ transferInfo.memo }}</strong>
                <button
                  type="button"
                  class="btn-copy-mini"
                  @click="emit('copy', transferInfo.memo, t('wallet.depositModal.copiedMemo'))"
                  :title="t('wallet.depositModal.copiedMemo')"
                >
                  <span class="material-symbols-outlined">content_copy</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="vqr-listening-pill">
          <span class="pulse-dot"></span>
          <span>{{ t('wallet.depositModal.vqrListening') }}</span>
        </div>
      </div>

      <!-- Step 2B: Stripe Card Payment Form -->
      <div v-else-if="step === 'stripe_pay'" class="modal-body stripe-body">
        <div class="stripe-badge-banner">
          <span class="material-symbols-outlined lock-icon">lock</span>
          <span>{{ t('wallet.depositModal.stripeBanner') }}</span>
        </div>
        <div class="stripe-form">
          <div class="form-group">
            <label class="form-label">
              <span class="material-symbols-outlined label-icon">credit_card</span>
              <span>{{ t('wallet.depositModal.stripeCardNumber') }}:</span>
            </label>
            <input
              type="text"
              :value="cardNumber"
              class="form-input-luxury font-mono"
              :placeholder="t('wallet.depositModal.stripeCardNumberPlaceholder')"
              maxlength="19"
              @input="emit('update:cardNumber', ($event.target as HTMLInputElement).value)"
            />
          </div>

          <div class="stripe-row-2col">
            <div class="form-group">
              <label class="form-label">
                <span class="material-symbols-outlined label-icon">calendar_month</span>
                <span>{{ t('wallet.depositModal.stripeExpiry') }}:</span>
              </label>
              <input
                type="text"
                :value="cardExpiry"
                class="form-input-luxury font-mono"
                placeholder="12/28"
                maxlength="5"
                @input="emit('update:cardExpiry', ($event.target as HTMLInputElement).value)"
              />
            </div>

            <div class="form-group">
              <label class="form-label">
                <span class="material-symbols-outlined label-icon">pin</span>
                <span>{{ t('wallet.depositModal.stripeCvc') }}:</span>
              </label>
              <input
                type="password"
                :value="cardCvc"
                class="form-input-luxury font-mono"
                placeholder="•••"
                maxlength="4"
                @input="emit('update:cardCvc', ($event.target as HTMLInputElement).value)"
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">
              <span class="material-symbols-outlined label-icon">person</span>
              <span>{{ t('wallet.depositModal.stripeName') }}:</span>
            </label>
            <input
              type="text"
              :value="cardName"
              class="form-input-luxury"
              :placeholder="t('wallet.depositModal.stripeNamePlaceholder')"
              @input="emit('update:cardName', ($event.target as HTMLInputElement).value)"
            />
          </div>
        </div>

        <div class="stripe-summary-pill">
          <span>{{
            t('wallet.depositModal.stripeSummaryPill', { amount: formatMoney(amount) })
          }}</span>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <button
          v-if="step !== 'select'"
          type="button"
          class="btn btn-outline"
          @click="emit('back')"
        >
          <span class="material-symbols-outlined">arrow_back</span>
          <span>{{ t('wallet.depositModal.backBtn') }}</span>
        </button>
        <button v-else type="button" class="btn btn-outline" @click="emit('close')">
          <span>{{ t('wallet.depositModal.cancelBtn') }}</span>
        </button>

        <button
          v-if="step === 'select'"
          type="button"
          class="btn btn-primary"
          :disabled="processing || amount < 50000"
          @click="emit('proceed')"
        >
          <span v-if="processing">{{ t('admin.pos.processing') }}</span>
          <span v-else>{{ t('wallet.depositModal.continueBtn') }} · {{ formatMoney(amount) }}</span>
        </button>

        <button
          v-else-if="step === 'sepay_pay'"
          type="button"
          class="btn btn-primary"
          :disabled="processing"
          @click="emit('confirm')"
        >
          <span class="material-symbols-outlined">check_circle</span>
          <span v-if="processing">{{ t('wallet.depositModal.stripeVerifying') }}</span>
          <span v-else>{{ t('wallet.depositModal.confirmTransferredBtn') }}</span>
        </button>

        <button
          v-else-if="step === 'stripe_pay'"
          type="button"
          class="btn btn-primary"
          :disabled="processing"
          @click="emit('confirm')"
        >
          <span class="material-symbols-outlined">lock</span>
          <span v-if="processing">{{ t('wallet.depositModal.stripeProcessing') }}</span>
          <span v-else>{{
            t('wallet.depositModal.payNowBtn', { amount: formatMoney(amount) })
          }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-title-box {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 22px;
  color: var(--accent, #c9a15a);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 18px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.label-icon {
  font-size: 16px;
  color: var(--accent, #c9a15a);
}

.form-select-luxury,
.form-input-luxury {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
  transition: border-color 0.15s ease;
}

.form-select-luxury:focus,
.form-input-luxury:focus {
  border-color: var(--accent, #c9a15a);
}

.presets-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.preset-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 10px 6px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface-subtle, #f7f3ed);
  color: var(--ink);
  cursor: pointer;
  transition: all 0.15s ease;
}

:global(html.dark) .preset-btn {
  background: var(--surface-subtle, #262024);
}

.preset-btn:hover {
  border-color: var(--accent, #c9a15a);
}

.preset-btn.active {
  border-color: var(--accent, #c9a15a);
  background: var(--accent-container, rgba(201, 161, 90, 0.15));
  color: var(--on-accent-container, #684f17);
  box-shadow: 0 0 0 1px var(--accent, #c9a15a);
}

:global(html.dark) .preset-btn.active {
  color: var(--accent, #d8b46e);
  background: rgba(216, 180, 110, 0.2);
}

.preset-val {
  font-size: 12.5px;
  font-weight: 700;
}

.bonus-badge {
  font-size: 10px;
  font-weight: 800;
  color: #10b981;
  margin-top: 2px;
}

.input-with-suffix {
  position: relative;
  display: flex;
  align-items: center;
}

.input-suffix {
  position: absolute;
  right: 14px;
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  pointer-events: none;
}

.input-help {
  font-size: 11.5px;
  color: var(--muted);
}

.methods-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.method-option-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface);
  cursor: pointer;
  transition: all 0.15s ease;
}

.method-option-card:hover {
  border-color: var(--outline);
}

.method-option-card.selected {
  border-color: var(--accent, #c9a15a);
  background: var(--accent-container, rgba(201, 161, 90, 0.08));
}

.method-radio {
  margin-top: 3px;
  accent-color: var(--accent, #c9a15a);
}

.method-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.method-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.method-icon {
  font-size: 18px;
  color: var(--muted);
}

.method-icon.text-accent {
  color: var(--accent, #c9a15a);
}

.instant-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: #10b981;
  color: #ffffff;
}

.stripe-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: #635bff;
  color: #ffffff;
}

.method-desc {
  margin: 0;
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.35;
}

.summary-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border);
  font-size: 13px;
}

:global(html.dark) .summary-box {
  background: var(--surface-subtle, #262024);
}

.sum-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sum-divider {
  height: 1px;
  background: var(--border);
  margin: 4px 0;
}

.sum-row.total {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--ink);
}

.total-val {
  font-size: 18px;
  color: var(--primary, #6b2d3f);
}

:global(html.dark) .total-val {
  color: var(--primary, #e58e9f);
}

.text-green {
  color: #10b981;
  font-weight: 700;
}
.text-gold {
  color: var(--accent, #c9a15a);
  font-weight: 700;
}

/* VietQR Step */
.vietqr-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.vqr-instructions {
  text-align: center;
}

.vqr-lead {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.vqr-card-container {
  display: flex;
  gap: 20px;
  align-items: center;
  flex-wrap: wrap;
  justify-content: center;
  width: 100%;
}

.vqr-qr-frame {
  width: 190px;
  height: 190px;
  padding: 8px;
  border-radius: 14px;
  background: #ffffff;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  display: flex;
  align-items: center;
  justify-content: center;
}

.vqr-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.vqr-details-table {
  flex: 1;
  min-width: 260px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border);
  font-size: 12.5px;
}

:global(html.dark) .vqr-details-table {
  background: var(--surface-subtle, #262024);
}

.vqr-detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.vqr-d-label {
  color: var(--muted);
  font-size: 12px;
}

.vqr-d-val {
  color: var(--ink);
}

.vqr-d-copyable {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-copy-mini {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--accent, #c9a15a);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-copy-mini:hover {
  background: var(--accent-container);
}

.btn-copy-mini .material-symbols-outlined {
  font-size: 14px;
}

.highlight-memo {
  background: rgba(201, 161, 90, 0.12);
  padding: 6px 8px;
  border-radius: 6px;
  border: 1px dashed rgba(201, 161, 90, 0.4);
}

.text-primary {
  color: var(--primary, #6b2d3f);
  font-size: 14px;
}

:global(html.dark) .text-primary {
  color: var(--primary, #e58e9f);
}

.vqr-listening-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  font-size: 12px;
  font-weight: 600;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: pulse-ring 1.5s infinite;
}

@keyframes pulse-ring {
  0% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
  }
}

/* Stripe Form Step */
.stripe-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.stripe-badge-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(99, 91, 255, 0.08);
  border: 1px solid rgba(99, 91, 255, 0.2);
  color: #635bff;
  font-size: 12px;
  font-weight: 600;
}

:global(html.dark) .stripe-badge-banner {
  background: rgba(99, 91, 255, 0.15);
  color: #9b95ff;
}

.lock-icon {
  font-size: 16px;
}

.stripe-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.stripe-row-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.stripe-summary-pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-radius: 10px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border);
  font-size: 13px;
  color: var(--muted);
}

:global(html.dark) .stripe-summary-pill {
  background: var(--surface-subtle, #262024);
}
</style>
