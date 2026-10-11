<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { errorMessage } from '@/core/api/client'
import { refundReviewApi, type RefundObligation } from '../../api/refundReviewApi'

const props = defineProps<{
  refunds: RefundObligation[]
  loading?: boolean
  reload?: () => void | Promise<void>
}>()

const emit = defineEmits<{ (e: 'changed'): void }>()

const { t } = useI18n()

const actionError = ref('')
const actionNotice = ref('')
const pendingId = ref<number | null>(null)

const showRejectFor = reactive<Record<number, boolean>>({})
const rejectReason = reactive<Record<number, string>>({})
const transferReference = reactive<Record<number, string>>({})
const transferEvidence = reactive<Record<number, string>>({})

function statusLabel(status: string): string {
  return t(`admin.manage.refundReview.status.${status}`)
}

async function runAction(id: number, action: () => Promise<unknown>, successKey: string) {
  if (pendingId.value !== null) return
  actionError.value = ''
  actionNotice.value = ''
  pendingId.value = id
  try {
    await action()
    actionNotice.value = t(successKey)
    showRejectFor[id] = false
    await props.reload?.()
    emit('changed')
  } catch (cause: unknown) {
    actionError.value = errorMessage(cause, t('admin.manage.refundReview.toasts.failed'))
  } finally {
    pendingId.value = null
  }
}

function approve(id: number) {
  return runAction(
    id,
    () => refundReviewApi.approveRefund(id),
    'admin.manage.refundReview.toasts.approved',
  )
}

function openReject(id: number) {
  actionError.value = ''
  showRejectFor[id] = true
}

function cancelReject(id: number) {
  showRejectFor[id] = false
}

function submitReject(id: number) {
  const reason = (rejectReason[id] ?? '').trim()
  return runAction(
    id,
    () => refundReviewApi.rejectRefund(id, reason),
    'admin.manage.refundReview.toasts.rejected',
  )
}

function submitReport(id: number) {
  const body = {
    transfer_reference: (transferReference[id] ?? '').trim(),
    evidence: (transferEvidence[id] ?? '').trim(),
  }
  return runAction(
    id,
    () => refundReviewApi.reportRefundExecution(id, body),
    'admin.manage.refundReview.toasts.reported',
  )
}
</script>

