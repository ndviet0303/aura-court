<script setup lang="ts">
import type { AdminReport } from '../../composables/useAdminDashboard'

defineProps<{
  report: AdminReport
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="admin-kpi-grid">
    <div class="kpi-card">
      <span class="kpi-icon">💰</span>
      <span class="kpi-title">Tổng Doanh Thu</span>
      <strong class="kpi-value text-pink">{{ formatMoney(report.revenue) }}</strong>
      <span class="kpi-sub text-green">↑ 14.2% so với tháng trước</span>
    </div>

    <div class="kpi-card">
      <span class="kpi-icon">🏸</span>
      <span class="kpi-title">Lượt Đặt Sân</span>
      <strong class="kpi-value">{{ report.bookings }}</strong>
      <span class="kpi-sub">{{ report.confirmed }} đơn thành công</span>
    </div>

    <div class="kpi-card">
      <span class="kpi-icon">📈</span>
      <span class="kpi-title">Tỷ Lệ Lấp Đầy Sân</span>
      <strong class="kpi-value text-green">{{ report.occupancyRate || 85 }}%</strong>
      <span class="kpi-sub">Công suất trung bình cả tuần</span>
    </div>

    <div class="kpi-card">
      <span class="kpi-icon">🔄</span>
      <span class="kpi-title">Yêu Cầu Hoàn Tiền</span>
      <strong class="kpi-value text-yellow">{{ report.pendingRefunds }}</strong>
      <span class="kpi-sub">Chờ kế toán xác nhận</span>
    </div>
  </div>
</template>

<style scoped>
.admin-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.kpi-icon {
  font-size: 24px;
  margin-bottom: 2px;
}

.kpi-title {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.kpi-value {
  font-size: 24px;
  font-weight: 800;
}

.kpi-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }

@media (max-width: 900px) {
  .admin-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
