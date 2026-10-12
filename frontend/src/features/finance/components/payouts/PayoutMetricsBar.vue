<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  gross: number
  fee: number
  net: number
  paid?: number
  providerFee?: number
  commission?: number
  refunds?: number
  loading?: boolean
}>()

const { t } = useI18n()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="payout-metrics-grid">
    <!-- Gross Revenue Card -->
    <div class="metric-card gross-box">
      <div v-if="loading" class="skeleton-card-inner">
        <div class="skeleton-shimmer sk-icon"></div>
        <div class="skeleton-shimmer sk-title"></div>
        <div class="skeleton-shimmer sk-val"></div>
        <div class="skeleton-shimmer sk-sub"></div>
      </div>
      <template v-else>
        <div class="card-head">
          <span class="m-lbl">{{ t('admin.payouts.grossRevenue') }}</span>
          <div class="m-icon-wrapper gross-icon">
            <span class="material-symbols-outlined icon-20">trending_up</span>
          </div>
        </div>
        <strong class="m-val">{{ formatMoney(gross) }}</strong>
        <span class="m-sub text-muted">{{ t('admin.payouts.grossSub') }}</span>
      </template>
    </div>

    <!-- Fees & Commission Card -->
    <div class="metric-card fee-box">
      <div v-if="loading" class="skeleton-card-inner">
        <div class="skeleton-shimmer sk-icon"></div>
        <div class="skeleton-shimmer sk-title"></div>
        <div class="skeleton-shimmer sk-val"></div>
        <div class="skeleton-shimmer sk-sub"></div>
      </div>
      <template v-else>
        <div class="card-head">
          <span class="m-lbl">{{ t('admin.payouts.totalFees') }}</span>
          <div class="m-icon-wrapper fee-icon">
            <span class="material-symbols-outlined icon-20">receipt_long</span>
          </div>
        </div>
        <strong class="m-val text-red">-{{ formatMoney(fee) }}</strong>
        <span class="m-sub text-muted">
          <template v-if="commission || refunds">
            {{ t('admin.payouts.providerFee') }}: {{ formatMoney(providerFee || 0) }}
            <template v-if="commission">
              | {{ t('admin.payouts.historicalCommission') }}: {{ formatMoney(commission) }}
            </template>
            <template v-if="refunds">
              | {{ t('admin.payouts.refunds') }}: {{ formatMoney(refunds) }}
            </template>
          </template>
          <template v-else>
            {{ t('admin.payouts.feesSub') }}
          </template>
        </span>
      </template>
    </div>

    <!-- Net Settlement Card -->
    <div class="metric-card net-box">
      <div v-if="loading" class="skeleton-card-inner">
        <div class="skeleton-shimmer sk-icon"></div>
        <div class="skeleton-shimmer sk-title"></div>
        <div class="skeleton-shimmer sk-val"></div>
        <div class="skeleton-shimmer sk-sub"></div>
      </div>
      <template v-else>
        <div class="card-head">
          <span class="m-lbl">{{ t('admin.payouts.netSettlement') }}</span>
          <div class="m-icon-wrapper net-icon">
            <span class="material-symbols-outlined icon-20">account_balance_wallet</span>
          </div>
        </div>
        <strong class="m-val text-pink">{{ formatMoney(net) }}</strong>
        <span class="m-sub text-green">{{ t('admin.payouts.netSub') }}</span>
      </template>
    </div>

    <!-- Total Disbursed / Paid Card -->
    <div class="metric-card paid-box">
      <div v-if="loading" class="skeleton-card-inner">
        <div class="skeleton-shimmer sk-icon"></div>
        <div class="skeleton-shimmer sk-title"></div>
        <div class="skeleton-shimmer sk-val"></div>
        <div class="skeleton-shimmer sk-sub"></div>
      </div>
      <template v-else>
        <div class="card-head">
          <span class="m-lbl">{{ t('admin.payouts.totalPaid') }}</span>
          <div class="m-icon-wrapper paid-icon">
            <span class="material-symbols-outlined icon-20">check_circle</span>
          </div>
        </div>
        <strong class="m-val text-green">{{ formatMoney(paid || 0) }}</strong>
        <span class="m-sub text-muted">{{ t('admin.payouts.paidSub') }}</span>
      </template>
    </div>
  </div>
</template>

<style scoped>
.payout-metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.metric-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 18px 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.metric-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
}

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.m-icon-wrapper {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gross-icon {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.fee-icon {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.net-icon {
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
}

.paid-icon {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.m-lbl {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  letter-spacing: 0.5px;
}

.m-val {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink);
  line-height: 1.2;
}

.m-sub {
  font-size: 12px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.text-green {
  color: #10b981;
}

.text-red {
  color: #ef4444;
}

.text-muted {
  color: var(--muted);
}

.icon-20 {
  font-size: 20px;
}

/* Skeleton Loading */
.skeleton-card-inner {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.skeleton-shimmer {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 6px;
}

.skeleton-shimmer::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 209, 220, 0.45) 45%,
    rgba(255, 255, 255, 0.8) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-wave 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skeleton-shimmer {
  background-color: var(--soft, #2b2e36);
}

:global(html.dark) .skeleton-shimmer::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(120, 85, 94, 0.35) 45%,
    rgba(255, 255, 255, 0.1) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

.sk-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
}

.sk-title {
  width: 60%;
  height: 12px;
}

.sk-val {
  width: 80%;
  height: 22px;
}

.sk-sub {
  width: 70%;
  height: 11px;
}

@keyframes shimmer-wave {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

@media (max-width: 1100px) {
  .payout-metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .payout-metrics-grid {
    grid-template-columns: 1fr;
  }
}
</style>
