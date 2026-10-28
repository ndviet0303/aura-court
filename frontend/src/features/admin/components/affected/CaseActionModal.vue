<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { venueClock, venueClockRange, venueDate, venueInstant } from '@/core/utils/venueTime'
import { AuraButton, DateTimePicker, SelectDropdown } from '@/shared/components'
import type { Court } from '@/features/court/types/court'
import {
  CONTACT_CHANNELS,
  type AffectedCase,
  type CaseAction,
  type CaseOutcomeForm,
  type ContactChannel,
  type OperatorCommandForm,
} from '../../types/affected'

/** The outcome form or one design D8 command (move court, change time, cancel) for an open case. */
const props = defineProps<{
  action: { kind: CaseAction; row: AffectedCase } | null
  /** Courts of the same sport and organization; move-court only. */
  targets: Court[]
  /** Only the owner attaches an off-platform refund note to a cancellation (BR-38). */
  owner: boolean
  busy?: boolean
  error?: string
  fieldErrors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  close: []
  submit: [form: CaseOutcomeForm | OperatorCommandForm]
}>()

const { t } = useI18n()
const OUTCOMES: CaseOutcomeForm['status'][] = ['contacting', 'unreachable', 'kept']

const outcome = ref<CaseOutcomeForm['status']>('contacting')
const channel = ref<ContactChannel>('phone')
const note = ref('')
const agreed = ref(false)
const courtId = ref<number | null>(null)
const startsAt = ref('')
const refundNote = ref('')

watch(
  () => props.action,
  (action) => {
    if (!action) return
    const { reservation } = action.row
    outcome.value = 'contacting'
    channel.value = action.row.channel ?? 'phone'
    note.value = ''
    agreed.value = false
    courtId.value = null
    startsAt.value = `${venueDate(reservation.starts_at)}T${venueClock(reservation.starts_at)}`
    refundNote.value = ''
  },
  { immediate: true },
)

const kind = computed(() => props.action?.kind)
const channelOptions = computed(() =>
  CONTACT_CHANNELS.map((c) => ({ value: c, label: t(`admin.affected.channel.${c}`) })),
)
const outcomeOptions = computed(() =>
  OUTCOMES.map((o) => ({ value: o, label: t(`admin.affected.status.${o}`) })),
)
const courtOptions = computed(() => props.targets.map((c) => ({ value: c.id, label: c.code })))

// change-time keeps the booking's total duration (design D8), so only the new start is picked.
const newStart = computed(() => (startsAt.value ? venueInstant(startsAt.value) : ''))
const newEnd = computed(() => {
  const reservation = props.action?.row.reservation
  if (!reservation || !newStart.value) return ''
  const duration = Date.parse(reservation.ends_at) - Date.parse(reservation.starts_at)
  return new Date(Date.parse(newStart.value) + duration).toISOString()
})

// UC-2.18 step 2: a cancellation records its reason, like an outcome its note.
const noteRequired = computed(() => kind.value === 'outcome' || kind.value === 'operator-cancel')
const canSubmit = computed(() => {
  if (noteRequired.value && !note.value.trim()) return false
  if (kind.value === 'outcome') return true
  if (kind.value === 'move-court' && courtId.value === null) return false
  if (kind.value === 'change-time' && !newStart.value) return false
  return agreed.value
})
const fieldError = (field: string) => props.fieldErrors?.[field]?.[0] ?? ''

