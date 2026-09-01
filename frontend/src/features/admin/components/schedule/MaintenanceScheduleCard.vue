<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatSlotRange } from '@/i18n'
import type { MaintenanceBlock } from '../../composables/useAdminSchedule'

defineProps<{
  blocks: MaintenanceBlock[]
}>()

const emit = defineEmits<{
  (e: 'scheduleDowntime'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="maintenance-card">
    <div class="card-header">
      <div>
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">build</span>
          <h3 class="title">{{ t('admin.schedule.maintenanceTitle') }}</h3>
        </div>
        <p class="sub">{{ t('admin.schedule.maintenanceSub') }}</p>
      </div>

      <button type="button" class="btn-schedule-downtime-cta" @click="emit('scheduleDowntime')">
        <span class="material-symbols-outlined icon-16">add_moderator</span>
        <span>{{ t('admin.schedule.scheduleDowntimeBtn') }}</span>
      </button>
    </div>

    <div v-if="blocks.length === 0" class="empty-blocks">
      {{ t('admin.schedule.noMaintenance') }}
    </div>

    <div v-else class="blocks-list">
      <div v-for="b in blocks" :key="b.id" class="block-row">
        <div class="block-info">
          <strong>{{ b.reason }}</strong>
          <span class="block-court">
            <span class="material-symbols-outlined icon-14">location_on</span>
            <span>{{ resolveLocalizedText(b.court_name) }}</span>
          </span>
          <span class="block-time font-mono">
            <span class="material-symbols-outlined icon-14">schedule</span>
            <span>{{ formatSlotRange(b.starts_at, b.ends_at) }}</span>
          </span>
        </div>
        <span class="block-status-badge">
          {{ t('admin.schedule.statusLocked') }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.maintenance-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  flex-wrap: wrap;
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

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.btn-schedule-downtime-cta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: #f59e0b;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.empty-blocks {
  padding: 24px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
}

.blocks-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.block-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.block-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.block-court, .block-time {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted);
}

.block-status-badge {
  font-size: 11px;
  font-weight: 700;
  color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}
</style>
