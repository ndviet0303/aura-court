<script setup lang="ts">
import { useWallet, type VenueWallet } from '../composables/useWallet'

import WalletTotalBalanceCard from '../components/WalletTotalBalanceCard.vue'
import VenueWalletsList from '../components/VenueWalletsList.vue'
import WalletTransactionsTable from '../components/WalletTransactionsTable.vue'
import WalletDepositModal from '../components/WalletDepositModal.vue'
import WalletPayQrModal from '../components/WalletPayQrModal.vue'

const {
  loading,
  processing,
  notice,
  error,
  transactionFilter,
  selectedVenueFilter,
  isDepositModalOpen,
  isQrModalOpen,
  selectedWalletForAction,
  depositAmount,
  depositMethod,
  depositTargetWalletId,
  quickAmounts,
  wallets,
  totalBalance,
  filteredTransactions,
  openDepositModal,
  openQrModal,
  confirmDeposit,
} = useWallet()
</script>

<template>
  <div class="wallet-view-page">
    <!-- Header Section -->
    <header class="wallet-header">
      <div class="header-titles">
        <h1 class="page-title">💳 Ví Hội Viên Đa Cụm Sân (Multi-Venue Wallet)</h1>
        <p class="page-sub">Quản lý số dư trả trước, nạp tiền tự động qua VietQR và thanh toán không tiền mặt tại tất cả cụm sân Aura.</p>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Total Balance Card -->
    <WalletTotalBalanceCard
      :total-balance="totalBalance"
      :wallets-count="wallets.length"
      @deposit="openDepositModal()"
    />

    <!-- Individual Venue Wallets -->
    <VenueWalletsList
      :wallets="wallets"
      @deposit="openDepositModal"
      @qr="openQrModal"
    />

    <!-- Transaction History Table -->
    <WalletTransactionsTable
      :transactions="filteredTransactions"
      :filter="transactionFilter"
      :venue-filter="selectedVenueFilter"
      @update:filter="transactionFilter = $event"
      @update:venue-filter="selectedVenueFilter = $event"
    />

    <!-- Modals -->
    <WalletDepositModal
      :show="isDepositModalOpen"
      :wallets="wallets"
      :target-wallet-id="depositTargetWalletId"
      :amount="depositAmount"
      :method="depositMethod"
      :quick-amounts="quickAmounts"
      :processing="processing"
      @close="isDepositModalOpen = false"
      @update:target-wallet-id="depositTargetWalletId = $event"
      @update:amount="depositAmount = $event"
      @update:method="depositMethod = $event"
      @confirm="confirmDeposit"
    />

    <WalletPayQrModal
      :show="isQrModalOpen"
      :wallet="selectedWalletForAction"
      @close="isQrModalOpen = false"
    />
  </div>
</template>

<style scoped>
.wallet-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.wallet-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}
</style>
