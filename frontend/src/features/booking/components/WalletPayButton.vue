<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { formatCurrency } from '@/i18n'

const props = defineProps<{
  amount: number
  walletBalance: number
  loading?: boolean
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'pay'): void
}>()

const router = useRouter()

const isSufficient = computed(() => props.walletBalance >= props.amount)
const shortfall = computed(() => Math.max(0, props.amount - props.walletBalance))
const balanceAfter = computed(() => Math.max(0, props.walletBalance - props.amount))

function goToWallet() {
  router.push('/wallet')
}
</script>

<template>
  <div class="wallet-pay-card">
    <div class="wallet-pay-header">
      <div class="header-left">
        <span class="material-symbols-outlined wallet-icon">account_balance_wallet</span>
        <div>
          <h3 class="wallet-pay-title">{{ $t('payment.payFromWalletTitle') || 'Thanh Toán Bằng Ví Aura' }}</h3>
          <p class="wallet-pay-subtitle">{{ $t('payment.payFromWalletSubtitle') || 'Trừ trực tiếp số dư ví và xác nhận giữ chỗ ngay lập tức' }}</p>
        </div>
      </div>
      <div class="balance-pill" :class="{ 'balance-insufficient': !isSufficient }">
        <span class="pill-label">{{ $t('payment.currentBalance') || 'Số dư ví' }}:</span>
        <strong class="pill-amount font-mono">{{ formatCurrency(walletBalance) }}</strong>
      </div>
    </div>

    <!-- Payment Action Area -->
    <div v-if="isSufficient" class="wallet-action-box sufficient-box">
      <div class="balance-preview-row">
        <span>{{ $t('payment.balanceAfter') || 'Số dư sau thanh toán' }}:</span>
        <strong class="font-mono text-emerald">{{ formatCurrency(balanceAfter) }}</strong>
      </div>

      <button
        type="button"
        class="btn-wallet-pay"
        :disabled="processing || loading"
        @click="emit('pay')"
      >
        <span class="material-symbols-outlined btn-icon">
          {{ processing ? 'progress_activity' : 'check_circle' }}
        </span>
        <span v-if="processing">{{ $t('payment.processing') || 'Đang xử lý thanh toán...' }}</span>
        <span v-else>{{ $t('payment.confirmWalletPay', { amount: formatCurrency(amount) }) || `Xác Nhận Thanh Toán ${formatCurrency(amount)}` }}</span>
      </button>
    </div>

    <!-- Insufficient Balance Area -->
    <div v-else class="wallet-action-box insufficient-box">
      <div class="shortfall-alert">
        <span class="material-symbols-outlined shortfall-icon">warning</span>
        <div class="shortfall-text">
          <strong>{{ $t('payment.insufficientBalanceTitle') || 'Số dư ví chưa đủ để thanh toán' }}</strong>
          <p>{{ $t('payment.shortfallDesc', { shortfall: formatCurrency(shortfall) }) || `Bạn cần nạp thêm tối thiểu ${formatCurrency(shortfall)} để hoàn tất giao dịch.` }}</p>
        </div>
      </div>

      <button
        type="button"
        class="btn-topup-wallet"
        @click="goToWallet"
      >
        <span class="material-symbols-outlined">add_card</span>
        <span>{{ $t('payment.topupNowCta') || 'Nạp Tiền Vào Ví Ngay' }}</span>
      </button>
    </div>

    <!-- Trust Feature List -->
    <div class="wallet-trust-footer">
      <div class="trust-item">
        <span class="material-symbols-outlined trust-icon">bolt</span>
        <span>{{ $t('payment.instantConfirmation') || 'Xác nhận tức thì không chờ đợi' }}</span>
      </div>
      <div class="trust-item">
        <span class="material-symbols-outlined trust-icon">assignment_return</span>
        <span>{{ $t('payment.autoRefundNotice') || 'Hoàn tiền 100% tự động về ví khi huỷ lịch' }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wallet-pay-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 18px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.wallet-pay-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--outline);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.wallet-icon {
  font-size: 32px;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.08);
  padding: 8px;
  border-radius: 12px;
}

.wallet-pay-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
}

.wallet-pay-subtitle {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.balance-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--outline);
}

.balance-pill.balance-insufficient {
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.06);
}

.pill-label {
  font-size: 12px;
  color: var(--muted);
}

.pill-amount {
  font-size: 14px;
  color: var(--ink);
}

.balance-insufficient .pill-amount {
  color: #ef4444;
}

.wallet-action-box {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.balance-preview-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: var(--muted);
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px dashed var(--outline);
}

.text-emerald {
  color: #10b981;
}

.btn-wallet-pay {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 14px 20px;
  border-radius: 12px;
  border: none;
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.25);
  transition: all 0.2s ease;
}

.btn-wallet-pay:hover:not(:disabled) {
  opacity: 0.95;
  transform: translateY(-1px);
}

.btn-wallet-pay:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-icon {
  font-size: 20px;
}

.shortfall-alert {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 12px;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.25);
}

.shortfall-icon {
  color: #f59e0b;
  font-size: 22px;
  margin-top: 1px;
}

.shortfall-text strong {
  display: block;
  font-size: 13.5px;
  color: var(--ink);
  margin-bottom: 2px;
}

.shortfall-text p {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted);
  line-height: 1.4;
}

.btn-topup-wallet {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 14px 20px;
  border-radius: 12px;
  border: none;
  background: var(--accent, #c9a15a);
  color: #ffffff;
  font-size: 14.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-topup-wallet:hover {
  opacity: 0.92;
  transform: translateY(-1px);
}

.wallet-trust-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding-top: 14px;
  border-top: 1px solid var(--outline);
  font-size: 12px;
  color: var(--muted);
}

.trust-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.trust-icon {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}
</style>
