<script setup lang="ts">
import { computed } from 'vue'
import { SelectDropdown, AuraButton } from '@/shared/components'

const props = defineProps<{
  activeTab: 'all' | 'open' | 'ongoing' | 'completed' | 'my'
  searchQuery: string
  selectedSport: string
  selectedFormat: string
  openCount?: number
  ongoingCount?: number
  totalCount?: number
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'all' | 'open' | 'ongoing' | 'completed' | 'my'): void
  (e: 'update:searchQuery', q: string): void
  (e: 'update:selectedSport', sport: string): void
  (e: 'update:selectedFormat', format: string): void
  (e: 'reset'): void
}>()

const sportOptions = [
  { value: 'all', label: 'Tất cả bộ môn', icon: 'sports' },
  { value: 'badminton', label: 'Cầu lông (Badminton)', icon: 'sports_handball' },
  { value: 'pickleball', label: 'Pickleball', icon: 'sports_tennis' },
  { value: 'tennis', label: 'Tennis (Quần vợt)', icon: 'sports_tennis' },
  { value: 'football_5v5', label: 'Bóng đá mini 5v5', icon: 'sports_soccer' },
]

const formatOptions = [
  { value: 'all', label: 'Mọi thể thức', icon: 'tune' },
  { value: 'single_elimination', label: 'Loại trực tiếp (Knockout)', icon: 'account_tree' },
  { value: 'round_robin', label: 'Vòng tròn tính điểm (Round Robin)', icon: 'sync' },
  { value: 'double_elimination', label: 'Nhánh Thắng - Thua (Double)', icon: 'call_split' },
]

const isFiltered = computed(() => {
  return (
    props.activeTab !== 'all' ||
    props.searchQuery.trim() !== '' ||
    props.selectedSport !== 'all' ||
    props.selectedFormat !== 'all'
  )
})
</script>

<template>
  <div class="tournament-filters-wrap">
    <!-- Status Tabs Row -->
    <div class="status-tabs-row" role="tablist">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'all' }"
        role="tab"
        :aria-selected="activeTab === 'all'"
        @click="emit('update:activeTab', 'all')"
      >
        <span class="material-symbols-outlined tab-ico">view_list</span>
        <span>Tất Cả Giải</span>
        <span v-if="totalCount !== undefined" class="tab-count">{{ totalCount }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'open' }"
        role="tab"
        :aria-selected="activeTab === 'open'"
        @click="emit('update:activeTab', 'open')"
      >
        <span class="material-symbols-outlined tab-ico">how_to_reg</span>
        <span>Đang Mở Đăng Ký</span>
        <span v-if="openCount" class="tab-count highlight-green">{{ openCount }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'ongoing' }"
        role="tab"
        :aria-selected="activeTab === 'ongoing'"
        @click="emit('update:activeTab', 'ongoing')"
      >
        <span class="material-symbols-outlined tab-ico">bolt</span>
        <span>Đang Tranh Tài</span>
        <span v-if="ongoingCount" class="tab-count highlight-pink">{{ ongoingCount }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'completed' }"
        role="tab"
        :aria-selected="activeTab === 'completed'"
        @click="emit('update:activeTab', 'completed')"
      >
        <span class="material-symbols-outlined tab-ico">check_circle</span>
        <span>Đã Kết Thúc</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'my' }"
        role="tab"
        :aria-selected="activeTab === 'my'"
        @click="emit('update:activeTab', 'my')"
      >
        <span class="material-symbols-outlined tab-ico">person</span>
        <span>Giải Của Tôi</span>
      </button>
    </div>

    <!-- Filter Controls Toolbar -->
    <div class="filter-controls-bar">
      <!-- Search Input -->
      <div class="search-input-wrap">
        <span class="material-symbols-outlined search-ico">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="search-input"
          placeholder="Tìm giải theo tên, địa điểm, thể thức..."
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="clear-search-btn"
          title="Xoá tìm kiếm"
          @click="emit('update:searchQuery', '')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Select Sport Dropdown -->
      <div class="dropdown-wrap">
        <SelectDropdown
          :model-value="selectedSport"
          :options="sportOptions"
          variant="bordered"
          aria-label="Chọn bộ môn"
          @update:model-value="emit('update:selectedSport', String($event))"
        />
      </div>

      <!-- Select Format Dropdown -->
      <div class="dropdown-wrap">
        <SelectDropdown
          :model-value="selectedFormat"
          :options="formatOptions"
          variant="bordered"
          aria-label="Chọn thể thức"
          @update:model-value="emit('update:selectedFormat', String($event))"
        />
      </div>

      <!-- Reset Filter Button -->
      <AuraButton
        v-if="isFiltered"
        variant="ghost"
        size="md"
        icon="restart_alt"
        @click="emit('reset')"
      >
        Đặt lại
      </AuraButton>
    </div>
  </div>
</template>

<style scoped>
.tournament-filters-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.status-tabs-row {
  display: flex;
  align-items: center;
  gap: 10px;
  overflow-x: auto;
  padding-bottom: 4px;
  scrollbar-width: none;
}

.status-tabs-row::-webkit-scrollbar {
  display: none;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 650;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
  user-select: none;
}

.tab-btn:hover {
  background: var(--surface-container-high, rgba(0, 0, 0, 0.04));
  border-color: var(--primary-border, var(--pink-dark));
}

.tab-btn.active {
  background: var(--primary, #6b2d3f);
  color: var(--on-primary, #ffffff);
  border-color: var(--primary, #6b2d3f);
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.25);
}

:global(html.dark .tab-btn.active) {
  background: var(--primary, #e58e9f);
  color: #1c0a10;
  border-color: var(--primary, #e58e9f);
  box-shadow: 0 4px 18px rgba(229, 142, 159, 0.35);
}

.tab-ico {
  font-size: 18px;
}

.tab-count {
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  background: rgba(0, 0, 0, 0.1);
}

.tab-btn.active .tab-count {
  background: rgba(255, 255, 255, 0.25);
}

.highlight-green {
  color: #10b981;
}

.highlight-pink {
  color: #e11d48;
}

.filter-controls-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input-wrap {
  position: relative;
  flex: 1 1 280px;
  min-width: 240px;
  display: flex;
  align-items: center;
}

.search-ico {
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
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-family: inherit;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-input:focus {
  outline: none;
  border-color: var(--pink-dark);
  box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.15);
}

.clear-search-btn {
  position: absolute;
  right: 10px;
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 50%;
}

.clear-search-btn:hover {
  color: var(--ink);
  background: rgba(0, 0, 0, 0.05);
}

.clear-search-btn .material-symbols-outlined {
  font-size: 16px;
}

.dropdown-wrap {
  flex: 1 1 200px;
  min-width: 190px;
}
</style>

