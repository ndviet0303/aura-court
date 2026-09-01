<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import type { CashShiftItem } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  shift: CashShiftItem
}>()

const emit = defineEmits<{
  (e: 'openDenominations'): void
  (e: 'openCashDrops'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="active-shift-card">
    <div class="shift-top-row">
      <div class="shift-headline">
        <span class="live-pulse">● {{ t('admin.shifts.statusLive') }}</span>
        <h3 class="shift-title">{{ shift.shift_name }}</h3>
        <span class="shift-venue">
          <span class="material-symbols-outlined icon-14">location_on</span>
          <span>{{ resolveLocalizedText(shift.venue_name) }} · {{ t('admin.shifts.cashierLabel', { name: shift.cashier_name }) }}</span>
        </span>
      </div>

      <div class="shift-quick-actions">
        <button type="button" class="btn-action-outline" @click="emit('openCashDrops')">
          <span class="material-symbols-outlined icon-16">payments</span>
          <span>{{ t('admin.shifts.cashDropsBtn', { count: shift.cash_drops.length }) }}</span>
        </button>
        <button type="button" class="btn-action-primary" @click="emit('openDenominations')">
          <span class="material-symbols-outlined icon-16">calculate</span>
          <span>{{ t('admin.shifts.auditDenominationsBtn') }}</span>
        </button>
      </div>
    </div>

    <div class="shift-metrics-grid">
      <div class="metric-box">
        <span class="metric-lbl">{{ t('admin.shifts.openingCashLabel') }}</span>
        <strong class="metric-val font-mono">{{ formatCurrency(shift.opening_cash) }}</strong>
      </div>

      <div class="metric-box">
        <span class="metric-lbl">{{ t('admin.shifts.cashSalesLabel') }}</span>
        <strong class="metric-val text-green font-mono">+{{ formatCurrency(shift.cash_sales) }}</strong>
      </div>

      <div class="metric-box">
        <span class="metric-lbl">{{ t('admin.shifts.qrSalesLabel') }}</span>
        <strong class="metric-val text-blue font-mono">{{ formatCurrency(shift.qr_sales + shift.wallet_sales) }}</strong>
      </div>

      <div class="metric-box">
        <span class="metric-lbl">{{ t('admin.shifts.totalSalesLabel') }}</span>
        <strong class="metric-val text-pink font-mono">{{ formatCurrency(shift.total_sales) }}</strong>
      </div>
    </div>

    <div class="shift-variance-bar" :class="{ 'has-diff': shift.variance !== 0 }">
      <div class="variance-item">
        <span>{{ t('admin.shifts.expectedCashLabel') }}</span>
        <strong class="font-mono">{{ formatCurrency(shift.expected_cash) }}</strong>
      </div>
      <div class="variance-item">
        <span>{{ t('admin.shifts.declaredCashLabel') }}</span>
        <strong class="font-mono">{{ formatCurrency(shift.declared_cash) }}</strong>
      </div>
      <div class="variance-item">
        <span>{{ t('admin.shifts.varianceLabel') }}</span>
        <strong class="font-mono" :class="shift.variance < 0 ? 'text-red' : shift.variance > 0 ? 'text-yellow' : 'text-green'">
          {{ shift.variance > 0 ? '+' : '' }}{{ formatCurrency(shift.variance) }}
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

.shift-headline {
  display: flex;
  flex-direction: column;
  gap: 2px;
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
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.shift-quick-actions {
  display: flex;
  gap: 8px;
}

.btn-action-outline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-action-outline:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.btn-action-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
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

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}

@media (max-width: 800px) {
  .shift-metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
