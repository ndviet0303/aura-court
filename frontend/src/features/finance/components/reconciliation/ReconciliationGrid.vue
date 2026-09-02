<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ReconciliationRun } from '../../types/reconciliation'

defineProps<{
  items: ReconciliationRun[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'investigate', item: ReconciliationRun): void
  (e: 'auto-resolve', id: number): void
}>()

const { t } = useI18n()

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)
}
</script>

<template>
  <div class="grid-card">
    <div class="grid-header">
      <div class="header-left">
        <h2 class="grid-title">{{ t('admin.reconciliation.gridTitle') }}</h2>
        <span class="count-badge">{{ t('admin.reconciliation.countBadge', { count: items.length }) }}</span>
      </div>
      <div class="grid-subtitle">
        {{ t('admin.reconciliation.gridSubtitle') }}
      </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table class="recon-table">
        <thead>
          <tr>
            <th>{{ t('admin.reconciliation.thTxn') }}</th>
            <th>{{ t('admin.reconciliation.thChannel') }}</th>
            <th class="text-right">{{ t('admin.reconciliation.thLedger') }}</th>
            <th class="text-right">{{ t('admin.reconciliation.thPsp') }}</th>
            <th class="text-right">{{ t('admin.reconciliation.thBank') }}</th>
            <th class="text-right">{{ t('admin.reconciliation.thVariance') }}</th>
            <th class="text-center">{{ t('admin.reconciliation.thStatus') }}</th>
            <th class="text-center">{{ t('admin.reconciliation.thRisk') }}</th>
            <th class="text-right">{{ t('admin.reconciliation.thActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <!-- Skeleton Loading Rows -->
          <template v-if="loading">
            <tr v-for="i in 5" :key="`skel-${i}`" class="skeleton-row">
              <td>
                <div class="skeleton-cell w-24 h-4"></div>
                <div class="skeleton-cell w-16 h-3 mt-1"></div>
              </td>
              <td>
                <div class="skeleton-cell w-28 h-4"></div>
                <div class="skeleton-cell w-36 h-3 mt-1"></div>
              </td>
              <td class="text-right"><div class="skeleton-cell w-20 h-4 ml-auto"></div></td>
              <td class="text-right"><div class="skeleton-cell w-20 h-4 ml-auto"></div></td>
              <td class="text-right"><div class="skeleton-cell w-20 h-4 ml-auto"></div></td>
              <td class="text-right"><div class="skeleton-cell w-16 h-4 ml-auto"></div></td>
              <td class="text-center"><div class="skeleton-cell w-24 h-5 mx-auto rounded-full"></div></td>
              <td class="text-center"><div class="skeleton-cell w-12 h-5 mx-auto rounded"></div></td>
              <td class="text-right"><div class="skeleton-cell w-20 h-7 ml-auto rounded"></div></td>
            </tr>
          </template>

          <!-- Empty State -->
          <tr v-else-if="items.length === 0">
            <td colspan="9" class="text-center py-12 text-muted">
              <div class="empty-wrap">
                <span class="material-symbols-outlined empty-icon">rule_folder</span>
                <p>{{ t('admin.reconciliation.emptyTable') }}</p>
              </div>
            </td>
          </tr>

          <!-- Real Data Rows -->
          <tr
            v-for="item in items"
            v-else
            :key="item.id"
            class="recon-row"
            :class="{ 'row-warning': item.status === 'mismatch' }"
          >
            <!-- TXN Code -->
            <td>
              <div class="txn-code-wrap">
                <span class="txn-code">{{ item.txnCode || item.details?.txnCode || `REC-${item.id}` }}</span>
                <span class="txn-date">{{ item.run_date }}</span>
              </div>
            </td>

            <!-- Channel -->
            <td>
              <div class="channel-info">
                <span class="channel-name">{{ item.channel || item.details?.channel || 'VietQR Pro' }}</span>
                <span v-if="item.details?.customer" class="customer-preview">
                  {{ item.details.customer }}
                </span>
              </div>
            </td>

            <!-- Ledger Total -->
            <td class="text-right font-mono font-medium">
              {{ formatCurrency(item.journal_total) }}
            </td>

            <!-- PSP Total -->
            <td class="text-right font-mono font-medium">
              {{ formatCurrency(item.provider_total) }}
            </td>

            <!-- Settlement Bank Total -->
            <td
              class="text-right font-mono font-medium"
              :class="{ 'text-error': item.mismatch_amount > 0 && item.settlement_total === 0 }"
            >
              {{ formatCurrency(item.settlement_total) }}
            </td>

            <!-- Delta Variance -->
            <td class="text-right font-mono font-bold">
              <span v-if="item.mismatch_amount === 0" class="text-success">0 ₫</span>
              <span v-else class="text-error flex-align-right">
                <span class="material-symbols-outlined text-[14px]">trending_down</span>
                -{{ formatCurrency(item.mismatch_amount) }}
              </span>
            </td>

            <!-- Status Badge -->
            <td class="text-center">
              <span
                class="status-pill"
                :class="{
                  'status-matched': item.status === 'matched',
                  'status-mismatch': item.status === 'mismatch',
                  'status-running': item.status === 'running',
                }"
              >
                <span class="status-dot"></span>
                {{
                  item.status === 'matched'
                    ? t('admin.reconciliation.statusLabelMatched')
                    : item.status === 'mismatch'
                      ? t('admin.reconciliation.statusLabelMismatch')
                      : t('admin.reconciliation.statusRunning')
                }}
              </span>
            </td>

            <!-- Risk Level Pill -->
            <td class="text-center">
              <span
                class="risk-pill"
                :class="{
                  'risk-high': (item.riskLevel || item.details?.riskLevel) === 'high' || (item.riskLevel || item.details?.riskLevel) === 'critical',
                  'risk-med': (item.riskLevel || item.details?.riskLevel) === 'medium',
                  'risk-low': (item.riskLevel || item.details?.riskLevel) === 'low' || !(item.riskLevel || item.details?.riskLevel),
                }"
              >
                {{
                  (item.riskLevel || item.details?.riskLevel) === 'high'
                    ? t('admin.reconciliation.riskLevelHigh')
                    : (item.riskLevel || item.details?.riskLevel) === 'medium'
                      ? t('admin.reconciliation.riskLevelMed')
                      : t('admin.reconciliation.riskLevelLow')
                }}
              </span>
            </td>

            <!-- Actions -->
            <td class="text-right">
              <div class="action-btn-group">
                <button
                  class="btn-investigate"
                  type="button"
                  @click="emit('investigate', item)"
                >
                  <span class="material-symbols-outlined">analytics</span>
                  {{ t('admin.reconciliation.btnInvestigate') }}
                </button>
                <button
                  v-if="item.status === 'mismatch'"
                  class="btn-auto-fix-sm"
                  type="button"
                  :title="t('admin.reconciliation.btnAutoResolveShort')"
                  @click="emit('auto-resolve', item.id)"
                >
                  <span class="material-symbols-outlined">rule</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.grid-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e1e3e4);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
  overflow: hidden;
}

