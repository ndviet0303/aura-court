<script setup lang="ts">
import type { BookingTab } from '../../composables/useUserBookings'

defineProps<{
  activeTab: BookingTab
  search: string
  totalCount: number
  upcomingCount: number
  completedCount: number
  cancelledCount: number
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: BookingTab): void
  (e: 'update:search', val: string): void
}>()
</script>

<template>
  <div class="tabs-and-search-container">
    <!-- Filter Tabs -->
    <div class="booking-tabs-bar" role="tablist">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'all' }"
        type="button"
        role="tab"
        :aria-selected="activeTab === 'all'"
        @click="emit('update:activeTab', 'all')"
      >
        <span class="material-symbols-outlined tab-icon">view_list</span>
        <span>{{ $t('booking.tabAll') }}</span>
        <span class="count-pill">{{ totalCount }}</span>
      </button>

      <button
        class="tab-btn"
        :class="{ active: activeTab === 'upcoming' }"
        type="button"
        role="tab"
        :aria-selected="activeTab === 'upcoming'"
        @click="emit('update:activeTab', 'upcoming')"
      >
        <span class="material-symbols-outlined tab-icon">schedule</span>
        <span>{{ $t('booking.tabUpcoming') }}</span>
        <span class="count-pill upcoming-pill">{{ upcomingCount }}</span>
      </button>

      <button
        class="tab-btn"
        :class="{ active: activeTab === 'completed' }"
        type="button"
        role="tab"
        :aria-selected="activeTab === 'completed'"
        @click="emit('update:activeTab', 'completed')"
      >
        <span class="material-symbols-outlined tab-icon">check_circle</span>
        <span>{{ $t('booking.tabCompleted') }}</span>
        <span class="count-pill">{{ completedCount }}</span>
      </button>

      <button
        class="tab-btn"
        :class="{ active: activeTab === 'cancelled' }"
        type="button"
        role="tab"
        :aria-selected="activeTab === 'cancelled'"
        @click="emit('update:activeTab', 'cancelled')"
      >
        <span class="material-symbols-outlined tab-icon">cancel</span>
        <span>{{ $t('booking.tabCancelled') }}</span>
        <span class="count-pill">{{ cancelledCount }}</span>
      </button>
    </div>

    <!-- Search Input -->
    <div class="search-input-wrap">
      <span class="material-symbols-outlined search-icon">search</span>
      <input
        :value="search"
        type="text"
        class="search-input"
        :placeholder="$t('booking.searchPlaceholder')"
        :aria-label="$t('booking.searchPlaceholder')"
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="search"
        class="btn-clear-search"
        type="button"
        :title="$t('picker.clear')"
        @click="emit('update:search', '')"
      >
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.tabs-and-search-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.booking-tabs-bar {
  display: flex;
  gap: 6px;
  background: var(--surface);
  padding: 6px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  overflow-x: auto;
  scrollbar-width: none;
}

.booking-tabs-bar::-webkit-scrollbar {
  display: none;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  color: var(--ink);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
}

.tab-btn.active {
  background: var(--primary, #6B2D3F);
  color: var(--on-primary, #fff);
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.2);
}

.tab-icon {
  font-size: 17px;
}

.count-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 9999px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.06));
  color: inherit;
}

.tab-btn.active .count-pill {
  background: rgba(255, 255, 255, 0.25);
  color: #fff;
}

.upcoming-pill {
  background: rgba(201, 161, 90, 0.2);
  color: var(--accent-hover, #b88e45);
}

.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 260px;
  max-width: 340px;
  flex: 1;
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
  padding: 9px 36px 9px 38px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-input:focus {
  border-color: var(--primary, #6B2D3F);
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.12);
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
  justify-content: center;
  padding: 2px;
}

.btn-clear-search .material-symbols-outlined {
  font-size: 16px;
}

@media (max-width: 768px) {
  .tabs-and-search-container {
    flex-direction: column;
    align-items: stretch;
  }
  .search-input-wrap {
    max-width: 100%;
    min-width: 100%;
  }
}
</style>
