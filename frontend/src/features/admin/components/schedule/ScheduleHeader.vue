<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import { resolveLocalizedText } from '@/i18n'
import type { Venue } from '@/features/court/types/court'

const props = defineProps<{
  venues: Venue[]
  selectedVenueId: number | 'all'
  saving?: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedVenueId', value: number | 'all'): void
  (e: 'openAuditLogs'): void
  (e: 'saveSchedule'): void
  (e: 'refresh'): void
}>()

const { t } = useI18n()

const venueOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.schedule.filterAllVenues'), icon: 'location_on' },
  ...props.venues.map((v) => ({
    value: v.id,
    label: resolveLocalizedText(v.name),
    icon: 'location_on',
  })),
])
</script>

<template>
  <header class="schedule-header">
    <div class="header-titles">
      <div class="header-title-row">
        <span class="material-symbols-outlined header-icon">schedule</span>
        <h1 class="page-headline">{{ t('admin.schedule.title') }}</h1>
      </div>
      <p class="page-sub">{{ t('admin.schedule.subtitle') }}</p>
    </div>

    <div class="header-actions">
      <!-- Venue Selector Filter -->
      <div v-if="venues.length > 0" class="venue-filter-wrap">
        <SelectDropdown
          :model-value="selectedVenueId"
          :options="venueOptions"
          variant="bordered"
          aria-label="Venue Filter"
          @update:model-value="(val) => emit('update:selectedVenueId', val === 'all' ? 'all' : Number(val))"
        />
      </div>

      <AuraButton variant="outline" class="btn-refresh" :disabled="loading" @click="emit('refresh')">
        <span class="material-symbols-outlined icon-18">refresh</span>
        <span>{{ t('admin.schedule.refreshBtn') }}</span>
      </AuraButton>

      <AuraButton variant="outline" class="btn-action-neutral btn-audit-logs" @click="emit('openAuditLogs')">
        <span class="material-symbols-outlined icon-18">history</span>
        <span>{{ t('admin.schedule.auditLogsBtn') }}</span>
      </AuraButton>

      <AuraButton variant="primary" :disabled="saving" @click="emit('saveSchedule')">
        <span class="material-symbols-outlined icon-18">save</span>
        <span>{{ saving ? t('common.loading') : t('admin.schedule.saveScheduleBtn') }}</span>
      </AuraButton>
    </div>
  </header>
</template>

<style scoped>
.schedule-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.header-titles {
  display: flex;
  flex-direction: column;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.page-headline {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.3px;
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.venue-filter-wrap {
  min-width: 220px;
}

.icon-18 {
  font-size: 18px;
}

@media (max-width: 768px) {
  .schedule-header {
    flex-direction: column;
    align-items: stretch;
  }
  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }
  .venue-filter-wrap {
    width: 100%;
    justify-content: space-between;
  }
}
</style>