<template>
  <div class="refunds-panel-container">
    <div class="refunds-header">
      <div>
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">gavel</span>
          <h3 class="panel-headline">{{ t('admin.manage.refundReview.title') }}</h3>
        </div>
        <p class="panel-sub">{{ t('admin.manage.refundReview.subtitle') }}</p>
      </div>
      <span class="refund-count-badge font-mono">{{
        t('admin.manage.refundsCount', { count: refunds.length })
      }}</span>
    </div>

    <!-- Action feedback -->
    <p v-if="actionError" class="action-alert error" data-testid="refund-action-error">
      {{ actionError }}
    </p>
    <p v-if="actionNotice" class="action-alert success" data-testid="refund-action-notice">
      {{ actionNotice }}
    </p>

    <!-- Skeleton Loading State -->
    <div v-if="loading" class="refunds-list">
      <div v-for="i in 4" :key="i" class="refund-card skeleton-card">
        <div class="ref-info-col">
          <div class="ref-title-row">
            <div class="skeleton-item skeleton-code"></div>
            <div class="skeleton-item skeleton-amt"></div>
          </div>
          <div class="skeleton-item skeleton-text"></div>
          <div class="skeleton-item skeleton-text short"></div>
        </div>

        <div class="ref-action-col">
          <div class="skeleton-item skeleton-status-badge"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="refunds.length === 0" class="empty-refunds-box">
      <span class="material-symbols-outlined empty-icon">verified_user</span>
      <p>{{ t('admin.manage.noPendingRefunds') }}</p>
    </div>

    <!-- Obligation Review List -->
    <div v-else class="refunds-list">
      <div
        v-for="o in refunds"
        :key="o.id"
        class="refund-card"
        :data-testid="`refund-card-${o.id}`"
      >
        <div class="ref-info-col">
          <div class="ref-title-row">
            <span class="ref-booking-code font-mono">{{
              o.reference_code || o.reservation_code || `#${o.id}`
            }}</span>
            <strong class="ref-amount text-pink font-mono">{{
              formatCurrency(o.refund_amount)
            }}</strong>
          </div>
          <p v-if="o.reservation_code" class="ref-reason font-mono">{{ o.reservation_code }}</p>
          <p v-if="o.reason" class="ref-reason">{{ o.reason }}</p>

          <!-- Evidence timeline -->
          <ul
            v-if="o.evidence && o.evidence.length"
            class="evidence-timeline"
            :data-testid="`refund-evidence-timeline-${o.id}`"
          >
            <li v-for="ev in o.evidence" :key="ev.id" class="evidence-item">
              <span class="material-symbols-outlined icon-14">radio_button_checked</span>
              <span class="evidence-type">{{ ev.event_type }}</span>
              <span v-if="ev.created_at" class="evidence-time font-mono">{{ ev.created_at }}</span>
            </li>
          </ul>
        </div>

        <div class="ref-action-col">
          <span
            class="status-badge"
            :class="`status-${o.status}`"
            :data-testid="`refund-status-${o.id}`"
          >
            {{ statusLabel(o.status) }}
          </span>

          <!-- requested: approve / reject with reason -->
          <div v-if="o.status === 'requested'" class="action-group">
            <button
              class="btn-review btn-approve"
              :disabled="pendingId !== null"
              :data-testid="`refund-approve-${o.id}`"
              @click="approve(o.id)"
            >
              {{ t('admin.manage.refundReview.actions.approve') }}
            </button>

            <button
              v-if="!showRejectFor[o.id]"
              class="btn-review btn-reject"
              :disabled="pendingId !== null"
              :data-testid="`refund-reject-${o.id}`"
              @click="openReject(o.id)"
            >
              {{ t('admin.manage.refundReview.actions.reject') }}
            </button>

            <div v-else class="review-form">
              <textarea
                v-model="rejectReason[o.id]"
                class="review-input"
                rows="2"
                :placeholder="t('admin.manage.refundReview.fields.rejectReason')"
                :data-testid="`refund-reject-reason-${o.id}`"
              ></textarea>
              <div class="form-actions">
                <button
                  class="btn-review btn-reject"
                  :disabled="pendingId !== null"
                  :data-testid="`refund-reject-submit-${o.id}`"
                  @click="submitReject(o.id)"
                >
                  {{ t('admin.manage.refundReview.actions.submit') }}
                </button>
                <button
                  class="btn-review btn-cancel"
                  :disabled="pendingId !== null"
                  :data-testid="`refund-reject-cancel-${o.id}`"
                  @click="cancelReject(o.id)"
                >
                  {{ t('admin.manage.refundReview.actions.cancel') }}
                </button>
              </div>
            </div>
          </div>

          <!-- approved: owner reports the executed transfer -->
          <div v-else-if="o.status === 'approved'" class="action-group review-form">
            <input
              v-model="transferReference[o.id]"
              class="review-input"
              type="text"
              :placeholder="t('admin.manage.refundReview.fields.transferReference')"
              :data-testid="`refund-transfer-reference-${o.id}`"
            />
            <textarea
              v-model="transferEvidence[o.id]"
              class="review-input"
              rows="2"
              :placeholder="t('admin.manage.refundReview.fields.evidence')"
              :data-testid="`refund-evidence-input-${o.id}`"
            ></textarea>
            <button
              class="btn-review btn-report"
              :disabled="pendingId !== null"
              :data-testid="`refund-report-${o.id}`"
              @click="submitReport(o.id)"
            >
              {{ t('admin.manage.refundReview.actions.reportExecution') }}
            </button>
          </div>

          <!-- owner_reported: awaiting the payer's acknowledgement (NOT completed) -->
          <p
            v-else-if="o.status === 'owner_reported'"
            class="pending-ack"
            :data-testid="`refund-pending-ack-${o.id}`"
          >
            <span class="material-symbols-outlined icon-14">hourglass_top</span>
            {{ t('admin.manage.refundReview.hints.notVerified') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.refunds-panel-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.refunds-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.panel-headline {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.panel-sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.refund-count-badge {
  padding: 4px 12px;
  border-radius: 20px;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  font-weight: 700;
  font-size: 12px;
}

.action-alert {
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
}

.action-alert.error {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.action-alert.success {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.empty-refunds-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
}

.empty-icon {
  font-size: 36px;
  color: #10b981;
  margin-bottom: 8px;
}

.refunds-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.refund-card {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  flex-wrap: wrap;
  gap: 12px;
}

.ref-info-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 200px;
}

.ref-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ref-booking-code {
  font-weight: 700;
  font-size: 14px;
}

.ref-amount {
  font-size: 14px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.ref-reason {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.evidence-timeline {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  border-left: 2px solid var(--outline);
  padding-left: 10px;
}

.evidence-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--muted);
}

.evidence-type {
  font-weight: 600;
  color: var(--text, inherit);
}

.evidence-time {
  font-size: 10px;
}

.ref-action-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  min-width: 220px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  text-transform: capitalize;
}

/* Requested: awaiting the owner's decision */
.status-requested {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

/* Approved: owner must still execute + report the transfer (not done) */
.status-approved {
  background: rgba(59, 130, 246, 0.12);
  color: #2563eb;
  border: 1px solid rgba(59, 130, 246, 0.3);
}

/* Owner reported: pending the payer's acknowledgement (NOT verified/done) */
.status-owner_reported {
  background: rgba(139, 92, 246, 0.12);
  color: #7c3aed;
  border: 1px solid rgba(139, 92, 246, 0.3);
}

/* Acknowledged: the only settled/completed state */
.status-acknowledged {
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-rejected,
.status-recipient_disputed {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.action-group {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
  width: 220px;
}

.review-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-actions {
  display: flex;
  gap: 8px;
}

.form-actions .btn-review {
  flex: 1;
}

.review-input {
  width: 100%;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--text, inherit);
  font-size: 12px;
  font-family: inherit;
  resize: vertical;
}

.review-input:focus {
  outline: none;
  border-color: var(--pink-dark, #db2777);
}

.btn-review {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition:
    filter 0.15s ease,
    opacity 0.15s ease;
}

.btn-review:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-approve {
  background: #059669;
  color: #fff;
}

.btn-reject {
  background: #dc2626;
  color: #fff;
}

.btn-report {
  background: #2563eb;
  color: #fff;
}

.btn-cancel {
  background: transparent;
  color: var(--muted);
  border-color: var(--outline);
}

.btn-review:not(:disabled):hover {
  filter: brightness(1.05);
}

.pending-ack {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(139, 92, 246, 0.1);
  color: #7c3aed;
  font-size: 12px;
  font-weight: 600;
  text-align: right;
  max-width: 220px;
}

.icon-14 {
  font-size: 14px;
}

/* Shimmer Skeleton */
.skeleton-card {
  pointer-events: none;
}

.skeleton-item {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 6px;
}

.skeleton-item::after {
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

:global(html.dark) .skeleton-item {
  background-color: var(--soft, #2b2e36);
}

:global(html.dark) .skeleton-item::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(120, 85, 94, 0.35) 45%,
    rgba(255, 255, 255, 0.1) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

.skeleton-code {
  width: 90px;
  height: 16px;
}

.skeleton-amt {
  width: 80px;
  height: 16px;
}

.skeleton-text {
  width: 220px;
  height: 12px;
  margin-top: 2px;
}

.skeleton-text.short {
  width: 160px;
}

.skeleton-status-badge {
  width: 90px;
  height: 28px;
  border-radius: 20px;
}

@keyframes shimmer-wave {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}
</style>
