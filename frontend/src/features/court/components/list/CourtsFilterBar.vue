<script setup lang="ts">
import { computed } from 'vue'
import { SelectDropdown } from '@/shared/components'

const props = defineProps<{
  search: string
  venue: string
  venueOptions: string[]
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:venue', val: string): void
}>()

const dropdownOptions = computed(() => {
  return [
    { value: 'all', label: 'Tất cả cụm sân Aura', icon: 'stadium' },
    ...props.venueOptions
      .filter((x) => x !== 'all')
      .map((v) => ({ value: v, label: v, icon: 'location_on' })),
  ]
})
</script>

<template>
  <div class="courts-filter-bar">
    <div class="search-box">
      <span class="material-symbols-outlined search-icon">search</span>
      <input
        :value="search"
        type="text"
        class="search-input"
        placeholder="Tìm sân theo tên, cơ sở hoặc khu vực..."
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <div class="selects-box">
      <SelectDropdown
        :model-value="venue"
        :options="dropdownOptions"
        variant="bordered"
        aria-label="Chọn cụm sân Aura"
        @update:model-value="emit('update:venue', String($event))"
      />
    </div>
  </div>
</template>

<style scoped>
.courts-filter-bar {
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

.search-box {
  position: relative;
  flex: 1;
  min-width: 240px;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 10px 14px 10px 38px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  transition: border-color 0.2s ease;
}

.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(201, 161, 90, 0.15);
}

.selects-box {
  min-width: 230px;
}

@media (max-width: 640px) {
  .courts-filter-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .selects-box {
    width: 100%;
    min-width: 0;
  }
}
</style>
