<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SelectDropdown, AuraButton } from '@/shared/components'

export interface SportCategory {
  id: string
  labelKey: string
  defaultLabel: string
  icon: string
}

const { t } = useI18n()

const props = withDefaults(
  defineProps<{
    search: string
    sportType?: string
    venue: string
    priceRange?: string
    sortBy?: string
    venueOptions: string[]
    totalCount?: number
    filteredCount?: number
    hasActiveFilters?: boolean
    sportCategories?: SportCategory[]
  }>(),
  {
    sportType: 'all',
    priceRange: 'all',
    sortBy: 'popular',
    totalCount: 0,
    filteredCount: 0,
    hasActiveFilters: false,
    sportCategories: () => [
      { id: 'all', labelKey: 'courts.allSports', defaultLabel: 'Tất cả môn', icon: 'apps' },
      { id: 'badminton', labelKey: 'courts.sportBadminton', defaultLabel: 'Cầu lông', icon: 'sports_tennis' },
      { id: 'pickleball', labelKey: 'courts.sportPickleball', defaultLabel: 'Pickleball', icon: 'sports_tennis' },
      { id: 'tennis', labelKey: 'courts.sportTennis', defaultLabel: 'Tennis', icon: 'sports_tennis' },
      { id: 'basketball', labelKey: 'courts.sportBasketball', defaultLabel: 'Bóng rổ', icon: 'sports_basketball' },
      { id: 'football', labelKey: 'courts.sportFootball', defaultLabel: 'Bóng đá', icon: 'sports_soccer' },
      { id: 'table_tennis', labelKey: 'courts.sportTableTennis', defaultLabel: 'Bóng bàn', icon: 'table_restaurant' },
      { id: 'volleyball', labelKey: 'courts.sportVolleyball', defaultLabel: 'Bóng chuyền', icon: 'sports_volleyball' },
    ],
  }
)

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:sportType', val: string): void
  (e: 'update:venue', val: string): void
  (e: 'update:priceRange', val: string): void
  (e: 'update:sortBy', val: string): void
  (e: 'reset'): void
}>()

const venueDropdownOptions = computed(() => {
  return [
    { value: 'all', label: t('courts.allVenues'), icon: 'stadium' },
    ...props.venueOptions
      .filter((x) => x !== 'all')
      .map((v) => ({ value: v, label: v, icon: 'location_on' })),
  ]
})

const priceDropdownOptions = computed(() => [
  { value: 'all', label: t('courts.priceAll'), icon: 'payments' },
  { value: 'under_100k', label: t('courts.priceUnder100k'), icon: 'payments' },
  { value: '100k_150k', label: t('courts.price100k150k'), icon: 'payments' },
  { value: '150k_200k', label: t('courts.price150k200k'), icon: 'payments' },
  { value: 'over_200k', label: t('courts.priceOver200k'), icon: 'payments' },
])

const sortDropdownOptions = computed(() => [
  { value: 'popular', label: t('courts.sortPopular'), icon: 'trending_up' },
  { value: 'price_asc', label: t('courts.sortPriceAsc'), icon: 'arrow_upward' },
  { value: 'price_desc', label: t('courts.sortPriceDesc'), icon: 'arrow_downward' },
  { value: 'rating_desc', label: t('courts.sortRating'), icon: 'star' },
  { value: 'name_asc', label: t('courts.sortNameAsc'), icon: 'sort_by_alpha' },
])

const activeSportLabel = computed(() => {
  const found = props.sportCategories.find((s) => s.id === props.sportType)
  return found ? t(found.labelKey, found.defaultLabel) : ''
})

const activePriceLabel = computed(() => {
  const found = priceDropdownOptions.value.find((p) => p.value === props.priceRange)
  return found?.label || ''
})

const activeSortLabel = computed(() => {
  const found = sortDropdownOptions.value.find((s) => s.value === props.sortBy)
  return found?.label || ''
})
</script>

