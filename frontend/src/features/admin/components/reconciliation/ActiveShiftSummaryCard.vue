<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency, formatDateTime } from '@/i18n'
import { AuraButton } from '@/shared/components'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'
import type { CashShiftItem } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  shift: CashShiftItem
  hasActiveShift: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'openDenominations'): void
  (e: 'openCashDrops'): void
  (e: 'openShift'): void
  (e: 'closeShift'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="active-shift-card">
    <!-- Loading Skeleton State -->
    <div v-if="loading" class="shift-skeleton-box">
      <div class="skeleton-header">
        <SkeletonLoader type="rect" height="22px" width="180px" />
        <SkeletonLoader type="rect" height="36px" width="220px" />
      </div>
      <div class="skeleton-metrics">
        <SkeletonLoader v-for="i in 4" :key="i" type="rect" height="70px" width="100%" />
      </div>
      <SkeletonLoader type="rect" height="48px" width="100%" />
    </div>

    <!-- Empty State: No Active Shift Open -->
    <div v-else-if="!hasActiveShift" class="empty-shift-box">
      <div class="empty-icon-wrap">
        <span class="material-symbols-outlined empty-icon">storefront</span>
      </div>
      <div class="empty-content">
        <h3 class="empty-title">{{ t('admin.shifts.noActiveShift') }}</h3>
        <p class="empty-sub">{{ t('admin.shifts.noActiveShiftDesc') }}</p>
      </div>
      <AuraButton variant="primary" @click="emit('openShift')">
        <span class="material-symbols-outlined icon-18">play_arrow</span>
        <span>{{ t('admin.shifts.startShiftNow') }}</span>
      </AuraButton>
    </div>

    <!-- Active Shift Display -->
    <div v-else class="active-shift-content">
      <div class="shift-top-row">
        <div class="shift-headline">
          <div class="shift-badge-row">
            <span class="live-pulse">
              <span class="pulse-dot"></span>
              {{ t('admin.shifts.statusLive') }}
            </span>
            <span class="shift-code-tag font-mono">{{ shift.code }}</span>
          </div>
          <h3 class="shift-title">{{ shift.shift_name }}</h3>
          <div class="shift-venue-row">
            <span class="venue-item">
              <span class="material-symbols-outlined icon-14">location_on</span>
              <span>{{ resolveLocalizedText(shift.venue_name) }}</span>
            </span>
            <span class="divider">·</span>
            <span class="venue-item">
              <span class="material-symbols-outlined icon-14">person</span>
              <span>{{ t('admin.shifts.cashierLabel', { name: shift.cashier_name }) }}</span>
            </span>
            <span class="divider">·</span>
            <span class="venue-item font-mono">
              <span class="material-symbols-outlined icon-14">schedule</span>
              <span>{{ shift.opened_at ? formatDateTime(shift.opened_at) : '' }}</span>
            </span>
          </div>
        </div>

        <div class="shift-quick-actions">
          <AuraButton variant="secondary" icon="payments" @click="emit('openCashDrops')">
            {{ t('admin.shifts.cashDropsBtn', { count: shift.cash_drops.length }) }}
          </AuraButton>
          <AuraButton variant="secondary" icon="calculate" @click="emit('openDenominations')">
            {{ t('admin.shifts.auditDenominationsBtn') }}
          </AuraButton>
          <AuraButton variant="primary" icon="lock_clock" @click="emit('closeShift')">
            {{ t('admin.shifts.closeShiftBtn') }}
          </AuraButton>
        </div>
      </div>

      <div class="shift-metrics-grid">
        <div class="metric-box">
          <span class="metric-lbl">{{ t('admin.shifts.openingCashLabel') }}</span>
          <strong class="metric-val font-mono">{{ formatCurrency(shift.opening_cash) }}</strong>
          <span class="metric-sub">{{ t('admin.shifts.initialFloat') }}</span>
        </div>

        <div class="metric-box">
          <span class="metric-lbl">{{ t('admin.shifts.cashSalesLabel') }}</span>
          <strong class="metric-val text-green font-mono"
            >+{{ formatCurrency(shift.cash_sales) }}</strong
          >
          <span class="metric-sub">{{
            t('admin.shifts.ordersCountLabel', { count: shift.orders_count })
          }}</span>
        </div>

        <div class="metric-box">
          <span class="metric-lbl">{{ t('admin.shifts.qrSalesLabel') }}</span>
          <strong class="metric-val text-blue font-mono">{{
            formatCurrency(shift.qr_sales + shift.wallet_sales)
          }}</strong>
          <span class="metric-sub">PayOS & Ví Aura</span>
        </div>

        <div class="metric-box">
          <span class="metric-lbl">{{ t('admin.shifts.totalSalesLabel') }}</span>
          <strong class="metric-val text-pink font-mono">{{
            formatCurrency(shift.total_sales)
          }}</strong>
          <span class="metric-sub"
            >{{ t('admin.shifts.cashDropsTally') }}: -{{
              formatCurrency(shift.cash_drops_total)
            }}</span
          >
        </div>
      </div>

      <div class="shift-variance-bar" :class="{ 'has-diff': shift.variance !== 0 }">
        <div class="variance-item">
          <span class="var-lbl">{{ t('admin.shifts.expectedCashLabel') }}</span>
          <strong class="font-mono text-ink">{{ formatCurrency(shift.expected_cash) }}</strong>
        </div>
        <div class="variance-item">
          <span class="var-lbl">{{ t('admin.shifts.declaredCashLabel') }}</span>
          <strong class="font-mono text-ink">{{ formatCurrency(shift.declared_cash) }}</strong>
        </div>
        <div class="variance-item">
          <span class="var-lbl">{{ t('admin.shifts.varianceLabel') }}</span>
          <strong
            class="font-mono"
            :class="
              shift.variance === 0 ? 'text-green' : shift.variance < 0 ? 'text-red' : 'text-yellow'
            "
          >
            {{ shift.variance > 0 ? '+' : '' }}{{ formatCurrency(shift.variance) }}
          </strong>
          <span
            v-if="shift.variance !== 0"
            class="variance-tag"
            :class="shift.variance < 0 ? 'tag-red' : 'tag-yellow'"
          >
            {{ shift.variance < 0 ? t('admin.shifts.shortCash') : t('admin.shifts.overCash') }}
          </span>
          <span v-else class="variance-tag tag-green">
            {{ t('admin.shifts.balancedCash') }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.active-shift-card {
  display: flex;
  flex-direction: column;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.shift-skeleton-box {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skeleton-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.skeleton-metrics {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.empty-shift-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 12px 6px;
  flex-wrap: wrap;
}

.empty-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.08);
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.empty-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.empty-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
}

.empty-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.active-shift-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.shift-top-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 14px;
}

.shift-headline {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.shift-badge-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.live-pulse {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
}

.pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(0.9);
    opacity: 0.7;
  }
  50% {
    transform: scale(1.4);
    opacity: 1;
  }
  100% {
    transform: scale(0.9);
    opacity: 0.7;
  }
}

