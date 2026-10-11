<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { errorMessage, idempotencyKey } from '@/core/api/client'
import { bookingApi } from '../../api/bookingApi'
import type {
  ChangeableBooking,
  RefundQuote,
  RefundObligation,
  RefundRecipient,
} from '../../types/reservationChanges'

const props = withDefaults(
  defineProps<{
    show: boolean
    booking: ChangeableBooking | null
    v2?: boolean
  }>(),
  { v2: false },
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'updated'): void
}>()

const { t } = useI18n()

const scope = ref<'whole' | 'partial'>('whole')
const selectedSlotIds = ref<number[]>([])
const reason = ref('')
const recipient = ref<RefundRecipient>({ bank_code: '', account_name: '', account_number: '' })

const quoting = ref(false)
const submitting = ref(false)
const error = ref('')
const quote = ref<RefundQuote | null>(null)
const obligation = ref<RefundObligation | null>(null)

// Stable idempotency key reused across retries.
const requestKey = ref('')

function reset() {
  scope.value = 'whole'
  selectedSlotIds.value = []
  reason.value = ''
  recipient.value = { bank_code: '', account_name: '', account_number: '' }
  quoting.value = false
  submitting.value = false
  error.value = ''
  quote.value = null
  obligation.value = null
  requestKey.value = ''
}

const slotsWithId = computed(() =>
  (props.booking?.slots ?? []).filter(
    (s): s is typeof s & { id: number } => typeof s.id === 'number',
  ),
)

const targetSlotIds = computed<number[]>(() =>
  scope.value === 'whole' ? slotsWithId.value.map((s) => s.id) : selectedSlotIds.value,
)

const needsRecipient = computed(() => (quote.value?.calculation.refund_amount ?? 0) > 0)

function toggleSlot(id: number) {
  const idx = selectedSlotIds.value.indexOf(id)
  if (idx >= 0) selectedSlotIds.value.splice(idx, 1)
  else selectedSlotIds.value.push(id)
  // A changed selection invalidates a prior captured quote.
  quote.value = null
}

const feeTypeLabel = computed(() => {
  const c = quote.value?.calculation
  if (!c) return ''
  if (c.fee_type === 'none') return t('v2Reservations.refund.feeTypeNone')
  if (c.fee_type === 'fixed') return t('v2Reservations.refund.feeTypeFixed')
  return t('v2Reservations.refund.feeTypePercentage', { value: c.fee_value })
})

async function runQuote() {
  if (!props.booking) return
  const ids = targetSlotIds.value
  if (ids.length === 0) {
    error.value = t('v2Reservations.refund.selectSlots')
    return
  }
  quoting.value = true
  error.value = ''
  try {
    const res = await bookingApi.getRefundQuote(props.booking.code, ids, props.v2)
    quote.value = res.data
  } catch (cause: unknown) {
    quote.value = null
    error.value = errorMessage(cause, t('v2Reservations.refund.quoteError'))
  } finally {
    quoting.value = false
  }
}

async function submit() {
  if (!props.booking || !quote.value) return
  const ids = targetSlotIds.value
  if (ids.length === 0) {
    error.value = t('v2Reservations.refund.selectSlots')
    return
  }
  if (!reason.value.trim()) {
    error.value = t('v2Reservations.refund.reasonRequired')
    return
  }
  if (
    needsRecipient.value &&
    !(recipient.value.bank_code && recipient.value.account_name && recipient.value.account_number)
  ) {
    error.value = t('v2Reservations.refund.recipientRequired')
    return
  }
  if (!requestKey.value) requestKey.value = idempotencyKey()

  const payload = {
    slot_ids: ids,
    reason: reason.value.trim(),
    idempotency_key: requestKey.value,
    ...(needsRecipient.value ? { recipient: { ...recipient.value } } : {}),
  }

  submitting.value = true
  error.value = ''
  try {
    const res = await bookingApi.createRefundRequest(props.booking.code, payload, props.v2)
    obligation.value = res.data
    emit('updated')
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('v2Reservations.refund.submitError'))
  } finally {
    submitting.value = false
  }
}

const statusLabel = computed(() => {
  const s = obligation.value?.status
  if (!s) return ''
  const key = `v2Reservations.refund.status.${s}`
  const label = t(key)
  return label === key ? s : label
})