<template>
  <div class="courts-filters-section" data-testid="courts-filters-section">
    <!-- Tier 1: Multi-Sport Categories Strip -->
    <div
      class="sport-categories-strip"
      role="tablist"
      :aria-label="t('courts.refineSearch')"
      data-testid="sport-categories-strip"
    >
      <button
        v-for="cat in sportCategories"
        :key="cat.id"
        type="button"
        role="tab"
        :aria-selected="sportType === cat.id"
        class="sport-category-btn"
        :class="{ active: sportType === cat.id }"
        :data-testid="`sport-btn-${cat.id}`"
        @click="emit('update:sportType', cat.id)"
      >
        <span class="material-symbols-outlined">{{ cat.icon }}</span>
        <span>{{ t(cat.labelKey, cat.defaultLabel) }}</span>
      </button>
    </div>

    <!-- Tier 2: Main Filter Bar Controls -->
    <div class="courts-filter-bar">
      <!-- Search Input -->
      <div class="search-box">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="search"
          type="text"
          class="search-input"
          :placeholder="t('courts.searchPlaceholder')"
          :aria-label="t('courts.searchPlaceholder')"
          data-testid="courts-search-input"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="search"
          type="button"
          class="btn-clear-search"
          :title="t('courts.clearSearch')"
          :aria-label="t('courts.clearSearch')"
          @click="emit('update:search', '')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Dropdowns Row -->
      <div class="filter-dropdowns">
        <!-- 1. Venue Dropdown (Kept first for test compatibility) -->
        <div class="dropdown-wrap venue-wrap" data-testid="venue-dropdown-wrap">
          <SelectDropdown
            :model-value="venue"
            :options="venueDropdownOptions"
            variant="bordered"
            :aria-label="t('courts.allVenues')"
            @update:model-value="emit('update:venue', String($event))"
          />
        </div>

        <!-- 2. Price Range Dropdown -->
        <div class="dropdown-wrap price-wrap" data-testid="price-dropdown-wrap">
          <SelectDropdown
            :model-value="priceRange"
            :options="priceDropdownOptions"
            variant="bordered"
            :aria-label="t('courts.priceRange')"
            @update:model-value="emit('update:priceRange', String($event))"
          />
        </div>

        <!-- 3. Sort By Dropdown -->
        <div class="dropdown-wrap sort-wrap" data-testid="sort-dropdown-wrap">
          <SelectDropdown
            :model-value="sortBy"
            :options="sortDropdownOptions"
            variant="bordered"
            :aria-label="t('courts.sortBy')"
            @update:model-value="emit('update:sortBy', String($event))"
          />
        </div>

        <!-- Reset Button -->
        <AuraButton
          v-if="hasActiveFilters"
          variant="ghost"
          size="sm"
          icon="filter_alt_off"
          class="btn-reset-filters"
          data-testid="btn-reset-filters"
          @click="emit('reset')"
        >
          {{ t('courts.resetFilters') }}
        </AuraButton>
      </div>
    </div>

    <!-- Tier 3: Results Count & Active Filter Chips -->
    <div v-if="hasActiveFilters || filteredCount > 0" class="filter-status-bar">
      <div class="status-left">
        <span class="results-count-badge font-mono">
          <span class="material-symbols-outlined count-icon">sports_score</span>
          <span>{{ t('courts.showingResults', { count: filteredCount }) }}</span>
        </span>
      </div>

      <div v-if="hasActiveFilters" class="active-chips-wrap">
        <!-- Active Sport Chip -->
        <button
          v-if="sportType !== 'all'"
          type="button"
          class="filter-chip"
          @click="emit('update:sportType', 'all')"
        >
          <span>{{ activeSportLabel }}</span>
          <span class="material-symbols-outlined chip-x">close</span>
        </button>

        <!-- Active Venue Chip -->
        <button
          v-if="venue !== 'all'"
          type="button"
          class="filter-chip"
          @click="emit('update:venue', 'all')"
        >
          <span>{{ venue }}</span>
          <span class="material-symbols-outlined chip-x">close</span>
        </button>

        <!-- Active Price Chip -->
        <button
          v-if="priceRange !== 'all'"
          type="button"
          class="filter-chip"
          @click="emit('update:priceRange', 'all')"
        >
          <span>{{ activePriceLabel }}</span>
          <span class="material-symbols-outlined chip-x">close</span>
        </button>

        <!-- Active Sort Chip (if not default) -->
        <button
          v-if="sortBy !== 'popular'"
          type="button"
          class="filter-chip"
          @click="emit('update:sortBy', 'popular')"
        >
          <span>{{ activeSortLabel }}</span>
          <span class="material-symbols-outlined chip-x">close</span>
        </button>

        <!-- Clear All Text Link -->
        <button
          type="button"
          class="btn-clear-all-text"
          @click="emit('reset')"
        >
          {{ t('courts.clearFilters') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.courts-filters-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
}

/* Tier 1: Sport Categories Strip */
.sport-categories-strip {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  padding: 2px 2px 4px;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  max-width: 100%;
  width: 100%;
}

.sport-categories-strip::-webkit-scrollbar {
  display: none;
}

.sport-category-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  border-radius: 9999px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
  flex-shrink: 0;
  text-decoration: none;
}

