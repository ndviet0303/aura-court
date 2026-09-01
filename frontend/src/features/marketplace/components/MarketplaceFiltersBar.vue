<script setup lang="ts">
import { computed } from 'vue'
import { SelectDropdown, AuraButton } from '@/shared/components'

const props = defineProps<{
  search: string
  timePeriod: string
  venue: string
  sortBy: string
  venuesList: string[]
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:timePeriod', val: string): void
  (e: 'update:venue', val: string): void
  (e: 'update:sortBy', val: 'newest' | 'price_asc' | 'price_desc' | 'time_asc'): void
  (e: 'reset'): void
}>()

const venueOptions = computed(() => [
  { value: 'all', label: 'Tất cả cụm sân', icon: 'location_on' },
  ...props.venuesList.map((v) => ({ value: v, label: v, icon: 'location_on' })),
])

const timePeriodOptions = [
  { value: 'all', label: 'Tất cả khung giờ', icon: 'schedule' },
  { value: 'morning', label: 'Sáng (06:00 - 12:00)', icon: 'wb_sunny' },
  { value: 'afternoon', label: 'Chiều (12:00 - 17:00)', icon: 'wb_twilight' },
  { value: 'evening', label: 'Giờ vàng tối (17:00 - 22:00)', icon: 'nights_stay' },
]

const sortOptions = [
  { value: 'newest', label: 'Mới đăng nhất', icon: 'swap_vert' },
  { value: 'price_asc', label: 'Giá: Thấp đến Cao', icon: 'trending_down' },
  { value: 'price_desc', label: 'Giá: Cao đến Thấp', icon: 'trending_up' },
  { value: 'time_asc', label: 'Giờ chơi: Sớm nhất', icon: 'schedule' },
]

const hasActiveFilters = computed(() => {
  return (
    props.search.trim() !== '' ||
    props.venue !== 'all' ||
    props.timePeriod !== 'all' ||
    props.sortBy !== 'newest'
  )
})
</script>

<template>
  <div class="filters-bar-card">
    <!-- Search Input -->
    <div class="search-input-wrap">
      <span class="material-symbols-outlined search-icon">search</span>
      <input
        :value="search"
        type="text"
        class="search-input"
        placeholder="Tìm theo tên sân, cụm sân, người bán..."
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="search"
        type="button"
        class="btn-clear-search"
        title="Xóa tìm kiếm"
        @click="emit('update:search', '')"
      >
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <!-- Dropdowns Group -->
    <div class="dropdowns-group">
      <!-- Venue Dropdown -->
      <div class="dropdown-wrapper">
        <SelectDropdown
          :model-value="venue"
          :options="venueOptions"
          placeholder="Tất cả cụm sân"
          variant="bordered"
          aria-label="Cụm sân"
          @update:model-value="emit('update:venue', String($event))"
        />
      </div>

      <!-- Time Period Dropdown -->
      <div class="dropdown-wrapper">
        <SelectDropdown
          :model-value="timePeriod"
          :options="timePeriodOptions"
          placeholder="Tất cả khung giờ"
          variant="bordered"
          aria-label="Khung giờ"
          @update:model-value="emit('update:timePeriod', String($event))"
        />
      </div>

      <!-- Sort Dropdown -->
      <div class="dropdown-wrapper">
        <SelectDropdown
          :model-value="sortBy"
          :options="sortOptions"
          placeholder="Sắp xếp"
          variant="bordered"
          aria-label="Sắp xếp"
          @update:model-value="emit('update:sortBy', $event as any)"
        />
      </div>

      <!-- Reset Filter Button -->
      <AuraButton
        v-if="hasActiveFilters"
        variant="ghost"
        size="sm"
        icon="filter_alt_off"
        class="btn-reset-filters"
        @click="emit('reset')"
      >
        Đặt lại
      </AuraButton>
    </div>
  </div>
</template>

<style scoped>
.filters-bar-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 14px;
  padding: 14px 18px;
  border-radius: 18px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: var(--shadow-sm);
}

.search-input-wrap {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 240px;
  position: relative;
}

.search-icon {
  position: absolute;
  left: 14px;
  font-size: 20px;
  color: var(--muted);
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 10px 38px 10px 42px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 13.5px;
  transition: all 0.2s ease;
  font-family: inherit;
}

.search-input:focus {
  outline: none;
  border-color: var(--primary, #6B2D3F);
  background: var(--surface);
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.1);
}

.btn-clear-search {
  position: absolute;
  right: 10px;
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  padding: 4px;
  border-radius: 50%;
}

.btn-clear-search:hover {
  color: var(--ink);
}

.btn-clear-search .material-symbols-outlined {
  font-size: 18px;
}

.dropdowns-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.dropdown-wrapper {
  min-width: 175px;
  max-width: 230px;
  flex: 1;
}

.btn-reset-filters {
  color: var(--accent, #C9A15A);
  font-size: 12.5px;
  padding: 8px 12px;
  border-radius: 10px;
}

@media (max-width: 900px) {
  .filters-bar-card {
    flex-direction: column;
    align-items: stretch;
    padding: 12px;
  }
  .dropdowns-group {
    width: 100%;
  }
  .dropdown-wrapper {
    min-width: 100%;
  }
}
</style>
