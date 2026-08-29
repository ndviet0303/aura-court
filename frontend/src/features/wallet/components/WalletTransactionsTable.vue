<script setup lang="ts">
import type { Transaction } from '../composables/useWallet'

defineProps<{
  transactions: Transaction[]
  filter: 'all' | 'expense' | 'topup' | 'refund'
  venueFilter: string
}>()

const emit = defineEmits<{
  (e: 'update:filter', val: 'all' | 'expense' | 'topup' | 'refund'): void
  (e: 'update:venueFilter', val: string): void
}>()

function formatMoney(amount: number): string {
  const prefix = amount > 0 ? '+' : ''
  return prefix + new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="wallet-transactions-card">
    <div class="card-header">
      <div class="title-meta">
        <h3 class="title">📜 Lịch Sử Giao Dịch Ví</h3>
        <p class="sub">Biến động số dư, nạp tiền, thanh toán đặt sân và chi tiêu POS tại quầy.</p>
      </div>

      <div class="filters-row">
        <div class="pill-tabs">
          <button
            class="p-tab"
            :class="{ active: filter === 'all' }"
            @click="emit('update:filter', 'all')"
          >
            Tất cả
          </button>
          <button
            class="p-tab"
            :class="{ active: filter === 'topup' }"
            @click="emit('update:filter', 'topup')"
          >
            Nạp tiền
          </button>
          <button
            class="p-tab"
            :class="{ active: filter === 'expense' }"
            @click="emit('update:filter', 'expense')"
          >
            Chi tiêu
          </button>
        </div>
      </div>
    </div>

    <div v-if="transactions.length === 0" class="empty-txns">
      Không có giao dịch nào phù hợp.
    </div>

    <div v-else class="transactions-list">
      <div v-for="t in transactions" :key="t.id" class="txn-row">
        <div class="txn-icon" :class="t.amount > 0 ? 'icon-plus' : 'icon-minus'">
          {{ t.amount > 0 ? '↓' : '↑' }}
        </div>

        <div class="txn-info">
          <strong class="txn-title">{{ t.title }}</strong>
          <span class="txn-sub">{{ t.venue_name }} · {{ t.description }}</span>
          <span class="txn-time font-mono">{{ t.date }} {{ t.time }}</span>
        </div>

        <strong
          class="txn-amount font-mono"
          :class="t.amount > 0 ? 'text-green' : 'text-pink'"
        >
          {{ formatMoney(t.amount) }}
        </strong>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wallet-transactions-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.title-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.pill-tabs {
  display: flex;
  gap: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  padding: 3px;
  border-radius: 8px;
}

.p-tab {
  padding: 6px 12px;
  border-radius: 6px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.p-tab.active {
  background: var(--surface);
  color: var(--pink-dark, #db2777);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.transactions-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.txn-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.txn-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 800;
}

.icon-plus { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.icon-minus { background: rgba(236, 72, 153, 0.1); color: var(--pink-dark, #db2777); }

.txn-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.txn-title {
  font-size: 13px;
}

.txn-sub {
  font-size: 11px;
  color: var(--muted);
}

.txn-time {
  font-size: 10px;
  color: var(--muted);
}

.txn-amount {
  font-size: 15px;
}

.text-green { color: #10b981; }
.text-pink { color: var(--pink-dark, #db2777); }

.empty-txns {
  padding: 30px;
  text-align: center;
  font-size: 12px;
  color: var(--muted);
}
</style>
