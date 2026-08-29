<script setup lang="ts">
import type { MonthlyInvoiceData } from '../../composables/usePaymentCycleDetail'

defineProps<{
  invoice: MonthlyInvoiceData
}>()

const emit = defineEmits<{
  (e: 'openPay'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="cycle-summary-card">
    <div class="summary-top-row">
      <div class="court-info">
        <span class="series-tag font-mono">HÓA ĐƠN KỲ #{{ invoice.id }}</span>
        <h2 class="court-title">{{ invoice.series?.court?.name || 'Sân Cố Định VIP 01' }}</h2>
        <p class="venue-sub">📍 {{ invoice.series?.court?.venue?.name }} · {{ invoice.series?.court?.venue?.address }}</p>
      </div>

      <div class="status-box">
        <span
          class="invoice-status-badge"
          :class="invoice.status === 'paid' ? 'status-paid' : 'status-pending'"
        >
          {{ invoice.status === 'paid' ? 'ĐÃ THANH TOÁN' : 'CHỜ THANH TOÁN' }}
        </span>
      </div>
    </div>

    <!-- Payment Summary Box -->
    <div class="payment-overview-box">
      <h3 class="box-title">Tóm Tắt Thanh Toán</h3>
      <div class="overview-grid">
        <div class="o-item">
          <span class="lbl">Kỳ thanh toán:</span>
          <strong>{{ invoice.period_start.slice(0, 10) }} ➔ {{ invoice.period_end.slice(0, 10) }}</strong>
        </div>

        <div class="o-item">
          <span class="lbl">Hạn chót đóng tiền:</span>
          <strong class="text-red">{{ invoice.due_at.slice(0, 10) }}</strong>
        </div>

        <div class="o-item">
          <span class="lbl">Tần suất lịch chơi:</span>
          <strong>{{ invoice.series?.frequency === 'weekly' ? 'Hàng tuần' : 'Định kỳ' }}</strong>
        </div>

        <div class="o-item">
          <span class="lbl">Tổng tiền kỳ này:</span>
          <strong class="total-amount text-pink">{{ formatMoney(invoice.amount) }}</strong>
        </div>
      </div>

      <div v-if="invoice.status !== 'paid'" class="pay-action-row">
        <button class="btn-action-primary" @click="emit('openPay')">
          💳 Thanh Toán VietQR Ngay
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cycle-summary-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.summary-top-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.series-tag {
  font-size: 11px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
}

.court-title {
  margin: 6px 0 2px;
  font-size: 20px;
  font-weight: 800;
}

.venue-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.invoice-status-badge {
  font-size: 12px;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 20px;
}

.status-paid { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

.payment-overview-box {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.box-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.o-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.lbl {
  font-size: 11px;
  color: var(--muted);
}

.total-amount {
  font-size: 18px;
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-red { color: #ef4444; }

.pay-action-row {
  display: flex;
  justify-content: flex-end;
  padding-top: 10px;
  border-top: 1px solid var(--outline);
}

.btn-action-primary {
  padding: 10px 24px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
</style>
