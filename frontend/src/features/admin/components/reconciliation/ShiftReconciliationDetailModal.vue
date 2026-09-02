<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency, formatDateTime } from '@/i18n'
import { SelectDropdown, AuraButton } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { CashShiftItem } from '../../composables/useAdminShiftReconciliation'

const props = defineProps<{
  show: boolean
  shift: CashShiftItem | null
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'saveDecision', payload: { shiftId: number; status: string; note?: string }): void
}>()

const { t } = useI18n()

const auditStatus = ref<string>('matched')
const managerNote = ref<string>('')

const decisionOptions = computed<DropdownOption[]>(() => [
  { value: 'matched', label: t('admin.shifts.optMatched'), icon: 'check_circle' },
  { value: 'approved', label: t('admin.shifts.optApproved'), icon: 'task_alt' },
  { value: 'exception', label: t('admin.shifts.optException'), icon: 'warning' },
  { value: 'rejected', label: t('admin.shifts.optRejected'), icon: 'cancel' },
])

watch(
  () => props.shift,
  (newShift) => {
    if (newShift) {
      auditStatus.value = newShift.reconciliation_status || (newShift.variance === 0 ? 'matched' : 'exception')
      managerNote.value = newShift.review_note || ''
    }
  },
  { immediate: true }
)

function handleSave() {
  if (!props.shift) return
  emit('saveDecision', {
    shiftId: props.shift.id,
    status: auditStatus.value,
    note: managerNote.value.trim() || undefined,
  })
}
</script>

