<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { Court } from '@/features/court/types/court'

const props = defineProps<{
  show: boolean
  courts: Court[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { court_id: number; starts_at: string; ends_at: string; reason: string }): void
}>()

const { t } = useI18n()

const courtId = ref<number>(props.courts[0]?.id || 1)
const startsAt = ref('2026-10-12T08:00')
const endsAt = ref('2026-10-14T17:00')
const reason = ref('')

function handleSubmit() {
  if (!startsAt.value || !endsAt.value || !reason.value.trim()) return
  emit('submit', {
    court_id: courtId.value,
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
        <button type="button" class="modal-close-btn btn-modal-close" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.selectCourtLabel') }}</label>
          <select v-model="courtId" class="form-input">
            <option v-for="c in courts" :key="c.id" :value="c.id">
              {{ resolveLocalizedText(c.name) }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.lockStartLabel') }}</label>
          <input v-model="startsAt" type="datetime-local" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.lockEndLabel') }}</label>
          <input v-model="endsAt" type="datetime-local" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('admin.schedule.reasonLabel') }}</label>
          <input v-model="reason" type="text" class="form-input" :placeholder="t('admin.schedule.reasonPlaceholder')" />
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="button" class="btn-primary" @click="handleSubmit">
          {{ t('admin.schedule.confirmLockBtn') }}
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
