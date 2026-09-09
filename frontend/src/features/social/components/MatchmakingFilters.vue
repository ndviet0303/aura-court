<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SocialSportType } from '../types/social'
import { SelectDropdown, AuraButton } from '@/shared/components'

const { t } = useI18n()

defineProps<{
  activeTab: 'all' | 'open' | 'my_matches' | 'partners' | 'feed' | 'connections'
  searchQuery: string
  selectedSport: SocialSportType
  selectedSkill: string
  selectedCity: string
  totalCount: number
  openCount: number
  myMatchesCount: number
  partnersCount: number
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'all' | 'open' | 'my_matches' | 'partners' | 'feed' | 'connections'): void
  (e: 'update:searchQuery', val: string): void
  (e: 'update:selectedSport', sport: SocialSportType): void
  (e: 'update:selectedSkill', skill: string): void
  (e: 'update:selectedCity', city: string): void
  (e: 'reset'): void
}>()

const sportsList = computed<Array<{ id: SocialSportType; label: string; icon: string }>>(() => [
  { id: 'all', label: t('matchmaking.filterAllSports'), icon: 'sports' },
  { id: 'badminton', label: t('tournament.filterBadminton'), icon: 'sports_tennis' },
  { id: 'pickleball', label: t('tournament.filterPickleball'), icon: 'sports_tennis' },
  { id: 'tennis', label: t('tournament.filterTennis'), icon: 'sports_tennis' },
  { id: 'football', label: t('tournament.filterFootball5'), icon: 'sports_soccer' },
  { id: 'basketball', label: t('home.filterBasketball'), icon: 'sports_basketball' },
  { id: 'volleyball', label: 'Bóng chuyền', icon: 'sports_volleyball' },
  { id: 'table_tennis', label: 'Bóng bàn', icon: 'sports_tennis' },
])

const skillOptions = computed(() => [
  { value: 'all', label: t('matchmaking.filterAllSkills') },
  { value: '2', label: 'Level 1 - 2 (' + t('matchmaking.levelBeginner') + ')' },
  { value: '3', label: 'Level 3 (' + t('matchmaking.levelIntermediate') + ')' },
  { value: '4', label: 'Level 4 (Khá / Thi đấu)' },
  { value: '5', label: 'Level 5 (' + t('matchmaking.levelAdvanced') + ')' },
])

const cityOptions = computed(() => [
  { value: 'all', label: t('matchmaking.filterAllCities') },
  { value: 'Hà Nội', label: 'Hà Nội' },
  { value: 'TP. Hồ Chí Minh', label: 'TP. Hồ Chí Minh' },
])
</script>