watch(
  () => props.show,
  (show) => {
    if (show) reset()
  },
  { immediate: true },
)
</script>

<template>
  <div
    v-if="show && booking"
    class="modal-backdrop"
    @click.self="emit('close')"
    data-testid="cancel-modal"
  >
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h3 class="modal-title">{{ t('v2Reservations.refund.cancelTitle') }}</h3>
          <p class="modal-sub">{{ t('v2Reservations.refund.cancelSubtitle') }}</p>
        </div>
        <button class="modal-close-btn" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- Submitted result: truthful server status, never "completed" -->
        <div v-if="obligation" class="result-box" data-testid="cancel-result">
          <p class="result-status" data-testid="cancel-result-status">
            {{ t('v2Reservations.refund.statusLabel') }}: {{ statusLabel }}
          </p>
          <p class="result-code">
            {{ t('v2Reservations.refund.referenceLabel') }}:
            <strong class="font-mono">{{ obligation.reference_code }}</strong>
          </p>
          <p class="result-amount">
            {{ t('v2Reservations.refund.refundLabel') }}:
            <strong class="font-mono">{{ formatCurrency(obligation.refund_amount) }}</strong>
          </p>
          <div class="notice-warn" data-testid="cancel-not-verified">
            {{ t('v2Reservations.refund.notVerifiedHint') }}
          </div>
        </div>

        <template v-else>
          <!-- Scope: whole / partial -->
          <div class="scope-row">
            <label class="scope-option">
              <input
                type="radio"
                value="whole"
                v-model="scope"
                data-testid="cancel-scope-whole"
                @change="quote = null"
              />
              <span>{{ t('v2Reservations.refund.scopeWhole') }}</span>
            </label>
            <label class="scope-option">
              <input
                type="radio"
                value="partial"
                v-model="scope"
                data-testid="cancel-scope-partial"
                @change="quote = null"
              />
              <span>{{ t('v2Reservations.refund.scopePartial') }}</span>
            </label>
          </div>

          <div v-if="scope === 'partial'" class="slots-box">
            <p class="form-label">{{ t('v2Reservations.refund.selectSlotsLabel') }}</p>
            <label v-for="s in slotsWithId" :key="s.id" class="slot-option">
              <input
                type="checkbox"
                :checked="selectedSlotIds.includes(s.id)"
                :data-testid="`cancel-slot-${s.id}`"
                @change="toggleSlot(s.id)"
              />
              <span class="font-mono"
                >{{ s.starts_at.slice(11, 16) }} - {{ s.ends_at?.slice(11, 16) }}</span
              >
              <span class="slot-price font-mono">{{ formatCurrency(s.unit_price) }}</span>
            </label>
          </div>

          <button
            class="btn btn-secondary"
            type="button"
            :disabled="quoting"
            data-testid="cancel-quote-btn"
            @click="runQuote"
          >
            {{ quoting ? t('v2Reservations.refund.quoting') : t('v2Reservations.refund.quoteBtn') }}
          </button>

          <!-- Captured policy calculation -->
          <div v-if="quote" class="preview-box" data-testid="cancel-quote">
            <div class="preview-row">
              <span>{{ t('v2Reservations.refund.deadlineLabel') }}</span>
              <span class="font-mono" data-testid="cancel-deadline">{{
                quote.calculation.due_at
              }}</span>
            </div>
            <div class="preview-row">
              <span>{{ t('v2Reservations.refund.grossLabel') }}</span>
              <span class="font-mono">{{ formatCurrency(quote.calculation.gross_amount) }}</span>
            </div>
            <div class="preview-row">
              <span>{{ t('v2Reservations.refund.feeLabel') }} ({{ feeTypeLabel }})</span>
              <span class="font-mono" data-testid="cancel-fee">{{
                formatCurrency(quote.calculation.fee_amount)
              }}</span>
            </div>
            <div class="preview-row emphasis">
              <span>{{ t('v2Reservations.refund.refundLabel') }}</span>
              <span class="font-mono" data-testid="cancel-refund">{{
                formatCurrency(quote.calculation.refund_amount)
              }}</span>
            </div>
            <div v-if="quote.points_to_restore" class="preview-row">
              <span>{{ t('v2Reservations.refund.pointsRestoreLabel') }}</span>
              <span class="font-mono">{{ quote.points_to_restore }}</span>
            </div>
            <p
              v-if="quote.calculation.refund_amount === 0"
              class="hint"
              data-testid="cancel-no-money"
            >
              {{ t('v2Reservations.refund.noMoneyBack') }}
            </p>

            <div class="form-row">
              <label class="form-label" for="cancel-reason">{{
                t('v2Reservations.refund.reasonLabel')
              }}</label>
              <textarea
                id="cancel-reason"
                v-model="reason"
                class="form-input"
                rows="2"
                :placeholder="t('v2Reservations.refund.reasonPlaceholder')"
                data-testid="cancel-reason"
              ></textarea>
            </div>

            <div v-if="needsRecipient" class="recipient-box">
              <p class="recipient-title">{{ t('v2Reservations.refund.recipientHeading') }}</p>
              <p class="hint">{{ t('v2Reservations.refund.recipientHint') }}</p>
              <input
                v-model="recipient.bank_code"
                class="form-input"
                :placeholder="t('v2Reservations.refund.bankCode')"
                data-testid="cancel-recipient-bank"
              />
              <input
                v-model="recipient.account_name"
                class="form-input"
                :placeholder="t('v2Reservations.refund.accountName')"
                data-testid="cancel-recipient-name"
              />
              <input
                v-model="recipient.account_number"
                class="form-input"
                :placeholder="t('v2Reservations.refund.accountNumber')"
                data-testid="cancel-recipient-number"
              />
            </div>
          </div>
        </template>

        <div v-if="error" class="error-box" data-testid="cancel-error">
          <span class="material-symbols-outlined">error</span>
          <span>{{ error }}</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" type="button" @click="emit('close')">
          {{ obligation ? t('v2Reservations.refund.close') : t('v2Reservations.refund.cancel') }}
        </button>
        <button
          v-if="!obligation"
          class="btn btn-primary"
          type="button"
          :disabled="!quote || submitting"
          data-testid="cancel-submit-btn"
          @click="submit"
        >
          {{
            submitting
              ? t('v2Reservations.refund.submitting')
              : t('v2Reservations.refund.submitBtn')
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}
.modal-card {
  background: var(--surface);
  color: var(--ink);
  border-radius: 20px;
  width: 100%;
  max-width: 520px;
  border: 1px solid var(--outline);
  box-shadow: 0 20px 48px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  overflow: hidden;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 22px 12px;
  border-bottom: 1px solid var(--outline);
}
.modal-title {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
}
.modal-sub {
  margin: 6px 0 0;
  font-size: 0.82rem;
  color: var(--muted);
}
.modal-close-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--muted);
}
.modal-body {
  padding: 18px 22px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.scope-row {
  display: flex;
  gap: 18px;
}
.scope-option,
.slot-option {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.88rem;
}
.slots-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  padding: 12px;
}
.slot-option {
  justify-content: flex-start;
}
.slot-price {
  margin-left: auto;
}
.form-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--muted);
  margin: 0;
}
.form-input {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-family: inherit;
}
.preview-box,
.result-box,
.recipient-box {
  border: 1px solid var(--outline);
  border-radius: 12px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.preview-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.86rem;
}
.preview-row.emphasis {
  font-weight: 700;
}
.recipient-title {
  font-size: 0.82rem;
  font-weight: 600;
  margin: 0;
}
.hint {
  font-size: 0.78rem;
  color: var(--muted);
  margin: 0;
}
.notice-warn {
  background: rgba(234, 179, 8, 0.12);
  border-radius: 10px;
  padding: 12px;
  font-size: 0.82rem;
}
.error-box {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #ef4444;
  font-size: 0.85rem;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px;
  border-top: 1px solid var(--outline);
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 10px;
  border: 1px solid transparent;
  cursor: pointer;
  font-weight: 600;
}
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-primary {
  background: var(--accent, #c9a15a);
  color: #1a0e02;
}
.btn-secondary {
  background: var(--soft, rgba(0, 0, 0, 0.06));
  color: var(--ink);
}
.btn-ghost {
  background: none;
  border-color: var(--outline);
  color: var(--ink);
}
</style>