<template>
  <div v-if="show && shift" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">receipt_long</span>
          <div>
            <h3 class="modal-title">{{ t('admin.shifts.reviewModalTitle') }}</h3>
            <span class="modal-sub-code font-mono">{{ shift.code }} · {{ resolveLocalizedText(shift.venue_name) }}</span>
          </div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- Top Info Header -->
        <div class="shift-meta-banner">
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.shifts.cashierNameLabel') }}</span>
            <strong class="meta-val">{{ shift.cashier_name }}</strong>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.shifts.openedAtLabel') }}</span>
            <span class="meta-val font-mono">{{ shift.opened_at ? formatDateTime(shift.opened_at) : 'N/A' }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.shifts.closedAtLabel') }}</span>
            <span class="meta-val font-mono">{{ shift.closed_at ? formatDateTime(shift.closed_at) : (shift.status === 'open' ? t('admin.shifts.statusLive') : 'N/A') }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.shifts.hdrReconciliationStatus') }}</span>
            <span class="status-badge" :class="`status-${shift.reconciliation_status}`">
              {{
                shift.reconciliation_status === 'matched'
                  ? t('admin.shifts.statusMatched')
                  : shift.reconciliation_status === 'approved'
                  ? t('admin.shifts.statusApproved')
                  : t('admin.shifts.statusException')
              }}
            </span>
          </div>
        </div>

        <!-- Section 1: Revenue Breakdown -->
        <div class="detail-section">
          <h4 class="section-title">
            <span class="material-symbols-outlined icon-16">analytics</span>
            <span>{{ t('admin.shifts.revenueBreakdown') }}</span>
          </h4>

          <div class="metrics-grid">
            <div class="metric-row">
              <span class="metric-key">{{ t('admin.shifts.ordersTotal') }}</span>
              <strong class="metric-val font-mono">{{ shift.orders_count }}</strong>
            </div>
            <div class="metric-row">
              <span class="metric-key">{{ t('admin.shifts.cashSales') }}</span>
              <strong class="metric-val text-green font-mono">+{{ formatCurrency(shift.cash_sales) }}</strong>
            </div>
            <div class="metric-row">
              <span class="metric-key">{{ t('admin.shifts.qrSales') }}</span>
              <strong class="metric-val text-blue font-mono">+{{ formatCurrency(shift.qr_sales) }}</strong>
            </div>
            <div class="metric-row">
              <span class="metric-key">{{ t('admin.shifts.walletSales') }}</span>
              <strong class="metric-val text-blue font-mono">+{{ formatCurrency(shift.wallet_sales) }}</strong>
            </div>
            <div class="metric-row total-row">
              <span class="metric-key">{{ t('admin.shifts.totalSalesLabel') }}</span>
              <strong class="metric-val text-pink font-mono">{{ formatCurrency(shift.total_sales) }}</strong>
            </div>
          </div>
        </div>

        <!-- Section 2: Cash Drawer Balancing -->
        <div class="detail-section">
          <h4 class="section-title">
            <span class="material-symbols-outlined icon-16">account_balance_wallet</span>
            <span>{{ t('admin.shifts.drawerAudit') }}</span>
          </h4>

          <div class="audit-grid">
            <div class="audit-box">
              <span class="audit-lbl">{{ t('admin.shifts.initialFloat') }}</span>
              <strong class="audit-val font-mono">{{ formatCurrency(shift.opening_cash) }}</strong>
            </div>
            <div class="audit-box">
              <span class="audit-lbl">{{ t('admin.shifts.calculatedExpected') }}</span>
              <strong class="audit-val font-mono text-ink">{{ formatCurrency(shift.expected_cash) }}</strong>
            </div>
            <div class="audit-box">
              <span class="audit-lbl">{{ t('admin.shifts.declaredByCashier') }}</span>
              <strong class="audit-val font-mono text-ink">{{ formatCurrency(shift.declared_cash) }}</strong>
            </div>
            <div class="audit-box highlight-audit" :class="{ 'has-variance': shift.variance !== 0 }">
              <span class="audit-lbl">{{ t('admin.shifts.finalDiscrepancy') }}</span>
              <strong
                class="audit-val font-mono"
                :class="shift.variance === 0 ? 'text-green' : shift.variance < 0 ? 'text-red' : 'text-yellow'"
              >
                {{ shift.variance > 0 ? '+' : '' }}{{ formatCurrency(shift.variance) }}
              </strong>
            </div>
          </div>
        </div>

        <!-- Section 3: Cashier Handover Note -->
        <div class="detail-section">
          <h4 class="section-title">
            <span class="material-symbols-outlined icon-16">chat</span>
            <span>{{ t('admin.shifts.cashierHandoverNote') }}</span>
          </h4>
          <div class="note-display-box">
            <p v-if="shift.note" class="note-text">{{ shift.note }}</p>
            <span v-else class="note-empty">{{ t('admin.shifts.noCashierNote') }}</span>
          </div>
        </div>

        <!-- Section 4: Manager Audit Decision Form -->
        <div class="detail-section manager-section">
          <h4 class="section-title">
            <span class="material-symbols-outlined icon-16 text-pink">verified_user</span>
            <span>{{ t('admin.shifts.managerAuditSection') }}</span>
          </h4>

          <div class="manager-form-grid">
            <div class="form-group">
              <label class="form-label">{{ t('admin.shifts.auditDecision') }}</label>
              <SelectDropdown
                :model-value="auditStatus"
                :options="decisionOptions"
                variant="bordered"
                @update:model-value="auditStatus = String($event)"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.shifts.managerNoteLabel') }}</label>
              <textarea
                v-model="managerNote"
                rows="2"
                class="form-textarea"
                :placeholder="t('admin.shifts.managerNotePlaceholder')"
              ></textarea>
            </div>

            <div v-if="shift.reviewed_by" class="reviewed-stamp">
              <span class="material-symbols-outlined icon-14 text-green">verified</span>
              <span>Đã duyệt bởi <strong>{{ shift.reviewed_by }}</strong> vào lúc {{ shift.reviewed_at ? formatDateTime(shift.reviewed_at) : '' }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" :disabled="saving" @click="emit('close')">
          {{ t('common.close') }}
        </AuraButton>
        <AuraButton
          variant="primary"
          :disabled="saving"
          @click="handleSave"
        >
          <span
            class="material-symbols-outlined icon-16"
            :class="{ 'spin-icon': saving }"
          >
            save
          </span>
          <span>{{ t('admin.shifts.saveAuditDecision') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 680px;
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: 90vh;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-sub-code {
  font-size: 12px;
  color: var(--muted);
}

.modal-close-btn {
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font-size: 16px;
  padding: 4px;
}

.modal-body {
  padding: 18px 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.shift-meta-banner {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  padding: 12px 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.meta-lbl {
  font-size: 11px;
  color: var(--muted);
}

.meta-val {
  font-size: 12.5px;
  color: var(--ink);
}

.detail-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-title {
  margin: 0;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 6px;
}

.metrics-grid {
  display: flex;
  flex-direction: column;
  border-radius: 10px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.metric-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 14px;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.metric-row:last-child {
  border-bottom: 0;
}

.total-row {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-weight: 700;
}

.metric-key {
  color: var(--muted);
}

.audit-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

.audit-box {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 12px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.highlight-audit {
  background: rgba(16, 185, 129, 0.06);
  border-color: rgba(16, 185, 129, 0.2);
}

.highlight-audit.has-variance {
  background: rgba(239, 68, 68, 0.06);
  border-color: rgba(239, 68, 68, 0.2);
}

.audit-lbl {
  font-size: 11px;
  color: var(--muted);
}

.audit-val {
  font-size: 15px;
  color: var(--ink);
}

.note-display-box {
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.note-text {
  margin: 0;
  font-size: 13px;
  color: var(--ink);
  line-height: 1.4;
}

.note-empty {
  font-size: 12.5px;
  color: var(--muted);
  font-style: italic;
}

.manager-section {
  padding: 16px;
  border-radius: 14px;
  background: rgba(219, 39, 119, 0.03);
  border: 1px solid rgba(219, 39, 119, 0.15);
}

.manager-form-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.form-textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
  resize: vertical;
}

.reviewed-stamp {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #10b981;
}

.status-badge {
  display: inline-flex;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 6px;
  width: fit-content;
}

.status-matched {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.status-approved {
  background: rgba(139, 92, 246, 0.12);
  color: #8b5cf6;
}

.status-exception, .status-rejected {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-blue { color: #3b82f6; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }
.text-ink { color: var(--ink); }

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--outline);
}

.icon-14 { font-size: 14px; }
.icon-16 { font-size: 16px; }

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

:global(html.dark) .modal-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .shift-meta-banner,
:global(html.dark) .audit-box,
:global(html.dark) .note-display-box {
  background: rgba(255, 255, 255, 0.02);
}

@media (max-width: 640px) {
  .shift-meta-banner, .audit-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
