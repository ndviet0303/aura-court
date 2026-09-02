<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ReconciliationStats } from '../../types/reconciliation'

defineProps<{
  stats: ReconciliationStats
  loading?: boolean
}>()

const { t } = useI18n()

function formatCurrency(amount?: number): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount || 0)
}
</script>

<template>
  <div class="kpi-grid">
    <!-- Skeleton Loading State -->
    <template v-if="loading">
      <div v-for="i in 4" :key="i" class="kpi-card skeleton-card">
        <div class="skeleton-header">
          <div class="skeleton-icon-bar"></div>
          <div class="skeleton-pill"></div>
        </div>
        <div class="skeleton-value"></div>
        <div class="skeleton-footer"></div>
      </div>
    </template>

    <!-- Real Content -->
    <template v-else>
      <!-- Card 1: Cổng Thanh toán PSP -->
      <div class="kpi-card group">
        <div class="card-glow primary-glow"></div>
        <div class="card-header">
          <div class="card-title-wrap">
            <span class="material-symbols-outlined icon-type">account_balance_wallet</span>
            <span class="card-label">{{ t('admin.reconciliation.kpiPspTitle') }}</span>
          </div>
          <span class="material-symbols-outlined status-icon-ok">check_circle</span>
        </div>
        <div class="card-value">{{ formatCurrency(stats.provider_total) }}</div>
        <div class="card-footer">
          <span class="pulse-dot-green"></span>
          <span class="footer-text">{{ t('admin.reconciliation.kpiPspSubtitle') }}</span>
        </div>
      </div>

      <!-- Card 2: Sổ cái nội bộ -->
      <div class="kpi-card group">
        <div class="card-glow primary-glow"></div>
        <div class="card-header">
          <div class="card-title-wrap">
            <span class="material-symbols-outlined icon-type">menu_book</span>
            <span class="card-label">{{ t('admin.reconciliation.kpiLedgerTitle') }}</span>
          </div>
          <span class="material-symbols-outlined status-icon-ok">verified</span>
        </div>
        <div class="card-value">{{ formatCurrency(stats.journal_total) }}</div>
        <div class="card-footer">
          <span class="pulse-dot-green"></span>
          <span class="footer-text">{{ t('admin.reconciliation.kpiLedgerSubtitle') }}</span>
        </div>
      </div>

      <!-- Card 3: Tài khoản quyết toán ngân hàng -->
      <div class="kpi-card group" :class="{ 'warning-card': stats.mismatch_total > 0 }">
        <div class="card-glow error-glow"></div>
        <div class="card-header">
          <div class="card-title-wrap">
            <span class="material-symbols-outlined icon-type">account_balance</span>
            <span class="card-label">{{ t('admin.reconciliation.kpiSettlementTitle') }}</span>
          </div>
          <span v-if="stats.mismatch_total > 0" class="material-symbols-outlined status-icon-warn">error</span>
          <span v-else class="material-symbols-outlined status-icon-ok">check_circle</span>
        </div>
        <div class="card-value" :class="{ 'text-error': stats.mismatch_total > 0 }">
          {{ formatCurrency(stats.settlement_total) }}
        </div>
        <div class="card-footer">
          <span :class="stats.mismatch_total > 0 ? 'pulse-dot-red' : 'pulse-dot-green'"></span>
          <span class="footer-text" :class="{ 'text-error font-medium': stats.mismatch_total > 0 }">
            {{
              stats.mismatch_total > 0
                ? t('admin.reconciliation.kpiSettlementVariance', {
                    amount: formatCurrency(stats.mismatch_total),
                    count: stats.open_alerts_count || 1,
                  })
                : t('admin.reconciliation.kpiSettlementSubtitle')
            }}
          </span>
        </div>
      </div>

      <!-- Card 4: Chi hộ & Rút tiền -->
      <div class="kpi-card group">
        <div class="card-glow primary-glow"></div>
        <div class="card-header">
          <div class="card-title-wrap">
            <span class="material-symbols-outlined icon-type">payments</span>
            <span class="card-label">{{ t('admin.reconciliation.kpiPayoutTitle') }}</span>
          </div>
          <span class="material-symbols-outlined status-icon-ok">task_alt</span>
        </div>
        <div class="card-value">{{ formatCurrency(stats.payout_total) }}</div>
        <div class="card-footer">
          <span class="pulse-dot-green"></span>
          <span class="footer-text">{{ t('admin.reconciliation.kpiPayoutSubtitle') }}</span>
        </div>
      </div>
    </template>
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
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 20px;
  border: 1px solid var(--outline, #e1e3e4);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(107, 45, 63, 0.08);
  border-color: var(--accent, #c9a15a);
}

.warning-card {
  border-color: #ffdad6;
  background: linear-gradient(180deg, var(--surface, #ffffff) 0%, rgba(255, 218, 214, 0.15) 100%);
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
  background: var(--primary-container, #f8e8ec);
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
  color: var(--pink-dark, #db2777);
}

.card-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted, #5d5f5f);
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
  color: var(--ink, #191c1d);
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
  color: var(--muted, #5d5f5f);
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

/* Skeleton Styles */
.skeleton-card {
  min-height: 120px;
}

.skeleton-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.skeleton-icon-bar {
  width: 130px;
  height: 16px;
  border-radius: 6px;
  background: var(--soft, #eff1f4);
  animation: shimmer 1.5s infinite;
}

.skeleton-pill {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--soft, #eff1f4);
  animation: shimmer 1.5s infinite;
}

.skeleton-value {
  width: 170px;
  height: 28px;
  border-radius: 8px;
  background: var(--soft, #eff1f4);
  animation: shimmer 1.5s infinite;
  margin: 6px 0;
}

.skeleton-footer {
  width: 140px;
  height: 14px;
  border-radius: 6px;
  background: var(--soft, #eff1f4);
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% { opacity: 0.6; }
  50% { opacity: 1; }
  100% { opacity: 0.6; }
}
</style>
