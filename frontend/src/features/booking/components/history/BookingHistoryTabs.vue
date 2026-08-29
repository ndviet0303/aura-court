<script setup lang="ts">
defineProps<{
  activeTab: 'all' | 'upcoming' | 'completed' | 'cancelled'
  totalCount: number
  upcomingCount: number
  completedCount: number
  cancelledCount: number
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'all' | 'upcoming' | 'completed' | 'cancelled'): void
}>()
</script>

<template>
  <div class="booking-tabs-bar">
    <button
      class="tab-btn"
      :class="{ active: activeTab === 'all' }"
      @click="emit('update:activeTab', 'all')"
    >
      Tất cả ({{ totalCount }})
    </button>
    <button
      class="tab-btn"
      :class="{ active: activeTab === 'upcoming' }"
      @click="emit('update:activeTab', 'upcoming')"
    >
      ⏰ Sắp diễn ra ({{ upcomingCount }})
    </button>
    <button
      class="tab-btn"
      :class="{ active: activeTab === 'completed' }"
      @click="emit('update:activeTab', 'completed')"
    >
      ✓ Đã hoàn thành ({{ completedCount }})
    </button>
    <button
      class="tab-btn"
      :class="{ active: activeTab === 'cancelled' }"
      @click="emit('update:activeTab', 'cancelled')"
    >
      ✕ Đã huỷ ({{ cancelledCount }})
    </button>
  </div>
</template>

<style scoped>
.booking-tabs-bar {
  display: flex;
  gap: 8px;
  background: var(--surface);
  padding: 6px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  width: fit-content;
}

.tab-btn {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.tab-btn.active {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  color: var(--pink-dark, #db2777);
  font-weight: 700;
}
</style>
