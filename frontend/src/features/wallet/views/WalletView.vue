<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useWallet, type VenueWallet } from '../composables/useWallet'

import WalletTotalBalanceCard from '../components/WalletTotalBalanceCard.vue'
import VenueWalletsList from '../components/VenueWalletsList.vue'
import WalletTransactionsTable from '../components/WalletTransactionsTable.vue'
import WalletDepositModal from '../components/WalletDepositModal.vue'
import WalletPayQrModal from '../components/WalletPayQrModal.vue'

const route = useRoute()
const organizationId =
  route.query.organization_id === undefined ? undefined : Number(route.query.organization_id)

const {
  loading,
  processing,
  notice,
  error,
  transactionFilter,
  selectedVenueFilter,
  searchQuery,
  isDepositModalOpen,
  depositStep,
  isQrModalOpen,
  selectedWalletForAction,
  depositAmount,
  depositMethod,
  depositTargetWalletId,
  cardNumber,
  cardExpiry,
  cardCvc,
  cardName,
  quickAmounts,
  wallets,
  transactions,
  totalBalance,
  totalPoints,
  filteredTransactions,
  transferInfo,
  loadWalletData,
  openDepositModal,
  openQrModal,
  proceedToPaymentStep,
  confirmDeposit,
  copyToClipboard,
} = useWallet(organizationId)

function scrollToHistory() {
  const el = document.getElementById('wallet-history-section')
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
  }
}
</script>

<template>
  <div class="wallet-view-page">
    <!-- Header Section -->
    <header class="wallet-header">
      <div class="header-titles">
        <div class="category-eyebrow">
          <span class="material-symbols-outlined eyebrow-icon">account_balance_wallet</span>
          <span class="font-mono">{{ $t('wallet.membershipEyebrow') }}</span>
        </div>
        <h1 class="page-title">{{ $t('wallet.title') }}</h1>
        <p class="page-sub">
          {{ $t('wallet.subtitle') }}
        </p>
      </div>

      <div class="header-actions">
        <button
          class="btn btn-outline btn-refresh"
          :disabled="loading"
          :title="$t('wallet.refreshBalance')"
          @click="loadWalletData"
        >
          <span class="material-symbols-outlined" :class="{ spinning: loading }">refresh</span>
          <span>{{ $t('wallet.refresh') }}</span>
        </button>
        <button class="btn btn-primary" @click="openDepositModal()">
          <span class="material-symbols-outlined">add_card</span>
          <span>{{ $t('wallet.depositBtn') }}</span>
        </button>
      </div>
    </header>

    <!-- Feedback Notice Toast -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success">
        <span class="material-symbols-outlined alert-icon">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error">
        <span class="material-symbols-outlined alert-icon">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Total System Balance Card -->
    <WalletTotalBalanceCard
      :total-balance="totalBalance"
      :total-points="totalPoints"
      :wallets-count="wallets.length"
      @deposit="openDepositModal()"
      @qr="openQrModal()"
      @scroll-history="scrollToHistory"
    />

    <!-- Individual Venue Passes & Wallets List -->
    <VenueWalletsList :wallets="wallets" @deposit="openDepositModal" @qr="openQrModal" />

    <!-- Transaction History Table -->
    <div id="wallet-history-section">
      <WalletTransactionsTable
        :transactions="filteredTransactions"
        :filter="transactionFilter"
        :venue-filter="selectedVenueFilter"
        :search-query="searchQuery"
        @update:filter="transactionFilter = $event"
        @update:venue-filter="selectedVenueFilter = $event"
        @update:search-query="searchQuery = $event"
        @deposit="openDepositModal()"
      />
    </div>

    <!-- Security & Trust Badges -->
    <section class="trust-security-section">
      <div class="trust-card">
        <div class="trust-icon-box">
          <span class="material-symbols-outlined">bolt</span>
        </div>
        <div class="trust-content">
          <h4 class="trust-title">Nạp Tiền Tức Thì 24/7</h4>
          <p class="trust-desc">
            Kết nối trực tiếp hệ thống VietQR Napas247, số dư tự động cộng vào ví chỉ sau 3 giây, kể
            cả ngày nghỉ và lễ Tết.
          </p>
        </div>
      </div>

      <div class="trust-card">
        <div class="trust-icon-box">
          <span class="material-symbols-outlined">security</span>
        </div>
        <div class="trust-content">
          <h4 class="trust-title">Bảo Mật Chuẩn Ngân Hàng</h4>
          <p class="trust-desc">
            Toàn bộ giao dịch được mã hóa 256-bit SSL và định danh khách hàng an toàn theo tiêu
            chuẩn bảo mật PCI-DSS.
          </p>
        </div>
      </div>

      <div class="trust-card">
        <div class="trust-icon-box">
          <span class="material-symbols-outlined">assignment_return</span>
        </div>
        <div class="trust-content">
          <h4 class="trust-title">Hoàn Tiền 100% Tự Động</h4>
          <p class="trust-desc">
            Khi huỷ lịch đặt sân hợp lệ theo quy định trước giờ thi đấu, tiền được hoàn trả 100% về
            ví trong vòng 1 giây.
          </p>
        </div>
      </div>
    </section>

    <!-- Modals -->
    <WalletDepositModal
      :show="isDepositModalOpen"
      :wallets="wallets"
      :target-wallet-id="depositTargetWalletId"
      :amount="depositAmount"
      :method="depositMethod"
      :quick-amounts="quickAmounts"
      :step="depositStep"
      :card-number="cardNumber"
      :card-expiry="cardExpiry"
      :card-cvc="cardCvc"
      :card-name="cardName"
      :transfer-info="transferInfo"
      :processing="processing"
      @close="isDepositModalOpen = false"
      @update:target-wallet-id="depositTargetWalletId = $event"
      @update:amount="depositAmount = $event"
      @update:method="depositMethod = $event"
      @update:card-number="cardNumber = $event"
      @update:card-expiry="cardExpiry = $event"
      @update:card-cvc="cardCvc = $event"
      @update:card-name="cardName = $event"
      @proceed="proceedToPaymentStep"
      @confirm="confirmDeposit"
      @back="depositStep = 'select'"
      @copy="copyToClipboard"
    />

    <WalletPayQrModal
      :show="isQrModalOpen"
      :wallet="selectedWalletForAction"
      @close="isQrModalOpen = false"
      @copy="copyToClipboard"
    />
  </div>
