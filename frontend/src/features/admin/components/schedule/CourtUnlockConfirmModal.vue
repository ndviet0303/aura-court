<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { CourtStop } from '../../composables/useAdminSchedule'

defineProps<{
  block: CourtStop | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div v-if="block" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon text-green">play_arrow</span>
          <h3 class="modal-title modal-title-text">{{ t('admin.schedule.unlockConfirmTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p class="confirm-text">
          {{ t('admin.schedule.unlockConfirmMsg', { reason: block.reason }) }}
        </p>

        <div class="court-summary-box">
          <div class="summary-row">
            <span class="summary-lbl">{{ t('admin.schedule.selectCourtLabel') }}</span>
            <strong>{{ resolveLocalizedText(block.court_name) }}</strong>
          </div>
          <div class="summary-row">
            <span class="summary-lbl">{{ t('admin.schedule.reasonLabel') }}</span>
            <span>{{ block.reason }}</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton variant="primary" :disabled="loading" @click="emit('confirm')">
          <span class="material-symbols-outlined icon-18">check</span>
          <span>{{ loading ? t('common.loading') : t('admin.schedule.unlockCourtBtn') }}</span>
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
  max-width: 460px;
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
}

.text-green {
  color: #10b981;
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

.confirm-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  color: var(--ink);
}

.court-summary-box {
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-lbl {
  color: var(--muted);
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
