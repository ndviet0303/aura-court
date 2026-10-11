<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatSlotRange } from '@/i18n'
import type { Court } from '@/features/court/types/court'

defineProps<{
  show: boolean
  court: Court | null
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'addBlock', payload: { starts_at: string; ends_at: string; reason: string }): void
  (e: 'deleteBlock', blockId: number): void
}>()

const { t } = useI18n()

const startsAt = ref('')
const endsAt = ref('')
const reason = ref('')

function handleAdd() {
  if (!startsAt.value || !endsAt.value) return
  emit('addBlock', {
    starts_at: startsAt.value,
    ends_at: endsAt.value,
    reason: reason.value || t('admin.manage.defaultLockReason'),
  })
  startsAt.value = ''
  endsAt.value = ''
  reason.value = ''
}
</script>

<template>
  <div v-if="show && court" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">lock</span>
          <h3 class="modal-title">
            {{ t('admin.manage.lockCourtModalTitle', { name: resolveLocalizedText(court.name) }) }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- Add Block Form -->
        <div class="block-form-box">
          <h4 class="form-sub-title">{{ t('admin.manage.addLockSlotTitle') }}</h4>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.lockStartTime') }}</label>
            <input v-model="startsAt" type="datetime-local" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.lockEndTime') }}</label>
            <input v-model="endsAt" type="datetime-local" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.lockReason') }}</label>
            <input
              v-model="reason"
              type="text"
              class="form-input"
              :placeholder="t('admin.manage.lockReasonPlaceholder')"
            />
          </div>

          <button
            type="button"
            class="btn-add-block"
            :disabled="!startsAt || !endsAt || saving"
            @click="handleAdd"
          >
            <span class="material-symbols-outlined icon-16">add</span>
            <span>{{ t('admin.manage.createLockBtn') }}</span>
          </button>
        </div>

        <!-- Existing Blocks List -->
        <div class="existing-blocks-section">
          <h4 class="form-sub-title">
            {{ t('admin.manage.currentLocksList', { count: court.blocks?.length || 0 }) }}
          </h4>

          <div v-if="!court.blocks?.length" class="empty-blocks-msg">
            {{ t('admin.manage.noLocks') }}
          </div>

          <div v-else class="blocks-list">
            <div v-for="block in court.blocks" :key="block.id" class="block-item-row">
              <div class="block-info">
                <strong>{{ block.reason || t('admin.manage.defaultLockReason') }}</strong>
                <span class="font-mono">{{ formatSlotRange(block.starts_at, block.ends_at) }}</span>
              </div>
              <button type="button" class="btn-del-block" @click="emit('deleteBlock', block.id)">
                {{ t('admin.manage.deleteBtn') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('common.close') }}
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

.block-form-box {
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 20px;
}

.form-sub-title {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
}

.btn-add-block {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 10px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  margin-top: 6px;
}

.btn-add-block:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.existing-blocks-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.empty-blocks-msg {
  font-size: 12px;
  color: var(--muted);
  font-style: italic;
}

.blocks-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 200px;
  overflow-y: auto;
}

.block-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.block-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.block-info span {
  color: var(--muted);
  font-size: 11px;
}

.btn-del-block {
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: transparent;
  color: #ef4444;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.icon-16 {
  font-size: 16px;
}
</style>
