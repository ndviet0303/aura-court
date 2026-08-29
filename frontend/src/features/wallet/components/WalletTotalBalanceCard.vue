<script setup lang="ts">
defineProps<{
  totalBalance: number
  walletsCount: number
}>()

const emit = defineEmits<{
  (e: 'deposit'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="total-balance-card">
    <div class="balance-left">
      <span class="badge font-mono">💳 AURA MEMBERSHIP MULTI-WALLET</span>
      <span class="lbl">Tổng Số Dư Khả Dụng Toàn Hệ Thống:</span>
      <strong class="total-amount text-pink font-mono">{{ formatMoney(totalBalance) }}</strong>
      <span class="sub">Tích hợp qua {{ walletsCount }} cụm sân đối tác Aura</span>
    </div>

    <div class="balance-actions">
      <button class="btn-topup" @click="emit('deposit')">
        ➕ Nạp Tiền Vào Ví
      </button>
    </div>
  </div>
</template>

<style scoped>
.total-balance-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  padding: 24px 28px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.balance-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.badge {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
  width: fit-content;
}

.lbl {
  font-size: 12px;
  color: var(--muted);
  margin-top: 4px;
}

.total-amount {
  font-size: 32px;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }

.btn-topup {
  padding: 12px 24px;
  border-radius: 12px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.25);
}
</style>
