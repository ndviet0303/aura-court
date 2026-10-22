<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { venueInstant } from '@/core/utils/venueTime'
import { AuraButton, DateTimePicker, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { Court } from '@/features/court/types/court'
import type { StopCommand, StopPayload } from '../../types/lifecycle'

const props = defineProps<{
  /** null hides the dialog. */
  command: StopCommand | null
  target: 'venue' | 'court'
  /** Staff may only record a maintenance pause. */
  owner: boolean
  /** When given, the court is picked here (schedule view). */
  courts?: Court[]
  loading?: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: [payload: StopPayload, courtId: number | null]
}>()

const { t } = useI18n()
const KINDS = ['maintenance', 'business'] as const

const reason = ref('')
const startMode = ref<'now' | 'scheduled'>('now')
const startsAt = ref('')
const expectedEnd = ref('')
const kind = ref<'maintenance' | 'business'>('maintenance')
const courtId = ref<number | null>(null)

watch(
  () => props.command,
  (command) => {
    if (!command) return
    reason.value = ''
    startMode.value = 'now'
    startsAt.value = ''
    expectedEnd.value = ''
    kind.value = 'maintenance'
    courtId.value = props.courts?.[0]?.id ?? null
  },
  { immediate: true },
)

const isPause = computed(() => props.command === 'pause')
const showKind = computed(() => isPause.value && props.target === 'court' && props.owner)
const reasonLength = computed(() => reason.value.trim().length)
const title = computed(() =>
  t(`admin.lifecycle.dialogTitle.${props.command}${props.target === 'venue' ? 'Venue' : 'Court'}`),
)
const courtOptions = computed<DropdownOption[]>(() =>
  (props.courts ?? []).map((c) => ({ value: c.id, label: c.code })),
)
const canSubmit = computed(
  () =>
    reasonLength.value >= 10 &&
    reasonLength.value <= 1000 &&
    (startMode.value === 'now' || !!startsAt.value) &&
    (!props.courts || courtId.value !== null),
)

function submit() {
  if (!canSubmit.value || !props.command) return
  const payload: StopPayload = { reason: reason.value.trim() }
  if (startMode.value === 'scheduled') payload.starts_at = venueInstant(startsAt.value)
  if (isPause.value) {
    if (expectedEnd.value) payload.expected_end_at = venueInstant(expectedEnd.value)
    if (props.target === 'court') payload.kind = props.owner ? kind.value : 'maintenance'
  }
  emit('submit', payload, courtId.value)
}
</script>

<template>
  <Teleport to="body">
    <div v-if="command" class="modal-backdrop" @click.self="emit('close')">
      <div class="modal-card" role="dialog" aria-modal="true" :aria-label="title">
        <div class="modal-header">
          <h3 class="modal-title">{{ title }}</h3>
          <button
            type="button"
            class="modal-close-btn"
            :aria-label="t('common.cancel')"
            @click="emit('close')"
          >
            ✕
          </button>
        </div>

        <div class="modal-body">
          <p v-if="command === 'close'" class="close-warning">
            {{ t('admin.lifecycle.closeWarning') }}
          </p>

          <div v-if="courts" class="form-group">
            <label class="form-label">{{ t('admin.lifecycle.courtLabel') }}</label>
            <SelectDropdown
              :model-value="courtId"
              :options="courtOptions"
              variant="bordered"
              :aria-label="t('admin.lifecycle.courtLabel')"
              @update:model-value="(val) => (courtId = Number(val))"
            />
          </div>

          <div v-if="showKind" class="form-group">
            <span class="form-label">{{ t('admin.lifecycle.kindLabel') }}</span>
            <div class="radio-row">
              <label v-for="option in KINDS" :key="option">
                <input v-model="kind" type="radio" :value="option" />
                {{ t(`admin.lifecycle.kind.${option}`) }}
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="stop-reason">
              {{ t('admin.lifecycle.reasonLabel') }}
            </label>
            <textarea
              id="stop-reason"
              v-model="reason"
              class="form-input"
              rows="3"
              maxlength="1000"
              :placeholder="t('admin.lifecycle.reasonPlaceholder')"
            />
            <span class="form-hint">
              {{ t('admin.lifecycle.reasonHint', { count: reasonLength }) }}
            </span>
          </div>

          <div class="form-group">
            <span class="form-label">{{ t('admin.lifecycle.startLabel') }}</span>
            <div class="radio-row">
              <label>
                <input v-model="startMode" type="radio" value="now" />
                {{ t('admin.lifecycle.startNow') }}
              </label>
              <label>
                <input v-model="startMode" type="radio" value="scheduled" />
                {{ t('admin.lifecycle.startScheduled') }}
              </label>
            </div>
            <DateTimePicker v-if="startMode === 'scheduled'" v-model="startsAt" />
          </div>

          <div v-if="isPause" class="form-group">
            <span class="form-label">{{ t('admin.lifecycle.expectedEndLabel') }}</span>
            <DateTimePicker v-model="expectedEnd" />
          </div>
        </div>

        <div class="modal-footer">
          <AuraButton variant="outline" @click="emit('close')">{{ t('common.cancel') }}</AuraButton>
          <AuraButton variant="primary" :disabled="loading || !canSubmit" @click="submit">
            {{ loading ? t('common.loading') : t('admin.lifecycle.submit') }}
          </AuraButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  width: 100%;
  max-width: 560px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.modal-close-btn {
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 18px;
  cursor: pointer;
}

.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.close-warning {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.08);
  color: #dc2626;
  font-size: 13px;
  font-weight: 600;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.form-hint {
  font-size: 11px;
  color: var(--muted);
}

.form-input {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 13px;
  font-family: inherit;
  resize: vertical;
}

.radio-row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 13px;
  color: var(--ink);
}

.radio-row label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

.form-group :deep(.date-time-picker) {
  display: flex;
  gap: 6px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid var(--outline);
}
</style>
