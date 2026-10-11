<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, DateTimePicker, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { Court } from '@/features/court/types/court'

const props = defineProps<{
  show: boolean
  courts: Court[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (
    e: 'submit',
    payload: { court_id: number; starts_at: string; ends_at: string; reason: string },
  ): void
}>()

const { t } = useI18n()

// Calculate default start and end times (tomorrow 08:00 to 18:00)
function getTomorrowDateTime(hours: number, minutes: number): string {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  d.setHours(hours, minutes, 0, 0)
  return d.toISOString().slice(0, 16)
}

const courtId = ref<number>(1)
const startsAt = ref(getTomorrowDateTime(8, 0))
const endsAt = ref(getTomorrowDateTime(18, 0))
const reason = ref('')

const courtOptions = computed<DropdownOption[]>(() =>
  props.courts.map((c) => ({
    value: c.id,
    label: resolveLocalizedText(c.name),
    icon: 'sports_tennis',
  })),
)

watch(
  () => props.courts,
  (newCourts) => {
    if (newCourts && newCourts.length > 0 && newCourts[0] && !courtId.value) {
      courtId.value = newCourts[0].id
    }
  },
  { immediate: true },
)

function handleSubmit() {
  if (!startsAt.value || !endsAt.value || !reason.value.trim() || !courtId.value) return
  emit('submit', {
    court_id: Number(courtId.value),
    starts_at: startsAt.value,
    ends_at: endsAt.value,
    reason: reason.value.trim(),
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">build</span>
          <h3 class="modal-title modal-title-text">{{ t('admin.schedule.downtimeModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn btn-modal-close" @click="emit('close')">
          ✕
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.selectCourtLabel') }}</label>
          <SelectDropdown
            :model-value="courtId"
            :options="courtOptions"
            variant="bordered"
            aria-label="Court Selection"
            @update:model-value="(val) => (courtId = Number(val))"
          />
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">{{ t('admin.schedule.lockStartLabel') }}</label>
            <DateTimePicker v-model="startsAt" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.schedule.lockEndLabel') }}</label>
            <DateTimePicker v-model="endsAt" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.reasonLabel') }}</label>
          <input
            v-model="reason"
            type="text"
            class="form-input"
            :placeholder="t('admin.schedule.reasonPlaceholder')"
          />
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton variant="primary" :disabled="loading || !reason.trim()" @click="handleSubmit">
          <span class="material-symbols-outlined icon-18">lock_clock</span>
          <span>{{ loading ? t('common.loading') : t('admin.schedule.confirmLockBtn') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
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
  max-width: 600px;
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
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
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

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group :deep(.date-time-picker) {
  display: flex;
  align-items: center;
  gap: 6px;
}

.form-group :deep(.date-time-picker .date-picker) {
  flex: 1 1 auto;
  min-width: 130px;
}

.form-group :deep(.date-time-picker .time-picker) {
  flex: 0 0 100px;
  min-width: 100px;
}

.form-group :deep(.date-picker-trigger),
.form-group :deep(.time-picker-trigger) {
  padding: 8px 10px;
  font-size: 12.5px;
  border-radius: 10px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.form-input {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 13px;
  outline: none;
  font-family: inherit;
  transition: border-color 0.15s ease;
}

.form-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.icon-18 {
  font-size: 18px;
}

@media (max-width: 480px) {
  .form-grid-2 {
    grid-template-columns: 1fr;
  }
}
</style>
