<script setup lang="ts">
import type { SplitItemDetail } from '../../composables/useGroupSplitBill'

defineProps<{
  bookingCode: string
  courtName: string
  venueName: string
  bookingDate: string
  bookingTime: string
  items: SplitItemDetail[]
  totalBill: number
  totalPaid: number
  remainingAmount: number
  progressPercent: number
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="split-summary-card">
    <div class="booking-top-info">
      <div>
        <span class="code-pill font-mono">MÃ ĐƠN: {{ bookingCode }}</span>
        <h2 class="court-title">{{ courtName }}</h2>
        <p class="venue-sub">📍 {{ venueName }} · {{ bookingDate }} ({{ bookingTime }})</p>
      </div>

      <div class="total-badge-box">
        <span class="lbl">Tổng Hóa Đơn:</span>
        <strong class="total-num text-pink">{{ formatMoney(totalBill) }}</strong>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="progress-section">
      <div class="progress-labels">
        <span>Tiến độ thanh toán nhóm: <strong>{{ progressPercent }}%</strong></span>
        <span>Đã thu: <strong>{{ formatMoney(totalPaid) }}</strong> / Còn lại: <strong class="text-red">{{ formatMoney(remainingAmount) }}</strong></span>
      </div>
      <div class="progress-track">
        <div class="progress-fill" :style="{ width: `${progressPercent}%` }"></div>
      </div>
    </div>

    <!-- Order Items List -->
    <div class="items-list-box">
      <h4 class="items-title">Chi Tiết Hóa Đơn Sân & Dịch Vụ:</h4>
      <div class="items-rows">
        <div v-for="item in items" :key="item.id" class="item-row">
          <span>{{ item.name }} (x{{ item.qty }})</span>
          <strong>{{ formatMoney(item.total) }}</strong>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.split-summary-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.booking-top-info {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.code-pill {
  font-size: 11px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
}

.court-title {
  margin: 6px 0 2px;
  font-size: 18px;
  font-weight: 800;
}

.venue-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.total-badge-box {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.lbl {
  font-size: 11px;
  color: var(--muted);
}

.total-num {
  font-size: 22px;
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-red { color: #ef4444; }

.progress-section {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
}

.progress-track {
  width: 100%;
  height: 8px;
  border-radius: 4px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 4px;
  background: #10b981;
  transition: width 0.3s;
}

.items-list-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.items-title {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.items-rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.item-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}
</style>