.grid-header {
  padding: 18px 20px 14px;
  border-bottom: 1px solid var(--outline, #f3f4f5);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.grid-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.count-badge {
  font-size: 12px;
  font-weight: 600;
  background: var(--soft, #f3f4f5);
  color: var(--muted, #5d5f5f);
  padding: 2px 8px;
  border-radius: 999px;
}

.grid-subtitle {
  font-size: 13px;
  color: var(--muted, #5d5f5f);
  margin-top: 4px;
}

.table-responsive {
  overflow-x: auto;
}

.recon-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
}

.recon-table th {
  background: var(--soft, #f8f9fa);
  color: var(--muted, #5d5f5f);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px 16px;
  border-bottom: 1px solid var(--outline, #e1e3e4);
}

.recon-table td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--outline, #f3f4f5);
  color: var(--ink, #191c1d);
  vertical-align: middle;
}

.recon-row {
  transition: background 0.15s;
}

.recon-row:hover {
  background: var(--soft, #fafafa);
}

.row-warning {
  background: rgba(255, 218, 214, 0.08);
}

.txn-code-wrap {
  display: flex;
  flex-direction: column;
}

.txn-code {
  font-family: monospace;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.txn-date {
  font-size: 11px;
  color: var(--muted, #817476);
}

.channel-info {
  display: flex;
  flex-direction: column;
}

.channel-name {
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.customer-preview {
  font-size: 11px;
  color: var(--muted, #5d5f5f);
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}

.font-mono {
  font-family: monospace;
}

.font-medium {
  font-weight: 500;
}

.font-bold {
  font-weight: 700;
}

.text-success {
  color: #059669;
}

.text-error {
  color: #ba1a1a;
}

.flex-align-right {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-matched {
  background: #e6f4ea;
  color: #137333;
}
.status-matched .status-dot {
  background: #137333;
}

:global(html.dark) .status-matched {
  background: #133820;
  color: #4ade80;
}
:global(html.dark) .status-matched .status-dot {
  background: #4ade80;
}

.status-mismatch {
  background: #ffdad6;
  color: #ba1a1a;
}
.status-mismatch .status-dot {
  background: #ba1a1a;
}

:global(html.dark) .status-mismatch {
  background: #49171b;
  color: #fca5a5;
}
:global(html.dark) .status-mismatch .status-dot {
  background: #fca5a5;
}

.status-running {
  background: #e8eaed;
  color: #5f6368;
}
.status-running .status-dot {
  background: #5f6368;
}

.risk-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.risk-high {
  background: #fce8e6;
  color: #c5221f;
}

.risk-med {
  background: #fef7e0;
  color: #b06000;
}

.risk-low {
  background: #e6f4ea;
  color: #137333;
}

:global(html.dark) .risk-high {
  background: #49171b;
  color: #fca5a5;
}
:global(html.dark) .risk-med {
  background: #3d2c00;
  color: #fde047;
}
:global(html.dark) .risk-low {
  background: #133820;
  color: #4ade80;
}

.action-btn-group {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-investigate {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--surface, #f8f9fa);
  border: 1px solid var(--outline, #e1e3e4);
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-investigate:hover {
  background: var(--primary-container, #f8e8ec);
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.btn-auto-fix-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  transition: background 0.2s;
}

.btn-auto-fix-sm:hover {
  background: var(--pink-deep, #be185d);
}

.empty-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.empty-icon {
  font-size: 32px;
  color: var(--muted, #817476);
}

/* Skeleton Rows */
.skeleton-row td {
  padding: 16px;
}

.skeleton-cell {
  background: var(--soft, #eff1f4);
  border-radius: 4px;
  animation: shimmer 1.5s infinite;
}

.w-12 { width: 48px; }
.w-16 { width: 64px; }
.w-20 { width: 80px; }
.w-24 { width: 96px; }
.w-28 { width: 112px; }
.w-36 { width: 144px; }
.h-3 { height: 12px; }
.h-4 { height: 16px; }
.h-5 { height: 20px; }
.h-7 { height: 28px; }
.mt-1 { margin-top: 4px; }
.ml-auto { margin-left: auto; }
.mx-auto { margin-left: auto; margin-right: auto; }
.rounded { border-radius: 6px; }
.rounded-full { border-radius: 999px; }

@keyframes shimmer {
  0% { opacity: 0.6; }
  50% { opacity: 1; }
  100% { opacity: 0.6; }
}
</style>
