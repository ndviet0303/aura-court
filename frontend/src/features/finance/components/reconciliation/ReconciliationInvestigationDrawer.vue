<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ReconciliationRun, ReconciliationDetail } from '../../types/reconciliation'

const props = defineProps<{
  show: boolean
  item: ReconciliationRun | null
  detail: ReconciliationDetail | null
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'resolve', id: number): void
}>()

const { t } = useI18n()

const overrideNotes = ref('')
const isSigning = ref(false)

function formatCurrency(amount?: number): string {
  if (amount === undefined || amount === null) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)
}

function handleResolve() {
  if (!props.item) return
  isSigning.value = true
  setTimeout(() => {
    emit('resolve', props.item!.id)
    isSigning.value = false
  }, 600)
}
</script>

<template>
  <div v-if="show" class="drawer-overlay" @click.self="emit('close')">
    <div class="drawer-container">
      <!-- Drawer Header -->
      <div class="drawer-header">
        <div class="header-main">
          <div class="title-row">
            <h3 class="drawer-title">{{ t('admin.reconciliation.drawerTitle') }}</h3>
            <span class="txn-badge">{{
              item?.txnCode || item?.details?.txnCode || `REC-${item?.id}`
            }}</span>
          </div>
          <p class="drawer-sub">
            {{ t('admin.reconciliation.drawerSub') }}
          </p>
        </div>
        <button class="btn-close" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Drawer Body -->
      <div class="drawer-body">
        <!-- Loading Skeleton -->
        <template v-if="loading">
          <div class="skeleton-discrepancy"></div>
          <div class="skeleton-grid-cols">
            <div v-for="i in 3" :key="i" class="skeleton-col"></div>
          </div>
          <div class="skeleton-diag"></div>
        </template>

        <!-- Real Content -->
        <template v-else>
          <!-- Discrepancy Overview Banner -->
          <div
            class="discrepancy-banner"
            :class="{
              'banner-mismatch': item?.status === 'mismatch',
              'banner-matched': item?.status === 'matched',
            }"
          >
            <div class="banner-stat">
              <span class="stat-label">{{ t('admin.reconciliation.reconStatusLabel') }}</span>
              <span class="stat-value font-bold">
                {{
                  item?.status === 'mismatch'
                    ? t('admin.reconciliation.statMismatch')
                    : t('admin.reconciliation.statMatched')
                }}
              </span>
            </div>
            <div class="banner-stat">
              <span class="stat-label">{{ t('admin.reconciliation.deltaLabel') }}</span>
              <span class="stat-value text-error font-mono font-bold" v-if="item?.mismatch_amount">
                -{{ formatCurrency(item.mismatch_amount) }}
              </span>
              <span class="stat-value text-success font-mono font-bold" v-else>0 ₫</span>
            </div>
          </div>

          <!-- 3-Column Comparison Grid -->
          <div class="comparison-grid">
            <!-- Col 1: Sổ cái Aura Booking -->
            <div class="comparison-col">
              <div class="col-header">
                <span class="material-symbols-outlined col-icon">menu_book</span>
                <span class="col-title">{{ t('admin.reconciliation.colLedgerTitle') }}</span>
              </div>
              <div class="col-content">
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelRecordedAmount') }}</span>
                  <span class="row-val font-bold font-mono">{{
                    formatCurrency(item?.journal_total)
                  }}</span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelBookingTime') }}</span>
                  <span class="row-val">06:42:15 ({{ item?.run_date }})</span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelCourtVenue') }}</span>
                  <span class="row-val">{{
                    item?.details?.court || 'Aura Court Q7 - Sân VIP 1'
                  }}</span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelCustomer') }}</span>
                  <span class="row-val">{{
                    item?.details?.customer || 'Trần Minh Quang (UID-8821)'
                  }}</span>
                </div>
                <div class="info-tag tag-success">{{ t('admin.reconciliation.tagLedgerOk') }}</div>
              </div>
            </div>

            <!-- Col 2: Cổng Thanh toán PSP -->
            <div class="comparison-col">
              <div class="col-header">
                <span class="material-symbols-outlined col-icon">account_balance_wallet</span>
                <span class="col-title">{{ t('admin.reconciliation.colPspTitle') }}</span>
              </div>
              <div class="col-content">
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelPspAmount') }}</span>
                  <span class="row-val font-bold font-mono">{{
                    formatCurrency(item?.provider_total)
                  }}</span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelPspRef') }}</span>
                  <span class="row-val font-mono">FT{{ item?.id || '260830001' }}</span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelPartnerChannel') }}</span>
                  <span class="row-val">{{
                    item?.channel || item?.details?.channel || 'VietQR Pro (MBBank)'
                  }}</span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{
                    t('admin.reconciliation.labelWebhookVerified')
                  }}</span>
                  <span class="row-val">06:42:16 (120ms)</span>
                </div>
                <div class="info-tag tag-success">{{ t('admin.reconciliation.tagPspOk') }}</div>
              </div>
            </div>

            <!-- Col 3: Quyết toán Ngân hàng -->
            <div class="comparison-col" :class="{ 'col-error': item?.status === 'mismatch' }">
              <div class="col-header">
                <span class="material-symbols-outlined col-icon">account_balance</span>
                <span class="col-title">{{ t('admin.reconciliation.colBankTitle') }}</span>
              </div>
              <div class="col-content">
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelBankActual') }}</span>
                  <span
                    class="row-val font-bold font-mono"
                    :class="{
                      'text-error': item?.settlement_total === 0 || item?.status === 'mismatch',
                    }"
                  >
                    {{ formatCurrency(item?.settlement_total) }}
                  </span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelBankAcc') }}</span>
                  <span class="row-val font-mono">038899281928 (MB)</span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{
                    t('admin.reconciliation.labelStatementPeriod')
                  }}</span>
                  <span class="row-val">Batch T+0 (07:00)</span>
                </div>
                <div class="info-row">
                  <span class="row-label">{{ t('admin.reconciliation.labelStatementMatch') }}</span>
                  <span class="row-val text-error font-medium" v-if="item?.mismatch_amount">
                    {{
                      t('admin.reconciliation.statementUncredited', {
                        amount: formatCurrency(item.mismatch_amount),
                      })
                    }}
                  </span>
                  <span class="row-val text-success" v-else>
                    {{ t('admin.reconciliation.statementCredited') }}
                  </span>
                </div>
                <div class="info-tag" :class="item?.mismatch_amount ? 'tag-error' : 'tag-success'">
                  {{
                    item?.mismatch_amount
                      ? t('admin.reconciliation.tagBankDelay')
                      : t('admin.reconciliation.tagBankSettled')
                  }}
                </div>
              </div>
            </div>
          </div>

          <!-- Root-Cause Discrepancy Diagnostic Card -->
          <div class="ai-diagnosis-card">
            <div class="ai-header">
              <div class="ai-title-wrap">
                <span class="material-symbols-outlined ai-icon">troubleshoot</span>
                <span class="ai-title">{{ t('admin.reconciliation.diagCardTitle') }}</span>
              </div>
              <span class="confidence-badge">{{
                t('admin.reconciliation.confidenceLabel', { score: '99.4' })
              }}</span>
            </div>
            <div class="ai-body">
              <p class="diagnosis-text">
                {{
                  item?.status === 'mismatch'
                    ? t('admin.reconciliation.diagDefaultText')
                    : t('admin.reconciliation.diagSuccessText')
                }}
              </p>
              <div class="recommendation-box">
                <span class="rec-title">💡 {{ t('admin.reconciliation.diagRecTitle') }}</span>
                <span class="rec-desc">
                  {{
                    item?.status === 'mismatch'
                      ? t('admin.reconciliation.diagRecMismatch')
                      : t('admin.reconciliation.diagRecMatched')
                  }}
                </span>
              </div>
            </div>
          </div>

          <!-- Resolution Form -->
          <div class="resolution-section" v-if="item?.status === 'mismatch'">
            <label class="form-label">{{ t('admin.reconciliation.auditNotesLabel') }}</label>
            <textarea
              v-model="overrideNotes"
              rows="2"
              :placeholder="t('admin.reconciliation.auditNotesPlaceholder')"
              class="notes-textarea"
            ></textarea>
          </div>
        </template>
      </div>

      <!-- Drawer Footer -->
      <div class="drawer-footer">
        <button class="btn-cancel" type="button" @click="emit('close')">
          {{ t('admin.reconciliation.btnCloseDrawer') }}
        </button>
        <div class="footer-action-buttons">
          <button
            v-if="item?.status === 'mismatch'"
            class="btn-primary-resolve"
            type="button"
            :disabled="isSigning"
            @click="handleResolve"
          >
            <span class="material-symbols-outlined">rule</span>
            {{
              isSigning
                ? t('admin.reconciliation.btnSigning')
                : t('admin.reconciliation.btnSignResolve')
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(25, 28, 29, 0.45);
  backdrop-filter: blur(4px);
  z-index: 999;
  display: flex;
  justify-content: flex-end;
}

.drawer-container {
  width: 680px;
  max-width: 95vw;
  height: 100vh;
  background: var(--surface, #ffffff);
  display: flex;
  flex-direction: column;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
  animation: slideIn 0.25s ease-out;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
  }
  to {
    transform: translateX(0);
  }
}

.drawer-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline, #e1e3e4);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: var(--soft, #f8f9fa);
}