function submit() {
  if (!canSubmit.value || props.busy) return
  const trimmed = note.value.trim()
  if (kind.value === 'outcome') {
    emit('submit', { status: outcome.value, channel: channel.value, note: trimmed })
    return
  }
  emit('submit', {
    customer_agreed: true,
    channel: channel.value,
    ...(trimmed ? { note: trimmed } : {}),
    ...(kind.value === 'move-court' && courtId.value !== null ? { court_id: courtId.value } : {}),
    ...(kind.value === 'change-time' ? { starts_at: newStart.value, ends_at: newEnd.value } : {}),
    ...(kind.value === 'operator-cancel' && props.owner && refundNote.value.trim()
      ? { refund_note: refundNote.value.trim() }
      : {}),
  })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="action" class="modal-backdrop" @click.self="emit('close')">
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="case-action-title">
        <div class="modal-header">
          <h3 id="case-action-title" class="modal-title">
            {{ t(`admin.affected.action.${kind}`) }} · {{ action.row.reservation.code }}
          </h3>
          <button
            type="button"
            class="modal-close-btn"
            :aria-label="t('common.close')"
            @click="emit('close')"
          >
            ✕
          </button>
        </div>

        <div class="modal-body case-form">
          <p class="muted">
            {{ action.row.reservation.court_code }} ·
            {{ venueDate(action.row.reservation.starts_at) }}
            {{ venueClockRange(action.row.reservation.starts_at, action.row.reservation.ends_at) }}
          </p>
          <p class="case-hint">{{ t(`admin.affected.hint.${kind}`) }}</p>
          <p v-if="error" class="form-error" role="alert">{{ error }}</p>

          <div v-if="kind === 'outcome'" class="form-group">
            <span class="form-label">{{ t('admin.affected.outcomeLabel') }}</span>
            <SelectDropdown
              :model-value="outcome"
              :options="outcomeOptions"
              variant="bordered"
              :aria-label="t('admin.affected.outcomeLabel')"
              @update:model-value="outcome = $event as CaseOutcomeForm['status']"
            />
          </div>

          <div v-if="kind === 'move-court'" class="form-group">
            <span class="form-label">{{ t('admin.affected.targetCourt') }} *</span>
            <SelectDropdown
              :model-value="courtId"
              :options="courtOptions"
              :placeholder="t('admin.affected.chooseCourt')"
              variant="bordered"
              :aria-label="t('admin.affected.targetCourt')"
              @update:model-value="courtId = Number($event)"
            />
            <span v-if="!targets.length" class="form-hint">{{
              t('admin.affected.noTargets')
            }}</span>
            <span v-if="fieldError('court_id')" class="form-error">{{
              fieldError('court_id')
            }}</span>
          </div>

          <div v-if="kind === 'change-time'" class="form-group">
            <span class="form-label">{{ t('admin.affected.newStart') }} *</span>
            <DateTimePicker v-model="startsAt" />
            <span v-if="newEnd" class="form-hint">
              {{
                t('admin.affected.newEnd', { end: `${venueDate(newEnd)} ${venueClock(newEnd)}` })
              }}
            </span>
            <span v-if="fieldError('starts_at')" class="form-error">
              {{ fieldError('starts_at') }}
            </span>
          </div>

          <div class="form-group">
            <span class="form-label">{{ t('admin.affected.channelLabel') }} *</span>
            <SelectDropdown
              :model-value="channel"
              :options="channelOptions"
              variant="bordered"
              :aria-label="t('admin.affected.channelLabel')"
              @update:model-value="channel = $event as ContactChannel"
            />
            <span v-if="fieldError('channel')" class="form-error">{{ fieldError('channel') }}</span>
          </div>

          <label class="form-group">
            <span class="form-label">
              {{
                t(
                  kind === 'operator-cancel'
                    ? 'admin.affected.cancelReasonLabel'
                    : 'admin.affected.noteLabel',
                )
              }}{{ noteRequired ? ' *' : '' }}
            </span>
            <textarea v-model="note" rows="2" maxlength="1000" class="form-textarea" />
            <span v-if="fieldError('note')" class="form-error">{{ fieldError('note') }}</span>
          </label>

          <label v-if="kind === 'operator-cancel' && owner" class="form-group">
            <span class="form-label">{{ t('admin.affected.refundNoteLabel') }}</span>
            <textarea v-model="refundNote" rows="2" maxlength="1000" class="form-textarea" />
            <span class="form-hint">{{ t('admin.affected.refundNoteHint') }}</span>
          </label>

          <template v-if="kind !== 'outcome'">
            <label class="case-agree">
              <input v-model="agreed" type="checkbox" />
              <span>{{ t('admin.affected.customerAgreed') }}</span>
            </label>
            <span v-if="fieldError('customer_agreed')" class="form-error">
              {{ fieldError('customer_agreed') }}
            </span>
          </template>
        </div>

        <div class="modal-footer">
          <AuraButton variant="outline" :disabled="busy" @click="emit('close')">
            {{ t('common.cancel') }}
          </AuraButton>
          <AuraButton
            :variant="kind === 'operator-cancel' ? 'danger' : 'primary'"
            :loading="busy"
            :disabled="!canSubmit"
            @click="submit"
          >
            {{ t(`admin.affected.action.${kind}`) }}
          </AuraButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.case-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
}

.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}

.case-hint {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.1);
  color: #92400e;
  font-size: 12px;
}

.case-agree {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.form-group :deep(.date-time-picker) {
  display: flex;
  gap: 6px;
}
</style>
