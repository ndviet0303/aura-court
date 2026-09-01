<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatDateTime } from '@/i18n'

defineProps<{
  show: boolean
  logs: any[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { t } = useI18n()
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
          {{ t('admin.schedule.noAuditLogs') }}
        </div>
        <div v-else class="logs-list">
          <div v-for="log in logs" :key="log.id" class="log-row">
            <strong>{{ log.action }}</strong>
            <span>{{ t('admin.schedule.performerLabel', { user: log.user?.name || t('admin.schedule.systemUser') }) }}</span>
            <span class="log-time font-mono">{{ log.created_at ? formatDateTime(log.created_at) : '' }}</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.close') }}</button>
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

.logs-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.log-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
}

.log-time {
  color: var(--muted);
}

.empty-logs {
  font-size: 12px;
  color: var(--muted);
  text-align: center;
  padding: 20px;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
