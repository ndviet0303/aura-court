<script setup lang="ts">
import type { MembershipKpiStats } from '../../types/membership'

defineProps<{
  stats: MembershipKpiStats
}>()

function formatVnd(val: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(val)
}
</script>

<template>
  <div class="membership-kpi-grid">
    <div class="kpi-card">
      <div class="kpi-icon-box gold">
        <span class="material-symbols-outlined">workspace_premium</span>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">Tổng Hội Viên VIP</span>
        <strong class="kpi-value">{{ stats.totalVipMembers }} Thành viên</strong>
        <span class="kpi-trend positive">+14.2% so với tháng trước</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-icon-box rose">
        <span class="material-symbols-outlined">payments</span>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">Doanh Thu Định Kỳ (MRR)</span>
        <strong class="kpi-value">{{ formatVnd(stats.monthlyRecurringRevenue) }}</strong>
        <span class="kpi-trend positive">Gia hạn tự động 92%</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-icon-box purple">
        <span class="material-symbols-outlined">stars</span>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">Điểm Thưởng Tích Lũy</span>
        <strong class="kpi-value">{{ stats.totalPointsIssued.toLocaleString('vi-VN') }} pts</strong>
        <span class="kpi-trend neutral">Hệ số trung bình x1.6</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-icon-box mint">
        <span class="material-symbols-outlined">redeem</span>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">Tỷ Lệ Đổi Thưởng</span>
        <strong class="kpi-value">{{ stats.redemptionRatePercent }}%</strong>
        <span class="kpi-trend positive">Voucher nước & giờ chơi</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.membership-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 20px;
  border-radius: 16px;
  background: var(--surface-container-lowest, #ffffff);
  border: 1px solid var(--outline-variant, #e1e3e4);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
  transition: transform 0.2s, box-shadow 0.2s;
}

:global(html.dark) .kpi-card {
  background: #1e2025;
  border-color: #353942;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
}

.kpi-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-icon-box span {
  font-size: 26px;
}

.kpi-icon-box.gold {
  background: #fef3c7;
  color: #d97706;
}

.kpi-icon-box.rose {
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

.kpi-icon-box.purple {
  background: #ede9fe;
  color: #7c3aed;
}

.kpi-icon-box.mint {
  background: #d1fae5;
  color: #059669;
}

.kpi-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.kpi-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--on-surface-variant, #4f4446);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.kpi-value {
  font-size: 18px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .kpi-value {
  color: #ffffff;
}

.kpi-trend {
  font-size: 11px;
  font-weight: 600;
}

.kpi-trend.positive {
  color: #10b981;
}

.kpi-trend.neutral {
  color: var(--muted, #6c757d);
}

@media (max-width: 1024px) {
  .membership-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .membership-kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>
