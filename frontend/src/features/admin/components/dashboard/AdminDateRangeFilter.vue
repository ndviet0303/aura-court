<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  datePreset: 'today' | '7days' | 'thisMonth' | 'custom'
  from: string
  to: string
  csvUrl: string
}>()

const emit = defineEmits<{
  (e: 'preset', preset: 'today' | '7days' | 'thisMonth' | 'custom'): void
  (e: 'update:from', val: string): void
  (e: 'update:to', val: string): void
  (e: 'filter'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="date-filter-bar">
    <div class="presets-row">
      <button
        type="button"
        class="preset-chip"
        :class="{ active: datePreset === 'today' }"
        @click="emit('preset', 'today')"
      >
        {{ t('admin.dashboard.today') }}
      </button>
      <button
        type="button"
        class="preset-chip"
        :class="{ active: datePreset === '7days' }"
        @click="emit('preset', '7days')"
      >
        {{ t('admin.dashboard.last7Days') }}
      </button>
      <button
        type="button"
        class="preset-chip"
        :class="{ active: datePreset === 'thisMonth' }"
        @click="emit('preset', 'thisMonth')"
      >
        {{ t('admin.dashboard.thisMonth') }}
      </button>
      <button
        type="button"
        class="preset-chip"
        :class="{ active: datePreset === 'custom' }"
        @click="emit('preset', 'custom')"
      >
        {{ t('admin.dashboard.custom') }}
      </button>
    </div>

    <div v-if="datePreset === 'custom'" class="custom-dates-row">
      <input
        :value="from"
        type="date"
        class="date-input"
        @input="emit('update:from', ($event.target as HTMLInputElement).value)"
      />
      <span class="material-symbols-outlined icon-arrow">arrow_forward</span>
      <input
        :value="to"
        type="date"
        class="date-input"
        @input="emit('update:to', ($event.target as HTMLInputElement).value)"
      />
      <button type="button" class="btn-apply-filter" @click="emit('filter')">
        {{ t('admin.dashboard.filter') }}
      </button>
    </div>

    <div class="actions-right">
      <a :href="csvUrl" target="_blank" class="btn-export-csv">
        <span class="material-symbols-outlined icon-18">download</span>
        <span>{{ t('admin.dashboard.exportCsv') }}</span>
      </a>
    </div>
  </div>
</template>

<style scoped>
.date-filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 14px 18px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.presets-row {
  display: flex;
  gap: 8px;
}

.preset-chip {
  padding: 6px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.preset-chip.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.custom-dates-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.date-input {
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
}

.btn-apply-filter {
  padding: 6px 14px;
  border-radius: 8px;
  border: 0;
  background: var(--ink);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-export-csv {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: border-color 0.15s;
}

.btn-export-csv:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.icon-arrow {
  font-size: 16px;
  color: var(--muted);
}

.icon-18 {
  font-size: 18px;
}
</style>
