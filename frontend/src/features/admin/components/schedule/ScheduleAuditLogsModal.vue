<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatDateTime } from '@/i18n'
import { AuraButton } from '@/shared/components'

defineProps<{
  show: boolean
  logs: any[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { t, te } = useI18n()

function formatAuditAction(action: string): string {
  if (!action) return ''
  const key = `admin.schedule.auditActions.${action}`
  if (te(key)) {
    return t(key)
  }
  if (!action.includes('.')) {
    return action
  }
  // Fallback formatted
  return action
    .split('.')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' · ')
}
</script>

<template>
  <div v-if="show" class="modal-backdrop modal-overlay-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">history</span>
          <h3 class="modal-title modal-title-text">{{ t('admin.schedule.auditLogsModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div v-if="logs.length === 0" class="empty-logs">
          <span class="material-symbols-outlined empty-icon">manage_history</span>
          <p>{{ t('admin.schedule.noAuditLogs') }}</p>
        </div>

        <div v-else class="logs-list">
          <div v-for="log in logs" :key="log.id" class="log-row">
            <div class="log-left">
              <span class="material-symbols-outlined log-icon">receipt_long</span>
              <div class="log-meta">
                <strong class="log-action">{{ formatAuditAction(log.action) }}</strong>
                <span class="log-performer">
                  {{ t('admin.schedule.performerLabel', { user: log.user?.name || log.actor || t('admin.schedule.systemUser') }) }}
                </span>
              </div>
            </div>

            <span class="log-time font-mono">
              {{ log.created_at ? formatDateTime(log.created_at) : '' }}
            </span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">
          {{ t('common.close') }}
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
  max-width: 640px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
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
  overflow-y: auto;
  flex: 1;
}

.logs-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.log-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 12px;
}

.log-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.log-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.log-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.log-action {
  font-size: 13px;
}

.log-performer {
  font-size: 11px;
  color: var(--muted);
}

.log-time {
  font-size: 11px;
  color: var(--muted);
  white-space: nowrap;
}

.empty-logs {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: var(--muted);
  text-align: center;
  gap: 8px;
}

.empty-icon {
  font-size: 36px;
  color: var(--muted);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 14px 20px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}
</style>
