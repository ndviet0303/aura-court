<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatDateTime } from '@/i18n'
import { SkeletonLoader, AuraButton } from '@/shared/components'
import type { MaintenanceBlock } from '../../composables/useAdminSchedule'

defineProps<{
  blocks: MaintenanceBlock[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'scheduleDowntime'): void
  (e: 'unlockCourt', block: MaintenanceBlock): void
}>()

const { t } = useI18n()

function formatTimeDisplay(startsAt: string, endsAt: string) {
  try {
    return `${formatDateTime(startsAt)} → ${formatDateTime(endsAt)}`
  } catch {
    return `${startsAt} - ${endsAt}`
  }
}
</script>

<template>
  <div class="maintenance-card">
    <div class="card-header">
      <div class="header-titles">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">build</span>
          <h3 class="title">{{ t('admin.schedule.maintenanceTitle') }}</h3>
        </div>
        <p class="sub">{{ t('admin.schedule.maintenanceSub') }}</p>
      </div>

      <AuraButton variant="primary" class="btn-schedule-downtime btn-schedule-downtime-cta" @click="emit('scheduleDowntime')">
        <span class="material-symbols-outlined icon-16">add_moderator</span>
        <span>{{ t('admin.schedule.scheduleDowntimeBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="blocks-list">
      <div v-for="i in 3" :key="i" class="block-row skeleton-block-row">
        <div class="block-info">
          <SkeletonLoader type="rect" width="140px" height="16px" rounded="4px" />
          <SkeletonLoader type="rect" width="100px" height="12px" rounded="4px" />
          <SkeletonLoader type="rect" width="220px" height="12px" rounded="4px" />
        </div>
        <SkeletonLoader type="rect" width="80px" height="26px" rounded="6px" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="blocks.length === 0" class="empty-blocks">
      <span class="material-symbols-outlined empty-icon">check_circle</span>
      <p>{{ t('admin.schedule.noMaintenance') }}</p>
    </div>

    <!-- Blocks List -->
    <div v-else class="blocks-list">
      <div v-for="b in blocks" :key="b.id" class="block-row">
        <div class="block-info">
          <div class="block-top-meta">
            <strong class="block-reason">{{ b.reason }}</strong>
            <span class="status-chip" :class="`status-${b.status}`">
              {{
                b.status === 'ongoing'
                  ? t('admin.schedule.statusOngoing')
                  : b.status === 'upcoming'
                    ? t('admin.schedule.statusUpcoming')
                    : t('admin.schedule.statusEnded')
              }}
            </span>
          </div>

          <span class="block-court">
            <span class="material-symbols-outlined icon-14">sports_tennis</span>
            <span>{{ resolveLocalizedText(b.court_name) }}</span>
          </span>

          <span class="block-time font-mono">
            <span class="material-symbols-outlined icon-14">schedule</span>
            <span>{{ formatTimeDisplay(b.starts_at, b.ends_at) }}</span>
          </span>
        </div>

        <div class="block-actions">
          <button
            type="button"
            class="btn-unlock-court"
            :title="t('admin.schedule.unlockCourtBtn')"
            @click="emit('unlockCourt', b)"
          >
            <span class="material-symbols-outlined icon-16">lock_open</span>
            <span>{{ t('admin.schedule.unlockCourtBtn') }}</span>
          </button>
        </div>
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

.header-titles {
  display: flex;
  flex-direction: column;
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

.empty-blocks {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 36px 20px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
  gap: 8px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px dashed var(--outline);
}

.empty-icon {
  font-size: 32px;
  color: #10b981;
}

.blocks-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.block-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 12px;
}

.block-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.block-top-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.block-reason {
  font-size: 13px;
  font-weight: 700;
}

.status-chip {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.status-ongoing {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.status-upcoming {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}

.status-ended {
  background: rgba(107, 114, 128, 0.12);
  color: #6b7280;
}

.block-court, .block-time {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--muted);
}

.block-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-unlock-court {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-unlock-court:hover {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  border-color: #10b981;
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}
</style>