.title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.drawer-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.txn-badge {
  font-family: monospace;
  font-size: 12px;
  font-weight: 700;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  padding: 3px 8px;
  border-radius: 6px;
}

.drawer-sub {
  font-size: 13px;
  color: var(--muted, #5d5f5f);
  margin: 4px 0 0;
}

.btn-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted, #5d5f5f);
  padding: 4px;
  border-radius: 50%;
  transition: background 0.2s;
}

.btn-close:hover {
  background: var(--outline, #e1e3e4);
  color: var(--ink, #191c1d);
}

.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.discrepancy-banner {
  display: flex;
  justify-content: space-between;
  padding: 14px 18px;
  border-radius: 12px;
}

.banner-mismatch {
  background: #ffdad6;
  border: 1px solid #ba1a1a;
}
:global(html.dark) .banner-mismatch {
  background: #49171b;
  border-color: #ba1a1a;
}

.banner-matched {
  background: #e6f4ea;
  border: 1px solid #137333;
}
:global(html.dark) .banner-matched {
  background: #133820;
  border-color: #4ade80;
}

.stat-label {
  font-size: 12px;
  color: var(--muted, #4f4446);
  display: block;
}

.stat-value {
  font-size: 15px;
  margin-top: 2px;
  display: block;
  color: var(--ink, #191c1d);
}

.comparison-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

@media (max-width: 600px) {
  .comparison-grid {
    grid-template-columns: 1fr;
  }
}

.comparison-col {
  background: var(--soft, #f8f9fa);
  border-radius: 12px;
  border: 1px solid var(--outline, #e1e3e4);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.col-error {
  border-color: #ffdad6;
  background: rgba(255, 218, 214, 0.15);
}

.col-header {
  display: flex;
  align-items: center;
  gap: 6px;
  border-bottom: 1px solid var(--outline, #e1e3e4);
  padding-bottom: 8px;
}

.col-icon {
  font-size: 18px;
  color: var(--pink-dark, #db2777);
}

.col-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.col-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.info-row {
  display: flex;
  flex-direction: column;
}

.row-label {
  font-size: 11px;
  color: var(--muted, #5d5f5f);
}

.row-val {
  font-size: 13px;
  color: var(--ink, #191c1d);
}

.info-tag {
  margin-top: 6px;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 6px;
  text-align: center;
}

.tag-success {
  background: #e6f4ea;
  color: #137333;
}
:global(html.dark) .tag-success {
  background: #133820;
  color: #4ade80;
}

.tag-error {
  background: #ffdad6;
  color: #ba1a1a;
}
:global(html.dark) .tag-error {
  background: #49171b;
  color: #fca5a5;
}

.ai-diagnosis-card {
  background: var(--soft, #f7f3ed);
  border: 1px solid var(--outline, #e8e1d7);
  border-radius: 14px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ai-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ai-title-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.ai-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.ai-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.confidence-badge {
  font-size: 11px;
  font-weight: 700;
  background: var(--primary-container, #f8e8ec);
  color: var(--on-primary-container, #4e1827);
  padding: 2px 8px;
  border-radius: 999px;
}

.diagnosis-text {
  font-size: 13px;
  color: var(--ink, #191c1d);
  line-height: 1.5;
  margin: 0;
}

.recommendation-box {
  background: var(--surface, #ffffff);
  border-radius: 8px;
  padding: 10px 12px;
  border: 1px dashed var(--outline, #e8e1d7);
  font-size: 12px;
}

.rec-title {
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  display: block;
}

.rec-desc {
  color: var(--muted, #4f4446);
  margin-top: 2px;
  display: block;
}

.resolution-section {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.notes-textarea {
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 8px;
  padding: 10px;
  font-size: 13px;
  outline: none;
  font-family: inherit;
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
}

.notes-textarea:focus {
  border-color: var(--pink-dark, #db2777);
}

.drawer-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--outline, #e1e3e4);
  background: var(--soft, #f8f9fa);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.btn-cancel {
  background: transparent;
  border: 1px solid var(--outline, #e1e3e4);
  padding: 8px 18px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  color: var(--ink, #191c1d);
}

.btn-primary-resolve {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  border: none;
  padding: 9px 20px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary-resolve:hover:not(:disabled) {
  background: var(--pink-deep, #be185d);
}

.btn-primary-resolve:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Skeletons */
.skeleton-discrepancy {
  height: 64px;
  background: var(--soft, #eff1f4);
  border-radius: 12px;
  animation: shimmer 1.5s infinite;
}
.skeleton-grid-cols {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}
.skeleton-col {
  height: 220px;
  background: var(--soft, #eff1f4);
  border-radius: 12px;
  animation: shimmer 1.5s infinite;
}
.skeleton-diag {
  height: 120px;
  background: var(--soft, #eff1f4);
  border-radius: 14px;
  animation: shimmer 1.5s infinite;
}

.font-mono {
  font-family: monospace;
}
.font-bold {
  font-weight: 700;
}
.font-medium {
  font-weight: 500;
}
.text-error {
  color: #ba1a1a;
}
.text-success {
  color: #059669;
}

@keyframes shimmer {
  0% {
    opacity: 0.6;
  }
  50% {
    opacity: 1;
  }
  100% {
    opacity: 0.6;
  }
}
</style>
