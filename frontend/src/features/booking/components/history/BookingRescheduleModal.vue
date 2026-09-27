<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { errorMessage, idempotencyKey } from '@/core/api/client'
import { bookingApi } from '../../api/bookingApi'
import type { Court } from '@/features/court/types/court'
import type {
  ChangeableBooking,
  ReschedulePreview,
  RescheduleResult,
  RefundRecipient,
} from '../../types/reservationChanges'

const props = withDefaults(
  defineProps<{
    show: boolean
    booking: ChangeableBooking | null
    // When true the modal targets the v2 reservation endpoints (apiV2).
    v2?: boolean
  }>(),
  { v2: false },
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'updated'): void
}>()

const { t } = useI18n()

const courts = ref<Court[]>([])
const courtId = ref<number | null>(null)
const date = ref('')
const time = ref('18:00')

const previewing = ref(false)
const submitting = ref(false)
const error = ref('')
const preview = ref<ReschedulePreview | null>(null)
const result = ref<RescheduleResult | null>(null)
const recipient = ref<RefundRecipient>({ bank_code: '', account_name: '', account_number: '' })

// Stable idempotency key: generated once per open request and reused on retry so
// a repeated submit does not double-create the reschedule request.
const requestKey = ref('')

function todayLocal(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function reset() {
  courtId.value = null
  date.value = todayLocal()
  time.value = '18:00'
  previewing.value = false
  submitting.value = false
  error.value = ''
  preview.value = null
  result.value = null
  requestKey.value = ''
  recipient.value = { bank_code: '', account_name: '', account_number: '' }
}

async function loadCourts() {
  try {
    const list = await bookingApi.getCourts()
    if (Array.isArray(list)) {
      courts.value = list
      if (!courtId.value && list[0]?.id) courtId.value = list[0].id
    }
  } catch {
    // Court list is a convenience; failure surfaces on preview instead.
  }
}

// Discriminate the three policy cases from the server preview.
const previewCase = computed<'extra' | 'refund' | 'equal' | null>(() => {
  const p = preview.value
  if (!p) return null
  if (p.extra_payment_amount > 0) return 'extra'
  if (p.refund_amount > 0) return 'refund'
  return 'equal'
})

const needsRecipient = computed(() => previewCase.value === 'refund')

function slotIso(): string | null {
  if (!date.value || !time.value) return null
  const iso = new Date(`${date.value}T${time.value}:00`)
  if (Number.isNaN(iso.getTime())) return null
  return iso.toISOString()
}

async function runPreview() {
  if (!props.booking) return
  const iso = slotIso()
  if (!courtId.value || !iso) {
    error.value = t('v2Reservations.reschedule.selectCourtAndTime')
    return
  }
  previewing.value = true
  error.value = ''
  // Do not clear a prior preview until we know the outcome; a conflict must not
  // wipe the customer's context, and the original booking is never altered here.
  try {
    const res = await bookingApi.previewReschedule(
      props.booking.code,
      { court_id: Number(courtId.value), slots: [iso] },
      props.v2,
    )
    preview.value = res.data
  } catch (cause: unknown) {
    preview.value = null
    const status = (cause as { status?: number })?.status
    error.value =
      status === 409
        ? t('v2Reservations.reschedule.conflictError')
        : errorMessage(cause, t('v2Reservations.reschedule.previewError'))
  } finally {
    previewing.value = false
  }
}

async function submit() {
  if (!props.booking || !preview.value) return
  const iso = slotIso()
  if (!courtId.value || !iso) {
    error.value = t('v2Reservations.reschedule.selectCourtAndTime')
    return
  }
  // Reuse the same key across retries; only mint one the first time.
  if (!requestKey.value) requestKey.value = idempotencyKey()

  const payload = {
    court_id: Number(courtId.value),
    slots: [iso],
    idempotency_key: requestKey.value,
    ...(needsRecipient.value &&
    recipient.value.bank_code &&
    recipient.value.account_name &&
    recipient.value.account_number
      ? { recipient: { ...recipient.value } }
      : {}),
  }

  submitting.value = true
  error.value = ''
  try {
    const res = await bookingApi.createRescheduleRequest(props.booking.code, payload, props.v2)
    result.value = res.data
    emit('updated')
  } catch (cause: unknown) {
    const status = (cause as { status?: number })?.status
    error.value =
      status === 409
        ? t('v2Reservations.reschedule.conflictError')
        : errorMessage(cause, t('v2Reservations.reschedule.submitError'))
  } finally {
    submitting.value = false
  }
}

watch(
  () => props.show,
  (show) => {
    if (show) {
      reset()
      void loadCourts()
    }
  },
  { immediate: true },
)
</script>

<template>
  <div v-if="show && booking" class="modal-backdrop" @click.self="emit('close')" data-testid="reschedule-modal">
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h3 class="modal-title">{{ t('v2Reservations.reschedule.title') }}</h3>
          <p class="modal-sub">{{ t('v2Reservations.reschedule.subtitle') }}</p>
        </div>
        <button class="modal-close-btn" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- Submitted result state -->
        <div v-if="result" class="result-box" data-testid="reschedule-result">
          <p class="result-status" data-testid="reschedule-result-status">
            {{ t('v2Reservations.reschedule.statusLabel') }}: {{ result.status }}
          </p>
          <p class="result-code">
            {{ t('v2Reservations.reschedule.replacementCodeLabel') }}:
            <strong class="font-mono">{{ result.replacement.code }}</strong>
          </p>
          <div v-if="result.payment_attempt" class="notice-warn" data-testid="reschedule-result-held">
            <strong>{{ t('v2Reservations.reschedule.resultHeldTitle') }}</strong>
            <p>{{ t('v2Reservations.reschedule.resultHeldDesc') }}</p>
          </div>
          <div v-else class="notice-ok" data-testid="reschedule-result-swapped">
            <strong>{{ t('v2Reservations.reschedule.resultSwappedTitle') }}</strong>
            <p v-if="result.refund" data-testid="reschedule-result-refund">
              {{ t('v2Reservations.reschedule.resultRefundCreatedDesc') }}
            </p>
          </div>
        </div>

        <!-- Picker + preview -->
        <template v-else>
          <div class="form-row">
            <label class="form-label" for="reschedule-court">{{ t('v2Reservations.reschedule.courtLabel') }}</label>
            <select id="reschedule-court" v-model.number="courtId" class="form-input" data-testid="reschedule-court">
              <option :value="null" disabled>{{ t('v2Reservations.reschedule.courtPlaceholder') }}</option>
              <option v-for="c in courts" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div class="form-grid">
            <div class="form-row">
              <label class="form-label" for="reschedule-date">{{ t('v2Reservations.reschedule.dateLabel') }}</label>
              <input id="reschedule-date" v-model="date" type="date" class="form-input" data-testid="reschedule-date" />
            </div>
            <div class="form-row">
              <label class="form-label" for="reschedule-time">{{ t('v2Reservations.reschedule.timeLabel') }}</label>
              <input id="reschedule-time" v-model="time" type="time" class="form-input" data-testid="reschedule-time" />
            </div>
          </div>

          <button
            class="btn btn-secondary"
            type="button"
            :disabled="previewing"
            data-testid="reschedule-preview-btn"
            @click="runPreview"
          >
            {{ previewing ? t('v2Reservations.reschedule.previewing') : t('v2Reservations.reschedule.previewBtn') }}
          </button>

          <!-- Captured policy calculation -->
          <div v-if="preview" class="preview-box" data-testid="reschedule-preview">
            <div class="preview-row">
              <span>{{ t('v2Reservations.reschedule.deadlineLabel') }}</span>
              <span class="font-mono" data-testid="reschedule-deadline">{{ preview.deadline_at }}</span>
            </div>
            <div class="preview-row">
              <span>{{ t('v2Reservations.reschedule.originalPaidLabel') }}</span>
              <span class="font-mono">{{ formatCurrency(preview.original_paid_amount) }}</span>
            </div>
            <div class="preview-row">
              <span>{{ t('v2Reservations.reschedule.newTotalLabel') }}</span>
              <span class="font-mono">{{ formatCurrency(preview.new_total_amount) }}</span>
            </div>
            <div class="preview-row">
              <span>{{ t('v2Reservations.reschedule.changeFeeLabel') }}</span>
              <span class="font-mono" data-testid="reschedule-change-fee">{{ formatCurrency(preview.change_fee_amount) }}</span>
            </div>

            <!-- Three policy cases -->
            <div v-if="previewCase === 'extra'" class="case-box case-warn" data-testid="reschedule-case-extra">
              <strong>{{ t('v2Reservations.reschedule.caseExtraTitle') }}</strong>
              <p>{{ t('v2Reservations.reschedule.caseExtraDesc') }}</p>
              <div class="preview-row emphasis">
                <span>{{ t('v2Reservations.reschedule.extraPaymentLabel') }}</span>
                <span class="font-mono" data-testid="reschedule-extra-amount">{{ formatCurrency(preview.extra_payment_amount) }}</span>
              </div>
            </div>

            <div v-else-if="previewCase === 'refund'" class="case-box case-ok" data-testid="reschedule-case-refund">
              <strong>{{ t('v2Reservations.reschedule.caseRefundTitle') }}</strong>
              <p>{{ t('v2Reservations.reschedule.caseRefundDesc') }}</p>
              <div class="preview-row emphasis">
                <span>{{ t('v2Reservations.reschedule.refundDifferenceLabel') }}</span>
                <span class="font-mono" data-testid="reschedule-refund-amount">{{ formatCurrency(preview.refund_amount) }}</span>
              </div>
            </div>

            <div v-else class="case-box" data-testid="reschedule-case-equal">
              <strong>{{ t('v2Reservations.reschedule.caseEqualTitle') }}</strong>
              <p>{{ t('v2Reservations.reschedule.caseEqualDesc') }}</p>
            </div>

            <!-- Refund-difference recipient -->
            <div v-if="needsRecipient" class="recipient-box">
              <p class="recipient-title">{{ t('v2Reservations.reschedule.recipientHeading') }}</p>
              <input
                v-model="recipient.bank_code"
                class="form-input"
                :placeholder="t('v2Reservations.reschedule.bankCode')"
                data-testid="reschedule-recipient-bank"
              />
              <input
                v-model="recipient.account_name"
                class="form-input"
                :placeholder="t('v2Reservations.reschedule.accountName')"
                data-testid="reschedule-recipient-name"
              />
              <input
                v-model="recipient.account_number"
                class="form-input"
                :placeholder="t('v2Reservations.reschedule.accountNumber')"
                data-testid="reschedule-recipient-number"
              />
            </div>
          </div>
        </template>

        <div v-if="error" class="error-box" data-testid="reschedule-error">
          <span class="material-symbols-outlined">error</span>
          <span>{{ error }}</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" type="button" @click="emit('close')">
          {{ result ? t('v2Reservations.reschedule.close') : t('v2Reservations.reschedule.cancel') }}
        </button>
        <button
          v-if="!result"
          class="btn btn-primary"
          type="button"
          :disabled="!preview || submitting"
          data-testid="reschedule-submit-btn"
          @click="submit"
        >
          {{ submitting ? t('v2Reservations.reschedule.submitting') : t('v2Reservations.reschedule.submitBtn') }}
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
.form-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.form-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--muted);
}
.form-input {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
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
.case-box {
  border-radius: 10px;
  padding: 12px;
  background: rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.case-warn {
  background: rgba(234, 179, 8, 0.12);
}
.case-ok {
  background: rgba(34, 197, 94, 0.12);
}
.recipient-title {
  font-size: 0.82rem;
  font-weight: 600;
  margin: 0;
}
.notice-warn {
  background: rgba(234, 179, 8, 0.12);
  border-radius: 10px;
  padding: 12px;
}
.notice-ok {
  background: rgba(34, 197, 94, 0.12);
  border-radius: 10px;
  padding: 12px;
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
