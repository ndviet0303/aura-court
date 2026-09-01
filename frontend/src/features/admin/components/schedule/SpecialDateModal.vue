<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SpecialDateItem } from '../../composables/useAdminSchedule'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { date: string; name: string; type: SpecialDateItem['type']; hours?: string }): void
}>()

const { t } = useI18n()

const date = ref('2026-11-20')
const name = ref('Ngày Nhà Giáo VN 20/11')
const type = ref<SpecialDateItem['type']>('special_hours')
const hours = ref('08:00 - 22:00')

function handleSubmit() {
  if (!date.value || !name.value.trim()) return
  emit('submit', {
    date: date.value,
    name: name.value.trim(),
    type: type.value,
    hours: hours.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">event</span>
          <h3 class="modal-title modal-title-text">{{ t('admin.schedule.addSpecialDateModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.dateLabel') }}</label>
          <input v-model="date" type="date" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.occasionNameLabel') }}</label>
          <input v-model="name" type="text" class="form-input" :placeholder="t('admin.schedule.occasionPlaceholder')" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.configTypeLabel') }}</label>
          <select v-model="type" class="form-input">
            <option value="holiday">{{ t('admin.schedule.typeHolidayOption') }}</option>
            <option value="special_hours">{{ t('admin.schedule.typeSpecialHoursOption') }}</option>
            <option value="closed">{{ t('admin.schedule.typeClosedOption') }}</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.hoursLabel') }}</label>
          <input v-model="hours" type="text" class="form-input" placeholder="08:00 - 22:00" />
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="button" class="btn-primary" @click="handleSubmit">
          {{ t('admin.schedule.submitSpecialDateBtn') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
