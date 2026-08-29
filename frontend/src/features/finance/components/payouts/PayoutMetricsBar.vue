<script setup lang="ts">
defineProps<{
  gross: number
  fee: number
  net: number
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="payout-metrics-grid">
    <div class="metric-card gross-box">
      <span class="m-icon">📊</span>
      <span class="m-lbl">TỔNG DOANH THU (GROSS)</span>
      <strong class="m-val">{{ formatMoney(gross) }}</strong>
      <span class="m-sub text-green">Doanh thu trước khấu trừ</span>
    </div>

    <div class="metric-card fee-box">
      <span class="m-icon">🏷️</span>
      <span class="m-lbl">PHÍ & HOA HỒNG (FEES)</span>
      <strong class="m-val text-red">-{{ formatMoney(fee) }}</strong>
      <span class="m-sub">Phí sàn 3% & Cổng thanh toán 2%</span>
    </div>

    <div class="metric-card net-box">
      <span class="m-icon">💰</span>
      <span class="m-lbl">THỰC NHẬN (NET SETTLEMENT)</span>
      <strong class="m-val text-pink">{{ formatMoney(net) }}</strong>
      <span class="m-sub text-green">Khả dụng rút về tài khoản</span>
    </div>
  </div>
</template>

<style scoped>
.payout-metrics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.metric-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 18px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.m-icon {
  font-size: 24px;
}

.m-lbl {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  letter-spacing: 0.5px;
}

.m-val {
  font-size: 22px;
}

.m-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-red { color: #ef4444; }

@media (max-width: 800px) {
  .payout-metrics-grid {
    grid-template-columns: 1fr;
  }
}
</style>
