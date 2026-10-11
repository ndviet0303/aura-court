<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { SkeletonLoader, AuraButton } from '@/shared/components'
import type { SpecialDateItem } from '../../composables/useAdminSchedule'

defineProps<{
  specialDates: SpecialDateItem[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'add'): void
  (e: 'delete', id: number): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="special-dates-card">
    <div class="card-header">
      <div class="header-titles">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">event</span>
          <h3 class="title">{{ t('admin.schedule.specialDatesTitle') }}</h3>
        </div>
        <p class="sub">{{ t('admin.schedule.specialDatesSub') }}</p>
      </div>

      <AuraButton
        variant="primary"
        class="btn-add-special-date btn-add-circle"
        @click="emit('add')"
      >
        <span class="material-symbols-outlined icon-16">add</span>
        <span>{{ t('admin.schedule.addSpecialDateBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="dates-list">
      <div v-for="i in 3" :key="i" class="date-row skeleton-date-row">
        <div class="date-info">
          <SkeletonLoader type="rect" width="160px" height="16px" rounded="4px" />
          <SkeletonLoader type="rect" width="120px" height="12px" rounded="4px" />
        </div>
        <SkeletonLoader type="rect" width="70px" height="24px" rounded="4px" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="specialDates.length === 0" class="empty-dates">
      <span class="material-symbols-outlined empty-icon">event_available</span>
      <p>{{ t('admin.schedule.noSpecialDates') }}</p>
    </div>

    <!-- Dates List -->
    <div v-else class="dates-list">
      <div v-for="d in specialDates" :key="d.id" class="date-row">
        <div class="date-info">
          <strong class="date-name">{{ resolveLocalizedText(d.name) }}</strong>
          <span class="date-val font-mono">
            <span class="material-symbols-outlined icon-14">calendar_today</span>
            <span>{{ d.date }} · {{ d.hours || t('admin.schedule.standardHours') }}</span>
          </span>
        </div>

        <div class="date-actions">
          <span class="type-tag" :class="`type-${d.type}`">
            {{
              d.type === 'holiday'
                ? t('admin.schedule.typeHoliday')
                : d.type === 'closed'
                  ? t('admin.schedule.typeClosed')
                  : t('admin.schedule.typeSpecialHours')
            }}
          </span>

          <button
            type="button"
            class="btn-delete-date"
            :title="t('admin.schedule.deleteSpecialDate')"
            @click="emit('delete', d.id)"
          >
            <span class="material-symbols-outlined icon-16">delete</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.special-dates-card {
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

.empty-dates {
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
  color: #3b82f6;
}

.dates-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.date-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.date-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.date-name {
  font-size: 13px;
}

.date-val {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted);
}

.date-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.type-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.type-holiday {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}
.type-special_hours {
  background: rgba(59, 130, 246, 0.12);
  color: #3b82f6;
}
.type-closed {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.btn-delete-date {
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.btn-delete-date:hover {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}
</style>
