<script setup lang="ts">
import type { Transaction, TransactionType } from '../composables/useWallet'

defineProps<{
  transactions: Transaction[]
  filter: TransactionType | 'all'
  venueFilter: string
  searchQuery: string
}>()

const emit = defineEmits<{
  (e: 'update:filter', val: TransactionType | 'all'): void
  (e: 'update:venueFilter', val: string): void
  (e: 'update:searchQuery', val: string): void
  (e: 'deposit'): void
}>()

function formatMoney(amount: number): string {
  const prefix = amount > 0 ? '+' : ''
  return prefix + new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function getIconForType(type: TransactionType): string {
  switch (type) {
    case 'topup':
      return 'arrow_downward'
    case 'booking':
      return 'sports_tennis'
    case 'pos':
      return 'storefront'
    case 'refund':
      return 'replay'
    default:
      return 'receipt'
  }
}
</script>

<template>
  <div class="wallet-transactions-card">
    <!-- Header & Controls -->
    <div class="card-header">
      <div class="title-meta">
        <h3 class="card-title">
          <span class="material-symbols-outlined title-icon">history</span>
          <span>{{ $t('wallet.transactionsTitle') }}</span>
        </h3>
        <p class="card-sub">
          {{ $t('wallet.transactionsSubtitle') }}
        </p>
      </div>

      <div class="filter-controls">
        <!-- Search Input -->
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            type="text"
            :value="searchQuery"
            :placeholder="$t('wallet.searchPlaceholder')"
            class="search-input"
            @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
          />
        </div>

        <!-- Filter Tabs -->
        <div class="pill-tabs">
          <button
            class="p-tab"
            :class="{ active: filter === 'all' }"
            @click="emit('update:filter', 'all')"
          >
            {{ $t('wallet.filterAll') }}
          </button>
          <button
            class="p-tab"
            :class="{ active: filter === 'topup' }"
            @click="emit('update:filter', 'topup')"
          >
            {{ $t('wallet.filterDeposit') }}
          </button>
          <button
            class="p-tab"
            :class="{ active: filter === 'booking' }"
            @click="emit('update:filter', 'booking')"
          >
            {{ $t('wallet.filterPayment') }}
          </button>
          <button
            class="p-tab"
            :class="{ active: filter === 'refund' }"
            @click="emit('update:filter', 'refund')"
          >
            {{ $t('wallet.filterRefund') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="transactions.length === 0" class="empty-state">
      <div class="empty-icon-circle">
        <span class="material-symbols-outlined">receipt_long</span>
      </div>
      <h4 class="empty-title">Không tìm thấy giao dịch nào</h4>
      <p class="empty-desc">Không có giao dịch nào khớp với bộ lọc hoặc từ khóa tìm kiếm của bạn.</p>
      <button class="btn btn-outline" @click="emit('deposit')">
        <span class="material-symbols-outlined">add_card</span>
        <span>Nạp Tiền Vào Ví Ngay</span>
      </button>
    </div>

    <!-- Transactions List -->
    <div v-else class="transactions-list">
      <div
        v-for="t in transactions"
        :key="t.id"
        class="txn-row"
      >
        <!-- Icon -->
        <div class="txn-icon-box" :class="`icon-${t.type}`">
          <span class="material-symbols-outlined">{{ getIconForType(t.type) }}</span>
        </div>

        <!-- Main Info -->
        <div class="txn-details">
          <div class="txn-main-line">
            <strong class="txn-title">{{ t.title }}</strong>
            <span class="txn-status-badge">Hoàn tất</span>
          </div>

          <div class="txn-meta-line">
            <span class="txn-venue">
              <span class="material-symbols-outlined meta-pin">location_on</span>
              <span>{{ t.venue_name }}</span>
            </span>
            <span class="dot-separator">•</span>
            <span class="txn-desc">{{ t.description }}</span>
          </div>

          <div class="txn-time-line font-mono">
            <span>{{ t.date }} Lúc {{ t.time }}</span>
            <span class="dot-separator">•</span>
            <span class="txn-ref">Mã GD: {{ t.reference }}</span>
          </div>
        </div>

        <!-- Amount -->
        <div class="txn-amount-box">
          <strong
            class="txn-amount-val font-mono"
            :class="t.amount > 0 ? 'text-credit' : 'text-debit'"
          >
            {{ formatMoney(t.amount) }}
          </strong>
          <span class="txn-balance-after font-mono">
            Số dư sau GD: {{ formatMoney(t.balance_after) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wallet-transactions-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px;
  border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow-sm);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.title-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.card-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ink);
}

.title-icon {
  color: var(--accent, #C9A15A);
  font-size: 22px;
}

.card-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.filter-controls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 10px;
  font-size: 16px;
  color: var(--muted);
  pointer-events: none;
}

.search-input {
  padding: 7px 12px 7px 32px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-subtle, #f7f3ed);
  color: var(--ink);
  font-size: 12.5px;
  outline: none;
  width: 190px;
  transition: all 0.15s ease;
}

:global(html.dark) .search-input {
  background: var(--surface-subtle, #262024);
}

.search-input:focus {
  border-color: var(--accent, #C9A15A);
  width: 220px;
}

.pill-tabs {
  display: flex;
  gap: 4px;
  background: var(--surface-subtle, #f7f3ed);
  padding: 3px;
  border-radius: 10px;
  border: 1px solid var(--border);
}

:global(html.dark) .pill-tabs {
  background: var(--surface-subtle, #262024);
}

.p-tab {
  padding: 6px 12px;
  border-radius: 7px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.p-tab:hover {
  color: var(--ink);
}

.p-tab.active {
  background: var(--surface);
  color: var(--primary, #6B2D3F);
  font-weight: 700;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

:global(html.dark) .p-tab.active {
  color: var(--primary, #E58E9F);
}

/* Transactions List */
.transactions-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.txn-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 18px;
  border-radius: 14px;
  border: 1px solid var(--border);
  background: var(--surface);
  transition: border-color 0.15s ease, transform 0.15s ease;
}

.txn-row:hover {
  border-color: var(--outline);
  transform: translateX(2px);
}

.txn-icon-box {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.txn-icon-box .material-symbols-outlined {
  font-size: 20px;
}

.icon-topup {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.icon-booking {
  background: var(--primary-container, rgba(107, 45, 63, 0.1));
  color: var(--primary, #6B2D3F);
}

:global(html.dark) .icon-booking {
  color: var(--primary, #E58E9F);
}

.icon-pos {
  background: var(--accent-container, rgba(201, 161, 90, 0.12));
  color: var(--on-accent-container, #855e1a);
}

:global(html.dark) .icon-pos {
  color: var(--accent, #D8B46E);
}

.icon-refund {
  background: rgba(59, 130, 246, 0.12);
  color: #3b82f6;
}

.txn-details {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  min-width: 0;
}

.txn-main-line {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.txn-title {
  font-size: 13.5px;
  color: var(--ink);
}

.txn-status-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.txn-meta-line {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.txn-venue {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  color: var(--ink);
  font-weight: 500;
}

.meta-pin {
  font-size: 13px;
  color: var(--accent, #C9A15A);
}

.txn-time-line {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--muted);
}

.dot-separator {
  color: var(--outline);
}

.txn-amount-box {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  flex-shrink: 0;
}

.txn-amount-val {
  font-size: 16px;
}

.text-credit {
  color: #10b981;
}

.text-debit {
  color: var(--ink);
}

.txn-balance-after {
  font-size: 10.5px;
  color: var(--muted);
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  gap: 12px;
  text-align: center;
}

.empty-icon-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--surface-subtle, #f7f3ed);
  color: var(--muted);
  border: 1px solid var(--border);
}

:global(html.dark) .empty-icon-circle {
  background: var(--surface-subtle, #262024);
}

.empty-icon-circle .material-symbols-outlined {
  font-size: 28px;
}

.empty-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

.empty-desc {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted);
  max-width: 320px;
}

@media (max-width: 768px) {
  .wallet-transactions-card {
    padding: 20px 16px;
  }
  .filter-controls {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
  }
  .search-input {
    width: 100%;
  }
  .pill-tabs {
    overflow-x: auto;
  }
  .txn-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
  .txn-amount-box {
    align-items: flex-start;
    width: 100%;
    border-top: 1px dashed var(--border);
    padding-top: 8px;
  }
}
</style>
