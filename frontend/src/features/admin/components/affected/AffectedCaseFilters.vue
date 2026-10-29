<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { Court, Venue } from '@/features/court/types/court'
import { CASE_SOURCES, CASE_STATUSES, type AffectedCaseFilters } from '../../types/affected'

const props = defineProps<{ filters: AffectedCaseFilters; venues: Venue[]; courts: Court[] }>()
const emit = defineEmits<{ change: [key: keyof AffectedCaseFilters, value: string | number] }>()

const { t } = useI18n()

const all = { value: '', label: t('common.all') }
const fields = computed<{ key: keyof AffectedCaseFilters; options: DropdownOption[] }[]>(() => [
  {
    key: 'venue_id',
    options: [
      all,
      ...props.venues.map((v) => ({ value: v.id, label: resolveLocalizedText(v.name) })),
    ],
  },
  { key: 'court_id', options: [all, ...props.courts.map((c) => ({ value: c.id, label: c.code }))] },
  {
    key: 'source_type',
    options: [
      all,
      ...CASE_SOURCES.map((s) => ({ value: s, label: t(`admin.affected.source.${s}`) })),
    ],
  },
  {
    key: 'status',
    // Without a status the server lists the open cases.
    options: [
      { value: '', label: t('admin.affected.filters.openOnly') },
      ...CASE_STATUSES.map((s) => ({ value: s, label: t(`admin.affected.status.${s}`) })),
    ],
  },
])
</script>

<template>
  <div class="case-filters">
    <label v-for="field in fields" :key="field.key" class="case-filter">
      <span class="form-label">{{ t(`admin.affected.filters.${field.key}`) }}</span>
      <SelectDropdown
        :model-value="filters[field.key]"
        :options="field.options"
        variant="bordered"
        :aria-label="t(`admin.affected.filters.${field.key}`)"
        @update:model-value="emit('change', field.key, $event)"
      />
    </label>
  </div>
</template>

<style scoped>
.case-filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
  gap: 12px;
}

.case-filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
</style>