.sport-category-btn .material-symbols-outlined {
  font-size: 18px;
}

.sport-category-btn:hover {
  border-color: var(--primary);
  background: var(--primary-container);
  color: var(--on-primary-container);
  transform: translateY(-1px);
}

.sport-category-btn.active {
  background: var(--nav-active-bg, var(--primary));
  color: var(--nav-active-text, #ffffff);
  border-color: var(--primary);
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.28);
  transform: translateY(-1px);
}

:global(html.dark) .sport-category-btn.active {
  background: var(--primary);
  color: var(--on-primary);
  box-shadow: 0 4px 14px rgba(229, 142, 159, 0.35);
}

/* Tier 2: Main Filter Bar Controls */
.courts-filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px 16px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.search-box {
  position: relative;
  flex: 1 1 240px;
  min-width: 200px;
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
  padding: 10px 36px 10px 38px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(201, 161, 90, 0.15);
}

.btn-clear-search {
  position: absolute;
  right: 10px;
  background: transparent;
  border: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  color: var(--muted);
  border-radius: 50%;
  transition: color 0.15s ease, background 0.15s ease;
}

.btn-clear-search:hover {
  color: var(--ink);
  background: var(--soft);
}

.btn-clear-search .material-symbols-outlined {
  font-size: 16px;
}

.filter-dropdowns {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.dropdown-wrap {
  min-width: 180px;
  flex: 1 0 auto;
}

.dropdown-wrap.venue-wrap {
  min-width: 210px;
}

.dropdown-wrap.price-wrap {
  min-width: 170px;
}

.dropdown-wrap.sort-wrap {
  min-width: 175px;
}

.btn-reset-filters {
  white-space: nowrap;
  font-size: 12.5px;
  height: 40px;
}

/* Tier 3: Status & Active Chips */
.filter-status-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  padding: 0 4px;
}

.status-left {
  display: flex;
  align-items: center;
}

.results-count-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.count-icon {
  font-size: 16px;
  color: var(--primary);
}

.active-chips-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--soft);
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.filter-chip:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.chip-x {
  font-size: 13px;
  opacity: 0.7;
}

.btn-clear-all-text {
  background: transparent;
  border: 0;
  color: var(--primary);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 4px 6px;
  text-decoration: underline;
  transition: opacity 0.15s ease;
}

.btn-clear-all-text:hover {
  opacity: 0.8;
}

@media (max-width: 900px) {
  .dropdown-wrap {
    flex: 1 1 calc(50% - 6px);
    min-width: 140px;
  }
}

@media (max-width: 640px) {
  .courts-filter-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .search-box {
    width: 100%;
    min-width: 0;
  }
  .filter-dropdowns {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
  }
  .dropdown-wrap {
    width: 100%;
    min-width: 0;
  }
  .filter-status-bar {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