</template>

<style scoped>
.wallet-view-page {
  display: flex;
  flex-direction: column;
  gap: 28px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px 24px 60px;
  color: var(--ink);
}

.wallet-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 18px;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 720px;
}

.category-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 800;
  color: var(--accent, #c9a15a);
  letter-spacing: 0.08em;
}

.eyebrow-icon {
  font-size: 16px;
}

.page-title {
  margin: 0;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ink);
  line-height: 1.2;
}

.page-sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
  line-height: 1.5;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-refresh {
  padding: 8px 14px;
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 12px;
  background: #10b981;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 600;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 12px;
  background: #ef4444;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 600;
  box-shadow: 0 4px 14px rgba(239, 68, 68, 0.25);
}

.alert-icon {
  font-size: 18px;
}

/* Trust & Security Section */
.trust-security-section {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
  margin-top: 8px;
}

.trust-card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow-sm);
}

.trust-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: var(--surface-subtle, #f7f3ed);
  color: var(--accent, #c9a15a);
  border: 1px solid var(--border);
  font-size: 20px;
  flex-shrink: 0;
}

:global(html.dark) .trust-icon-box {
  background: var(--surface-subtle, #262024);
}

.trust-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.trust-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.trust-desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.45;
}

.spinning {
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

.fade-enter-active,
.fade-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (max-width: 860px) {
  .wallet-view-page {
    padding: 20px 16px 40px;
    gap: 20px;
  }
  .page-title {
    font-size: 22px;
  }
  .header-actions {
    width: 100%;
  }
  .header-actions .btn {
    flex: 1;
  }
  .trust-security-section {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}
</style>
