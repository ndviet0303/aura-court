<script setup lang="ts">
import { ref } from 'vue'
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
            <template v-if="step === 'sepay_pay'">Chuyển Khoản Nạp Tiền VietQR SePay 24/7</template>
            <template v-else-if="step === 'stripe_pay'">Thanh Toán Thẻ Quốc Tế Qua Stripe</template>
            <template v-else>Nạp Tiền Vào Ví Hội Viên</template>
          </h3>
        </div>
        <button class="modal-close-btn" @click="emit('close')" aria-label="Đóng">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Step 1: Select Amount & Method -->
      <div v-if="step === 'select'" class="modal-body">
        <!-- Target Wallet Selection -->
        <div class="form-group">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">credit_card</span>
            <span>Chọn thẻ / ví muốn nạp:</span>
          </label>
          <select
            :value="targetWalletId"
            class="form-select-luxury"
            @change="emit('update:targetWalletId', ($event.target as HTMLSelectElement).value)"
          >
            <option v-for="w in wallets" :key="w.id" :value="w.id">
              {{ w.venue_name }} · Số dư hiện tại: {{ formatMoney(w.balance) }}
            </option>
          </select>
        </div>

        <!-- Quick Amount Presets -->
        <div class="form-group">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">payments</span>
            <span>Chọn nhanh mệnh giá nạp:</span>
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
            <span>Hoặc nhập số tiền tùy chỉnh:</span>
          </label>
          <div class="input-with-suffix">
            <input
              type="number"
              :value="amount"
              min="50000"
              step="50000"
              class="form-input-luxury font-mono"
              placeholder="Nhập số tiền..."
              @input="onCustomInput"
            />
            <span class="input-suffix font-mono">VND</span>
          </div>
          <span class="input-help">Mức nạp tối thiểu là 50.000 ₫ · Không giới hạn số lần nạp</span>
        </div>

        <!-- Payment Method Selection -->
        <div class="form-group">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">account_balance</span>
            <span>Phương thức thanh toán:</span>
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
                  <span class="material-symbols-outlined method-icon text-accent">qr_code_scanner</span>
                  <strong>Chuyển khoản VietQR SePay (Khuyên dùng)</strong>
                  <span class="instant-pill">Tức thì 3s</span>
                </div>
                <p class="method-desc">
                  Quét mã VietQR chuyển khoản liên ngân hàng 24/7 qua cổng SePay tự động cộng số dư tức thì sau 3 giây.
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
                  <strong>Cổng thanh toán quốc tế Stripe</strong>
                  <span class="stripe-pill">Bảo mật SSL</span>
                </div>
                <p class="method-desc">
                  Hỗ trợ thẻ tín dụng / ghi nợ quốc tế Visa, MasterCard, JCB, Apple Pay với chuẩn mã hóa PCI-DSS Level 1.
                </p>
              </div>
            </label>
          </div>
        </div>

        <!-- Breakdown summary -->
        <div class="summary-box">
          <div class="sum-row">
            <span>Số tiền nạp:</span>
            <strong class="font-mono">{{ formatMoney(amount) }}</strong>
          </div>
          <div class="sum-row">
            <span>Phí giao dịch:</span>
            <span class="text-green font-mono">0 ₫ (Miễn phí)</span>
          </div>
          <div class="sum-row">
            <span>Điểm tích lũy nhận kèm:</span>
            <span class="text-gold font-mono">+{{ Math.floor(amount / 10000) }} pts</span>
          </div>
          <div class="sum-divider"></div>
          <div class="sum-row total">
            <span>Tổng thanh toán:</span>
            <strong class="total-val font-mono">{{ formatMoney(amount) }}</strong>
          </div>
        </div>
      </div>

      <!-- Step 2A: SePay VietQR Direct Payment -->
      <div v-else-if="step === 'sepay_pay'" class="modal-body vietqr-body">
        <div class="vqr-instructions">
          <p class="vqr-lead">
            Quét mã QR SePay bằng ứng dụng ngân hàng bất kỳ để hoàn tất nạp tiền tự động:
          </p>
        </div>

        <div class="vqr-card-container">
          <div class="vqr-qr-frame">
            <img :src="transferInfo.qrUrl" alt="VietQR SePay Payment Code" class="vqr-image" />
          </div>

          <div class="vqr-details-table">
            <div class="vqr-detail-row">
              <span class="vqr-d-label">Ngân hàng thụ hưởng:</span>
              <strong class="vqr-d-val font-mono">{{ transferInfo.bank }}</strong>
            </div>

            <div class="vqr-detail-row">
              <span class="vqr-d-label">Số tài khoản:</span>
              <div class="vqr-d-copyable">
                <strong class="vqr-d-val font-mono">{{ transferInfo.accountNo }}</strong>
                <button
                  type="button"
                  class="btn-copy-mini"
                  @click="emit('copy', transferInfo.accountNo, 'Đã chép số tài khoản')"
                  title="Sao chép số tài khoản"
                >
                  <span class="material-symbols-outlined">content_copy</span>
                </button>
              </div>
            </div>

            <div class="vqr-detail-row">
              <span class="vqr-d-label">Tên chủ tài khoản:</span>
              <strong class="vqr-d-val">{{ transferInfo.accountName }}</strong>
            </div>

            <div class="vqr-detail-row">
              <span class="vqr-d-label">Số tiền:</span>
              <strong class="vqr-d-val text-primary font-mono">{{ formatMoney(transferInfo.amount) }}</strong>
            </div>

            <div class="vqr-detail-row highlight-memo">
              <span class="vqr-d-label">Nội dung chuyển khoản:</span>
              <div class="vqr-d-copyable">
                <strong class="vqr-d-val font-mono">{{ transferInfo.memo }}</strong>
                <button
                  type="button"
                  class="btn-copy-mini"
                  @click="emit('copy', transferInfo.memo, 'Đã chép nội dung chuyển khoản')"
                  title="Sao chép nội dung"
                >
                  <span class="material-symbols-outlined">content_copy</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="vqr-listening-pill">
          <span class="pulse-dot"></span>
          <span>Hệ thống SePay đang tự động lắng nghe giao dịch chuyển khoản...</span>
        </div>
      </div>

      <!-- Step 2B: Stripe Card Payment Form -->
      <div v-else-if="step === 'stripe_pay'" class="modal-body stripe-body">
        <div class="stripe-badge-banner">
          <span class="material-symbols-outlined lock-icon">lock</span>
          <span>Thanh toán bảo mật trực tiếp qua cổng <strong>Stripe</strong> (Mã hóa SSL 256-bit)</span>
        </div>

        <div class="stripe-form">
          <div class="form-group">
            <label class="form-label">
              <span class="material-symbols-outlined label-icon">credit_card</span>
              <span>Số thẻ tín dụng / ghi nợ:</span>
            </label>
            <input
              type="text"
              :value="cardNumber"
              class="form-input-luxury font-mono"
              placeholder="4242 •••• •••• 4242"
              maxlength="19"
              @input="emit('update:cardNumber', ($event.target as HTMLInputElement).value)"
            />
          </div>

          <div class="stripe-row-2col">
            <div class="form-group">
              <label class="form-label">
                <span class="material-symbols-outlined label-icon">calendar_month</span>
                <span>Hạn thẻ (MM/YY):</span>
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
                <span>Mã CVC/CVV:</span>
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
              <span>Tên in trên thẻ:</span>
            </label>
            <input
              type="text"
              :value="cardName"
              class="form-input-luxury"
              placeholder="NGUYEN VAN A"
              @input="emit('update:cardName', ($event.target as HTMLInputElement).value)"
            />
          </div>
        </div>

        <div class="stripe-summary-pill">
          <span>Số tiền nạp qua thẻ: </span>
          <strong class="font-mono text-primary">{{ formatMoney(amount) }}</strong>
          <span>(Miễn phí giao dịch)</span>
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
          <span>Quay Lại</span>
        </button>
        <button
          v-else
          type="button"
          class="btn btn-outline"
          @click="emit('close')"
        >
          <span>Huỷ Bỏ</span>
        </button>

        <button
          v-if="step === 'select'"
          type="button"
          class="btn btn-primary"
          :disabled="processing || amount < 50000"
          @click="emit('proceed')"
        >
          <span v-if="processing">Đang xử lý...</span>
          <span v-else>Tiếp Tục Thanh Toán · {{ formatMoney(amount) }}</span>
        </button>

        <button
          v-else-if="step === 'sepay_pay'"
          type="button"
          class="btn btn-primary"
          :disabled="processing"
          @click="emit('confirm')"
        >
          <span class="material-symbols-outlined">check_circle</span>
          <span v-if="processing">Đang xác thực...</span>
          <span v-else>Xác Nhận Đã Chuyển Tiền</span>
        </button>

        <button
          v-else-if="step === 'stripe_pay'"
          type="button"
          class="btn btn-primary"
          :disabled="processing"
          @click="emit('confirm')"
        >
          <span class="material-symbols-outlined">lock</span>
          <span v-if="processing">Đang xử lý thẻ...</span>
          <span v-else>Nạp Ngay {{ formatMoney(amount) }} Qua Stripe</span>
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
  color: var(--accent, #C9A15A);
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
  color: var(--accent, #C9A15A);
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
  border-color: var(--accent, #C9A15A);
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
  border-color: var(--accent, #C9A15A);
}

.preset-btn.active {
  border-color: var(--accent, #C9A15A);
  background: var(--accent-container, rgba(201, 161, 90, 0.15));
  color: var(--on-accent-container, #684F17);
  box-shadow: 0 0 0 1px var(--accent, #C9A15A);
}

:global(html.dark) .preset-btn.active {
  color: var(--accent, #D8B46E);
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
  border-color: var(--accent, #C9A15A);
  background: var(--accent-container, rgba(201, 161, 90, 0.08));
}

.method-radio {
  margin-top: 3px;
  accent-color: var(--accent, #C9A15A);
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
  color: var(--accent, #C9A15A);
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
  color: var(--primary, #6B2D3F);
}

:global(html.dark) .total-val {
  color: var(--primary, #E58E9F);
}

.text-green { color: #10b981; font-weight: 700; }
.text-gold { color: var(--accent, #C9A15A); font-weight: 700; }

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
  color: var(--accent, #C9A15A);
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
  color: var(--primary, #6B2D3F);
  font-size: 14px;
}

:global(html.dark) .text-primary {
  color: var(--primary, #E58E9F);
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
