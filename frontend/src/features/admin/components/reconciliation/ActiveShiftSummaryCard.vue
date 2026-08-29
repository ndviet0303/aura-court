<script setup lang="ts">
import type { CashShiftItem } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  shift: CashShiftItem
}>()

const emit = defineEmits<{
  (e: 'openDenominations'): void
  (e: 'openCashDrops'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="active-shift-card">
    <div class="shift-top-row">
      <div class="shift-headline">
        <span class="live-pulse">● ĐANG HOẠT ĐỘNG</span>
        <h3 class="shift-title">{{ shift.shift_name }}</h3>
        <span class="shift-venue">📍 {{ shift.venue_name }} · Thu ngân: <strong>{{ shift.cashier_name }}</strong></span>
      </div>

      <div class="shift-quick-actions">
        <button class="btn-action-outline" @click="emit('openCashDrops')">
          💸 Rút Tiền Két ({{ shift.cash_drops.length }})
        </button>
        <button class="btn-action-primary" @click="emit('openDenominations')">
          💵 Kiểm Đếm Tiền Mặt
        </button>
      </div>
    </div>

    <div class="shift-metrics-grid">
      <div class="metric-box">
        <span class="metric-lbl">Tiền Mặt Đầu Ca:</span>
        <strong class="metric-val">{{ formatMoney(shift.opening_cash) }}</strong>
      </div>

      <div class="metric-box">
        <span class="metric-lbl">Doanh Thu Tiền Mặt:</span>
        <strong class="metric-val text-green">+{{ formatMoney(shift.cash_sales) }}</strong>
      </div>

      <div class="metric-box">
        <span class="metric-lbl">Thanh Toán QR / Ví:</span>
        <strong class="metric-val text-blue">{{ formatMoney(shift.qr_sales + shift.wallet_sales) }}</strong>
      </div>

      <div class="metric-box">
        <span class="metric-lbl">Tổng Doanh Số Ca:</span>
        <strong class="metric-val text-pink">{{ formatMoney(shift.total_sales) }}</strong>
      </div>
    </div>

    <div class="shift-variance-bar" :class="{ 'has-diff': shift.variance !== 0 }">
      <div class="variance-item">
        <span>Tiền mặt dự kiến két:</span>
        <strong>{{ formatMoney(shift.expected_cash) }}</strong>
      </div>
      <div class="variance-item">
        <span>Tiền mặt thực tế đếm:</span>
        <strong>{{ formatMoney(shift.declared_cash) }}</strong>
      </div>
      <div class="variance-item">
        <span>Chênh lệch đối soát:</span>
        <strong :class="shift.variance < 0 ? 'text-red' : shift.variance > 0 ? 'text-yellow' : 'text-green'">
          {{ shift.variance > 0 ? '+' : '' }}{{ formatMoney(shift.variance) }}
        </strong>
      </div>
    </div>
  </div>
</template>

<style scoped>
.active-shift-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.shift-top-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.live-pulse {
  font-size: 11px;
  font-weight: 800;
  color: #10b981;
}

.shift-title {
  margin: 4px 0;
  font-size: 18px;
  font-weight: 800;
}

.shift-venue {
  font-size: 12px;
  color: var(--muted);
}

.shift-quick-actions {
  display: flex;
  gap: 8px;
}

.btn-action-outline {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-action-primary {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.shift-metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.metric-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.metric-lbl {
  font-size: 11px;
  color: var(--muted);
}

.metric-val {
  font-size: 15px;
}

.text-green { color: #10b981; }
.text-blue { color: #3b82f6; }
.text-pink { color: var(--pink-dark, #db2777); }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }

.shift-variance-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  font-size: 13px;
  flex-wrap: wrap;
  gap: 10px;
}

.variance-item {
  display: flex;
  gap: 6px;
  align-items: center;
}

@media (max-width: 800px) {
  .shift-metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