.shift-code-tag {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  padding: 3px 8px;
  border-radius: 6px;
}

.shift-title {
  margin: 2px 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.shift-venue-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--muted);
  flex-wrap: wrap;
}

.venue-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.divider {
  opacity: 0.5;
}

.shift-quick-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
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
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.metric-lbl {
  font-size: 11.5px;
  color: var(--muted);
  font-weight: 600;
}

.metric-val {
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.metric-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-green {
  color: #10b981;
}
.text-blue {
  color: #3b82f6;
}
.text-pink {
  color: var(--pink-dark, #db2777);
}
.text-yellow {
  color: #f59e0b;
}
.text-red {
  color: #ef4444;
}
.text-ink {
  color: var(--ink);
}

.shift-variance-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 18px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  font-size: 13px;
  flex-wrap: wrap;
  gap: 12px;
}

.variance-item {
  display: flex;
  gap: 8px;
  align-items: center;
}

.var-lbl {
  color: var(--muted);
  font-size: 12px;
}

.variance-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 5px;
}

.tag-green {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.tag-yellow {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}

.tag-red {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.icon-14 {
  font-size: 14px;
}
.icon-16 {
  font-size: 16px;
}
.icon-18 {
  font-size: 18px;
}

:global(html.dark) .active-shift-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .metric-box,
:global(html.dark) .shift-variance-bar {
  background: rgba(255, 255, 255, 0.03);
}

@media (max-width: 860px) {
  .shift-metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 540px) {
  .shift-metrics-grid {
    grid-template-columns: 1fr;
  }
}
</style>
