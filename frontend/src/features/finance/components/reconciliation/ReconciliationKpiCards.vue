<script setup lang="ts">
import type { ReconciliationStats } from '../../types/reconciliation'

defineProps<{
  stats: ReconciliationStats
}>()

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)
}
</script>

<template>
  <div class="kpi-grid">
    <!-- Card 1: Cổng Thanh toán PSP -->
    <div class="kpi-card group">
      <div class="card-glow primary-glow"></div>
      <div class="card-header">
        <div class="card-title-wrap">
          <span class="material-symbols-outlined icon-type">account_balance_wallet</span>
          <span class="card-label">CỔNG THANH TOÁN (PSP)</span>
        </div>
        <span class="material-symbols-outlined status-icon-ok">check_circle</span>
      </div>
      <div class="card-value">{{ formatCurrency(stats.provider_total) }}</div>
      <div class="card-footer">
        <span class="pulse-dot-green"></span>
        <span class="footer-text">1,842 giao dịch · Đã khớp nội bộ</span>
      </div>
    </div>

    <!-- Card 2: Sổ cái nội bộ -->
    <div class="kpi-card group">
      <div class="card-glow primary-glow"></div>
      <div class="card-header">
        <div class="card-title-wrap">
          <span class="material-symbols-outlined icon-type">menu_book</span>
          <span class="card-label">SỔ CÁI NỘI BỘ</span>
        </div>
        <span class="material-symbols-outlined status-icon-ok">verified</span>
      </div>
      <div class="card-value">{{ formatCurrency(stats.journal_total) }}</div>
      <div class="card-footer">
        <span class="pulse-dot-green"></span>
        <span class="footer-text">Trạng thái cân đối 100%</span>
      </div>
    </div>

    <!-- Card 3: Tài khoản quyết toán ngân hàng -->
    <div class="kpi-card group" :class="{ 'warning-card': stats.mismatch_total > 0 }">
      <div class="card-glow error-glow"></div>
      <div class="card-header">
        <div class="card-title-wrap">
          <span class="material-symbols-outlined icon-type">account_balance</span>
          <span class="card-label">TÀI KHOẢN QUYẾT TOÁN</span>
        </div>
        <span class="material-symbols-outlined status-icon-warn">error</span>
      </div>
      <div class="card-value text-error">{{ formatCurrency(stats.settlement_total) }}</div>
      <div class="card-footer">
        <span class="pulse-dot-red"></span>
        <span class="footer-text text-error font-medium">
          Chênh lệch: -{{ formatCurrency(stats.mismatch_total) }} (Treo 1 GD)
        </span>
      </div>
    </div>

    <!-- Card 4: Chi hộ & Rút tiền -->
    <div class="kpi-card group">
      <div class="card-glow primary-glow"></div>
      <div class="card-header">
        <div class="card-title-wrap">
          <span class="material-symbols-outlined icon-type">payments</span>
          <span class="card-label">QUỸ RÚT & CHI HỘ</span>
        </div>
        <span class="material-symbols-outlined status-icon-ok">task_alt</span>
      </div>
      <div class="card-value">{{ formatCurrency(stats.payout_total) }}</div>
      <div class="card-footer">
        <span class="pulse-dot-green"></span>
        <span class="footer-text">142 lệnh hoàn tất · 2 lệnh tạm giữ</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

@media (max-width: 1100px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  position: relative;
  background: #ffffff;
  border-radius: 16px;
  padding: 20px;
  border: 1px solid #e1e3e4;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(120, 85, 94, 0.08);
  border-color: #ffd1dc;
}

.warning-card {
  border-color: #ffdad6;
  background: linear-gradient(180deg, #ffffff 0%, #fffbfa 100%);
}

.warning-card:hover {
  border-color: #ba1a1a;
}

.card-glow {
  position: absolute;
  top: 0;
  right: 0;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  filter: blur(35px);
  opacity: 0.4;
  pointer-events: none;
}

.primary-glow {
  background: #ffd1dc;
}

.error-glow {
  background: #ffdad6;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.icon-type {
  font-size: 20px;
  color: #78555e;
}

.card-label {
  font-size: 11px;
  font-weight: 700;
  color: #5d5f5f;
  letter-spacing: 0.06em;
}

.status-icon-ok {
  font-size: 18px;
  color: #059669;
  font-variation-settings: 'FILL' 1;
}

.status-icon-warn {
  font-size: 18px;
  color: #ba1a1a;
  font-variation-settings: 'FILL' 1;
}

.card-value {
  font-size: 24px;
  font-weight: 700;
  color: #191c1d;
  letter-spacing: -0.02em;
}

.text-error {
  color: #ba1a1a !important;
}

.card-footer {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #5d5f5f;
  margin-top: 2px;
}

.pulse-dot-green {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}

.pulse-dot-red {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #ba1a1a;
  box-shadow: 0 0 0 2px rgba(186, 26, 26, 0.2);
}
</style>