<template>
  <div class="matchmaking-filters-container">
    <!-- Navigation Tabs -->
    <div class="filter-tabs-row" role="tablist">
      <button
        type="button"
        role="tab"
        class="tab-btn"
        :class="{ active: activeTab === 'all' }"
        @click="emit('update:activeTab', 'all')"
      >
        <span class="material-symbols-outlined tab-ico">list_alt</span>
        <span>{{ $t('matchmaking.tabAllMatches') }}</span>
        <span class="tab-badge">{{ totalCount }}</span>
      </button>

      <button
        type="button"
        role="tab"
        class="tab-btn"
        :class="{ active: activeTab === 'open' }"
        @click="emit('update:activeTab', 'open')"
      >
        <span class="material-symbols-outlined tab-ico">how_to_reg</span>
        <span>{{ $t('matchmaking.tabOpen') }}</span>
        <span class="tab-badge highlight">{{ openCount }}</span>
      </button>

      <button
        type="button"
        role="tab"
        class="tab-btn"
        :class="{ active: activeTab === 'my_matches' }"
        @click="emit('update:activeTab', 'my_matches')"
      >
        <span class="material-symbols-outlined tab-ico">person_check</span>
        <span>{{ $t('matchmaking.tabMyMatches') }}</span>
        <span v-if="myMatchesCount > 0" class="tab-badge">{{ myMatchesCount }}</span>
      </button>

      <button
        type="button"
        role="tab"
        class="tab-btn"
        :class="{ active: activeTab === 'partners' }"
        @click="emit('update:activeTab', 'partners')"
      >
        <span class="material-symbols-outlined tab-ico">handshake</span>
        <span>{{ $t('matchmaking.tabPartners') }}</span>
        <span class="tab-badge">{{ partnersCount }}</span>
      </button>

      <button
        type="button"
        role="tab"
        class="tab-btn"
        :class="{ active: activeTab === 'feed' }"
        @click="emit('update:activeTab', 'feed')"
      >
        <span class="material-symbols-outlined tab-ico">dynamic_feed</span>
        <span>Bảng tin CLB</span>
      </button>

      <button
        type="button"
        role="tab"
        class="tab-btn"
        :class="{ active: activeTab === 'connections' }"
        @click="emit('update:activeTab', 'connections')"
      >
        <span class="material-symbols-outlined tab-ico">people</span>
        <span>Bạn bè & Kết nối</span>
      </button>
    </div>

    <!-- Sports Selector Chips -->
    <div class="sports-chips-scroll">
      <button
        v-for="sport in sportsList"
        :key="sport.id"
        type="button"
        class="sport-chip"
        :class="{ active: selectedSport === sport.id }"
        @click="emit('update:selectedSport', sport.id)"
      >
        <span class="material-symbols-outlined chip-icon">{{ sport.icon }}</span>
        <span>{{ sport.label }}</span>
      </button>
    </div>

    <!-- Search & Dropdown Filters Toolbar -->
    <div class="filters-toolbar">
      <div class="search-input-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="search-input"
          :placeholder="$t('matchmaking.searchPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="clear-search-btn"
          :aria-label="$t('picker.clear')"
          @click="emit('update:searchQuery', '')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="dropdowns-group">
        <div class="dropdown-item">
          <SelectDropdown
            :model-value="selectedSkill"
            :options="skillOptions"
            :placeholder="$t('matchmaking.filterAllSkills')"
            @update:model-value="emit('update:selectedSkill', String($event))"
          />
        </div>

        <div class="dropdown-item">
          <SelectDropdown
            :model-value="selectedCity"
            :options="cityOptions"
            :placeholder="$t('matchmaking.filterAllCities')"
            @update:model-value="emit('update:selectedCity', String($event))"
          />
        </div>

        <AuraButton
          v-if="searchQuery || selectedSport !== 'all' || selectedSkill !== 'all' || selectedCity !== 'all'"
          variant="ghost"
          size="md"
          icon="restart_alt"
          @click="emit('reset')"
        >
          {{ $t('matchmaking.resetFilters') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.matchmaking-filters-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.filter-tabs-row {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 4px;
  overflow-x: auto;
  scrollbar-width: none;
}
.filter-tabs-row::-webkit-scrollbar {
  display: none;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: 0;
  border-radius: 10px 10px 0 0;
  background: transparent;
  color: var(--muted);
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
  border-bottom: 2px solid transparent;
}

.tab-btn:hover {
  color: var(--ink);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
}

.tab-btn.active {
  color: var(--pink-dark, #db2777);
  border-bottom-color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.04);
}

.tab-ico {
  font-size: 18px;
}

.tab-badge {
  padding: 2px 7px;
  border-radius: 999px;
  background: var(--surface-container, rgba(0, 0, 0, 0.06));
  font-size: 11px;
  font-weight: 800;
}

.tab-badge.highlight {
  background: rgba(219, 39, 119, 0.15);
  color: var(--pink-dark, #db2777);
}

.sports-chips-scroll {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 2px 0;
  scrollbar-width: none;
}
.sports-chips-scroll::-webkit-scrollbar {
  display: none;
}

.sport-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12.5px;
  font-weight: 650;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.sport-chip:hover {
  border-color: rgba(219, 39, 119, 0.4);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
}

.sport-chip.active {
  background: var(--pink-dark, #db2777);
  border-color: var(--pink-dark, #db2777);
  color: #fff;
  box-shadow: 0 2px 8px rgba(219, 39, 119, 0.25);
}

.chip-icon {
  font-size: 16px;
}

.filters-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 280px;
}

.search-icon {
  position: absolute;
  left: 14px;
  color: var(--muted);
  font-size: 18px;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 11px 36px 11px 40px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-input:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.clear-search-btn {
  position: absolute;
  right: 10px;
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2px;
  border-radius: 50%;
}

.clear-search-btn:hover {
  color: var(--ink);
}

.dropdowns-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.dropdown-item {
  min-width: 190px;
}

@media (max-width: 768px) {
  .dropdowns-group {
    width: 100%;
  }
  .dropdown-item {
    flex: 1;
    min-width: 140px;
  }
}
</style>
