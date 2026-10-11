<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, DatePicker, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { SpecialDateItem } from '../../composables/useAdminSchedule'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (
    e: 'submit',
    payload: { date: string; name: string; type: SpecialDateItem['type']; hours?: string },
  ): void
}>()

const { t } = useI18n()

// Default date to next month's 1st
const defaultDate = new Date()
defaultDate.setMonth(defaultDate.getMonth() + 1)
defaultDate.setDate(1)

const date = ref(defaultDate.toISOString().slice(0, 10))
const name = ref('')
const type = ref<SpecialDateItem['type']>('special_hours')
const hours = ref('08:00 - 22:00')

const typeOptions = computed<DropdownOption[]>(() => [
  { value: 'special_hours', label: t('admin.schedule.typeSpecialHoursOption'), icon: 'schedule' },
  { value: 'holiday', label: t('admin.schedule.typeHolidayOption'), icon: 'celebration' },
  { value: 'closed', label: t('admin.schedule.typeClosedOption'), icon: 'block' },
])

function handleSubmit() {
  if (!date.value || !name.value.trim()) return
  emit('submit', {
    date: date.value,
    name: name.value.trim(),
    type: type.value,
    hours: type.value === 'closed' ? undefined : hours.value.trim(),
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">event</span>
          <h3 class="modal-title modal-title-text">
            {{ t('admin.schedule.addSpecialDateModalTitle') }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.dateLabel') }}</label>
          <DatePicker v-model="date" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.occasionNameLabel') }}</label>
          <input
            v-model="name"
            type="text"
            class="form-input"
            :placeholder="t('admin.schedule.occasionPlaceholder')"
          />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.configTypeLabel') }}</label>
          <SelectDropdown
            :model-value="type"
            :options="typeOptions"
            variant="bordered"
            aria-label="Configuration Type"
            @update:model-value="(val) => (type = val as SpecialDateItem['type'])"
          />
        </div>

        <div v-if="type !== 'closed'" class="form-group">
          <label class="form-label">{{ t('admin.schedule.hoursLabel') }}</label>
          <input v-model="hours" type="text" class="form-input" placeholder="08:00 - 22:00" />
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton variant="primary" :disabled="!name.trim() || !date" @click="handleSubmit">
          <span class="material-symbols-outlined icon-18">add</span>
          <span>{{ t('admin.schedule.submitSpecialDateBtn') }}</span>
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
  max-width: 500px;
  overflow: hidden;
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
  gap: 14px;
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
</style>
