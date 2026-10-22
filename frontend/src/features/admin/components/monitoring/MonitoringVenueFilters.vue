<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import { OPERATING_STATUSES } from '@/features/court/types/court'
import type { MonitoringFilters } from '../../types/suspension'

const props = defineProps<{
  filters: MonitoringFilters
  organizations: Array<{ id: number; name: string }>
}>()
const emit = defineEmits<{ change: [key: keyof MonitoringFilters, value: string | number] }>()

const { t } = useI18n()

const all = { value: '', label: t('common.all') }
const fields = computed<{ key: keyof MonitoringFilters; options: DropdownOption[] }[]>(() => [
  {
    key: 'organization_id',
    options: [all, ...props.organizations.map((org) => ({ value: org.id, label: org.name }))],
  },
  {
    key: 'status',
    options: [
      all,
      ...OPERATING_STATUSES.map((s) => ({ value: s, label: t(`admin.lifecycle.status.${s}`) })),
    ],
  },
  {
    key: 'suspended',
    options: [
      all,
      { value: '1', label: t('admin.monitoring.filters.suspendedOnly') },
      { value: '0', label: t('admin.monitoring.filters.notSuspended') },
    ],
  },
])

function setKeyword(event: Event) {
  emit('change', 'keyword', (event.target as HTMLInputElement).value.trim())
}
</script>

<template>
  <div class="monitoring-filters">
    <label class="monitoring-filter">
      <span class="form-label">{{ t('admin.monitoring.filters.keyword') }}</span>
      <input
        :value="filters.keyword"
        type="search"
        maxlength="100"
        class="form-input"
        :placeholder="t('admin.monitoring.filters.keywordHint')"
        @change="setKeyword"
      />
    </label>
    <label v-for="field in fields" :key="field.key" class="monitoring-filter">
      <span class="form-label">{{ t(`admin.monitoring.filters.${field.key}`) }}</span>
      <SelectDropdown
        :model-value="filters[field.key]"
        :options="field.options"
        variant="bordered"
        :aria-label="t(`admin.monitoring.filters.${field.key}`)"
        @update:model-value="emit('change', field.key, $event)"
      />
    </label>
  </div>
</template>

<style scoped>
.monitoring-filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.monitoring-filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
</style>
