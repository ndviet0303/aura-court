<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { RecurringContractItem } from '../../types/contract'

defineProps<{
  show: boolean
  contract: RecurringContractItem | null
  actionLoading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'cancelContract', contractId: number): void
}>()

const { t } = useI18n()

const CYCLE_STATUS_KEYS: Record<string, string> = {
  paid: 'admin.contracts.statusPaid',
  zero_payable: 'admin.contracts.statusZeroPayable',
  guaranteed: 'admin.contracts.statusGuaranteed',
  overdue: 'admin.contracts.statusOverdue',
  cancelled: 'admin.contracts.statusCancelled',
}

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('vi-VN')
}
</script>

<template>
  <div v-if="show && contract" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">description</span>
          <div>
            <h3 class="modal-title">
              {{ t('admin.contracts.contractDetailTitle', { code: contract.contract_code }) }}
            </h3>
            <span
              class="status-tag mt-1"
              :class="{
                'tag-active': contract.status === 'active' || contract.status === 'confirmed',
                'tag-overdue': contract.status === 'overdue',
                'tag-cancelled': contract.status === 'cancelled',
              }"
            >
              ●
              {{
                contract.status === 'active' || contract.status === 'confirmed'
                  ? t('admin.contracts.activeEffective')
                  : contract.status === 'overdue'
                    ? t('admin.contracts.overdueBadge')
                    : t('admin.contracts.statusCancelled')
              }}
            </span>
          </div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- Customer & Contract Meta Grid -->
        <div class="contract-meta-grid">
          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.contracts.customerClubLabel') }}</span>
            <strong class="text-ink">{{ contract.customer_name }}</strong>
            <span class="sub-txt font-mono"
              >{{ contract.phone }} {{ contract.email ? `· ${contract.email}` : '' }}</span
            >
          </div>

          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.contracts.fixedCourtLabel') }}</span>
            <strong class="text-ink">{{ resolveLocalizedText(contract.court_name) }}</strong>
            <span class="sub-txt">{{ resolveLocalizedText(contract.venue_name) }}</span>
          </div>

          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.contracts.scheduleAndSlotLabel') }}</span>
            <strong class="text-ink">{{ contract.weekday }}</strong>
            <span class="sub-txt font-mono"
              >{{ contract.time_slot }} ({{ contract.frequency }})</span
            >
          </div>

          <div class="meta-item">
            <span class="meta-lbl">{{ t('admin.contracts.contractTermLabel') }}</span>
            <strong class="font-mono text-ink"
              >{{ formatDate(contract.start_date) }} - {{ formatDate(contract.end_date) }}</strong
            >
            <span class="sub-txt text-pink font-mono font-bold"
              >{{ formatCurrency(contract.total_monthly_amount) }} / tháng</span
            >
          </div>
        </div>

        <!-- Monthly Invoice Cycles Section -->
        <div class="section-box">
          <div class="section-hdr">
            <span class="material-symbols-outlined sec-icon text-pink">receipt_long</span>
            <h4 class="sec-title">{{ t('admin.contracts.invoiceCyclesTitle') }}</h4>
          </div>

          <div class="cycles-table-wrap">
            <div class="cycles-header">
              <span>{{ t('admin.contracts.hdrInvoiceCycle') }}</span>
              <span>{{ t('admin.contracts.hdrDueDate') }}</span>
              <span>{{ t('admin.contracts.hdrAmount') }}</span>
              <span>{{ t('admin.contracts.hdrCycleStatus') }}</span>
              <span>{{ t('admin.contracts.hdrReceiptCode') }}</span>
              <span class="text-right">{{ t('admin.contracts.hdrActions') }}</span>
            </div>

            <div v-if="!contract.invoices || contract.invoices.length === 0" class="no-cycles-sub">
              {{ t('admin.contracts.noInvoicesYet') }}
            </div>

            <div v-for="(cycle, idx) in contract.invoices" :key="cycle.id" class="cycle-row">
              <div class="cycle-period">
                <strong>{{ t('admin.contracts.cycleLabel', { index: idx + 1 }) }}</strong>
                <span class="period-sub font-mono"
                  >{{ formatDate(cycle.period_start) }} - {{ formatDate(cycle.period_end) }}</span
                >
              </div>

              <span class="font-mono">{{
                cycle.status === 'zero_payable' ? '—' : formatDate(cycle.due_at)
              }}</span>
              <strong class="text-pink font-mono">{{ formatCurrency(cycle.amount) }}</strong>

              <div>
                <span
                  class="cycle-status"
                  :class="{
                    paid: cycle.status === 'paid',
                    overdue: cycle.status === 'overdue',
                    pending:
                      cycle.status === 'pending' ||
                      cycle.status === 'guaranteed' ||
                      cycle.status === 'zero_payable',
                  }"
                >
                  ●
                  {{ t(CYCLE_STATUS_KEYS[cycle.status] ?? 'admin.contracts.statusUnpaid') }}
                </span>
              </div>

              <span class="font-mono receipt-code">{{ cycle.payment_reference || '—' }}</span>

              <div class="action-cell">
                <span v-if="cycle.status === 'paid'" class="paid-badge">
                  <span class="material-symbols-outlined icon-14 text-green">check_circle</span>
                  <span>{{ formatDate(cycle.paid_at) }}</span>
                </span>
                <span v-else-if="cycle.status === 'zero_payable'" class="text-muted">{{
                  t('admin.contracts.statusZeroPayable')
                }}</span>
                <span v-else-if="cycle.status === 'cancelled'" class="text-muted">—</span>
                <span v-else class="text-muted">{{
                  t('admin.contracts.awaitingVerifiedReceipt')
                }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Occurrences List Section -->
        <div
          v-if="contract.occurrences && contract.occurrences.length > 0"
          class="section-box mt-4"
        >
          <div class="section-hdr">
            <span class="material-symbols-outlined sec-icon">calendar_month</span>
            <h4 class="sec-title">
              {{ t('admin.contracts.occurrencesTitle') }} ({{ contract.occurrences.length }}
              {{ t('admin.contracts.sessions') }})
            </h4>
          </div>

          <div class="occurrences-grid">
            <div
              v-for="occ in contract.occurrences.slice(0, 8)"
              :key="occ.id"
              class="occ-badge"
              :class="{
                'occ-confirmed': occ.status === 'confirmed',
                'occ-released': occ.status === 'released' || occ.status === 'cancelled',
              }"
            >
              <span class="occ-seq font-mono">#{{ occ.sequence }}</span>
              <span class="occ-date">{{ formatDate(occ.starts_at) }}</span>
              <span class="occ-status">{{ occ.status === 'confirmed' ? '✓' : '✕' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton
          v-if="contract.status !== 'cancelled'"
          variant="danger"
          :disabled="actionLoading"
          @click="emit('cancelContract', contract.id)"
        >
          <span class="material-symbols-outlined icon-16">cancel</span>
          <span>{{ t('admin.contracts.terminateContractBtn') }}</span>
        </AuraButton>

        <AuraButton variant="outline" @click="emit('close')">
          {{ t('common.close') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  width: 100%;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.18);
  overflow: hidden;
  margin: auto;
}

.modal-lg {
  max-width: 860px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
  flex-shrink: 0;
}

.title-with-icon {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.card-title-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
  margin-top: 2px;
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: none;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  line-height: 1;
}

.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.contract-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
  padding: 16px;
  border-radius: 14px;
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
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.text-ink {
  color: var(--ink);
}

.sub-txt {
  font-size: 12px;
  color: var(--muted);
}

.section-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-hdr {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sec-icon {
  font-size: 18px;
  color: var(--muted);
}

.sec-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.cycles-table-wrap {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.cycles-header {
  display: grid;
  grid-template-columns: 2fr 1.2fr 1.2fr 1.2fr 1.5fr 110px;
  padding: 10px 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
}

.cycle-row {
  display: grid;
  grid-template-columns: 2fr 1.2fr 1.2fr 1.2fr 1.5fr 110px;
  padding: 12px 14px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
}

.cycle-row:last-child {
  border-bottom: 0;
}

.cycle-period {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.period-sub {
  font-size: 10px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.text-green {
  color: #10b981;
}

.font-bold {
  font-weight: 700;
}

.cycle-status {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  display: inline-block;
}

.cycle-status.paid {
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
}
.cycle-status.overdue {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
}
.cycle-status.pending {
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
}

.receipt-code {
  font-size: 11px;
  color: var(--muted);
}

.action-cell {
  display: flex;
  justify-content: flex-end;
}

.btn-pay-now {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.08);
  color: var(--pink-dark, #db2777);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-pay-now:hover {
  background: var(--pink-dark, #db2777);
  color: #fff;
}

.paid-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted);
  font-family: monospace;
}

.occurrences-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.occ-badge {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  font-size: 11px;
}

.occ-confirmed {
  background: rgba(16, 185, 129, 0.05);
  border-color: rgba(16, 185, 129, 0.2);
}

.occ-released {
  opacity: 0.5;
  text-decoration: line-through;
}

.occ-seq {
  font-weight: 700;
  color: var(--muted);
}

.status-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  display: inline-block;
}

.tag-active {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}
.tag-overdue {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}
.tag-cancelled {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
  flex-shrink: 0;
}

.btn-danger-outline {
  border-color: rgba(239, 68, 68, 0.3) !important;
  color: #ef4444 !important;
}

.btn-danger-outline:hover {
  background: rgba(239, 68, 68, 0.08) !important;
}

.no-cycles-sub {
  padding: 16px;
  text-align: center;
  color: var(--muted);
  font-size: 12px;
}

.icon-14 {
  font-size: 14px;
}
.icon-16 {
  font-size: 16px;
}
.mt-1 {
  margin-top: 4px;
}
.mt-4 {
  margin-top: 16px;
}
.text-right {
  text-align: right;
}
</style>
