<script setup lang="ts">
defineProps<{
  activeTab: 'all' | 'held' | 'confirmed' | 'cancelled'
  searchQuery: string
  totalCount: number
  heldCount: number
  confirmedCount: number
  cancelledCount: number
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'all' | 'held' | 'confirmed' | 'cancelled'): void
  (e: 'update:searchQuery', query: string): void
}>()
</script>

<template>
  <div class="v2-tabs-bar">
    <!-- Status Filter Tabs -->
    <div class="tabs-nav-list" role="tablist">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'all' }"
        @click="emit('update:activeTab', 'all')"
      >
        <span>Tất cả</span>
        <span class="tab-badge font-mono">{{ totalCount }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'held' }"
        @click="emit('update:activeTab', 'held')"
      >
        <span>Đang giữ chỗ</span>
        <span class="tab-badge font-mono held-badge">{{ heldCount }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'confirmed' }"
        @click="emit('update:activeTab', 'confirmed')"
      >
        <span>Đã xác nhận</span>
        <span class="tab-badge font-mono confirmed-badge">{{ confirmedCount }}</span>
      </button>

      <button
        v-if="cancelledCount > 0"
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'cancelled' }"
        @click="emit('update:activeTab', 'cancelled')"
      >
        <span>Đã hủy</span>
        <span class="tab-badge font-mono muted-badge">{{ cancelledCount }}</span>
      </button>
    </div>

    <!-- Search Input -->
    <div class="search-box">
      <span class="material-symbols-outlined search-icon">search</span>
      <input
        :value="searchQuery"
        type="text"
        placeholder="Tìm theo mã đơn, tên sân..."
        class="search-input"
        @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="searchQuery"
        class="clear-search-btn"
        type="button"
        @click="emit('update:searchQuery', '')"
      >
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.v2-tabs-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.tabs-nav-list {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-subtle);
  padding: 4px;
  border-radius: 12px;
  border: 1px solid var(--border);
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  color: var(--ink);
}

.tab-btn.active {
  background: var(--surface);
  color: var(--ink);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.tab-badge {
  padding: 1px 6px;
  border-radius: 9999px;
  font-size: 11px;
  background: var(--border);
  color: var(--ink);
}

.tab-btn.active .tab-badge {
  background: var(--primary-container, rgba(107, 45, 63, 0.1));
  color: var(--primary, #6B2D3F);
}

.tab-badge.held-badge {
  background: var(--warning-bg, rgba(239, 108, 0, 0.15));
  color: var(--warning-text, #ef6c00);
}

.tab-badge.confirmed-badge {
  background: var(--success-bg, rgba(46, 125, 50, 0.15));
  color: var(--success-text, #2e7d32);
}

.tab-badge.muted-badge {
  opacity: 0.7;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 240px;
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
  padding: 8px 32px 8px 36px;
  border-radius: 9999px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  transition: all 0.2s ease;
}

.search-input:focus {
  border-color: var(--primary, #6B2D3F);
  box-shadow: 0 0 0 3px rgba(107, 45, 63, 0.1);
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
}

.clear-search-btn .material-symbols-outlined {
  font-size: 16px;
}

@media (max-width: 768px) {
  .v2-tabs-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .tabs-nav-list {
    overflow-x: auto;
  }
}
</style>
